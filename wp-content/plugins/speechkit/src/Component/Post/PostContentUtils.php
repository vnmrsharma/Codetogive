<?php

declare(strict_types=1);

namespace Beyondwords\Wordpress\Component\Post;

/**
 * BeyondWords Post Content Utilities.
 *
 * @package    Beyondwords
 * @subpackage Beyondwords/includes
 * @author     Stuart McAlpine <stu@beyondwords.io>
 * @since      3.5.0
 */
class PostContentUtils
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * Get the source text for the audio, ready to be sent to the BeyondWords API.
     *
     * @deprecated 4.0.0 Renamed to PostContentUtils::getBody()
     *
     * @param int|WP_Post $post The WordPress post ID, or post object.
     *
     * @since 3.0.0
     * @since 3.5.0  Moved from Core\Utils to Component\Post\PostUtils
     * @since 3.8.0  Exclude Gutenberg blocks with attribute { beyondwordsAudio: false }
     * @since 4.0.0 Renamed from PostContentUtils::getSourceTextForAudio() to PostContentUtils::getBody()
     *
     * @return string The body (the processed $post->post_content).
     */
    public static function getSourceTextForAudio($post)
    {
        _doing_it_wrong(
            'PostContentUtils::getBody',
            'BeyondWords PostContentUtils::getSourceTextForAudio() has been renamed to PostContentUtils::getBody()',
            '4.0.0'
        );

        return PostContentUtils::getBody($post);
    }

    /**
     * Get the body for the audio, ready to be sent to the BeyondWords API.
     *
     * The following rules are applied:
     *
     *     Main body content entered in WordPress
     *   + Optionally filtered using [SpeechKit-Start]/[SpeechKit-Stop] "shortcodes"
     *   + With registered content filters FROM OTHER PLUGINS applied
     *   + Optionally prepended with the Post excerpt
     *   + Optionally filtered using the beyondwords_content filter
     *
     * @SuppressWarnings(PHPMD.LongVariable)
     *
     * @param int|WP_Post $post The WordPress post ID, or post object.
     *
     * @since 3.0.0
     * @since 3.5.0 Moved from Core\Utils to Component\Post\PostUtils
     * @since 3.8.0 Exclude Gutenberg blocks with attribute { beyondwordsAudio: false }
     * @since 4.0.0 Renamed from PostContentUtils::getSourceTextForAudio() to PostContentUtils::getBody()
     *
     * @return string The body (the processed $post->post_content).
     */
    public static function getBody($post)
    {
        global $beyondwords_wordpress_plugin;

        $post = get_post($post);

        if (!($post instanceof \WP_Post)) {
            throw new \Exception('Post Not Found');
        }

        $content = PostContentUtils::getContentWithoutExcludedBlocks($post);

        // If SpeechKit-Start/Stop tags are present then use the content within them
        // @deprecated v3.0.0: publishers should use the beyondwords_content filter instead.
        $regex = '/\[SpeechKit-Start\](.*?)\[SpeechKit-Stop\]/s';

        if (preg_match_all($regex, $content, $match, PREG_PATTERN_ORDER) > 0) {
            $content = implode(' ', $match[1]);
        }

        // Temporarily remove our Player filter, to exclude the player <div>
        if ($beyondwords_wordpress_plugin && isset($beyondwords_wordpress_plugin->player)) {
            remove_filter('the_content', array($beyondwords_wordpress_plugin->player, 'addPlayerToContent'));
        }

        // Apply other standard WordPress filters to handle shortcodes etc
        $content = apply_filters('the_content', $content);

        // Add our Player filter back in again
        if ($beyondwords_wordpress_plugin && isset($beyondwords_wordpress_plugin->player)) {
            add_filter('the_content', array($beyondwords_wordpress_plugin->player, 'addPlayerToContent'));
        }

        // TODO maybe we need to do something like this to deal with Shortcodes a little better?
        // $content = wp_strip_all_tags(apply_filters('the_content', $content));

        // Trim to remove trailing newlines – common for WordPress content
        $content = trim($content);

        /**
         * Filters the content body we send for audio processing.
         *
         * @since 4.0.0
         *
         * @param string $content The post content.
         * @param int    $postId  The post ID.
         */
        $content = apply_filters('beyondwords_content', $content, $post->ID);

        return $content;
    }

    /**
     * Get the summary for the audio content, ready to be sent to the BeyondWords API.
     *
     * @param int|WP_Post $post The WordPress post ID, or post object.
     *
     * @since 4.0.0
     *
     * @return string The summary.
     */
    public static function getSummary($post)
    {
        $post = get_post($post);

        if (!($post instanceof \WP_Post)) {
            throw new \Exception('Post Not Found');
        }

        $summary = null;

        // Optionally send the excerpt to the REST API, if the plugin setting has been checked
        $prependExcerpt = get_option('beyondwords_prepend_excerpt');

        if ($prependExcerpt && has_excerpt($post)) {
            // Escape characters
            $summary = htmlentities($post->post_excerpt, ENT_QUOTES | ENT_XHTML);
            // Apply WordPress filters
            $summary = apply_filters('get_the_excerpt', $summary);
            // Convert line breaks into paragraphs
            $summary = trim(wpautop($summary));
        }

        return $summary;
    }

    /**
     * Get the segments for the audio content, ready to be sent to the BeyondWords API.
     *
     * THIS METHOD IS CURRENTLY NOT IN USE. Segments cannot currently include HTML
     * formatting tags such as <strong> and <em> so we do not pass segments, we pass
     * a HTML string as the body param instead.
     *
     * @param int|WP_Post $post The WordPress post ID, or post object.
     *
     * @since 4.0.0
     *
     * @return array|null The segments.
     */
    public static function getSegments($post)
    {
        if (! has_blocks($post)) {
            return null;
        }

        $titleSegment = (object) [
            'section' => 'title',
            'text'    => get_the_title($post),
        ];

        $summarySegment = (object) [
            'section' => 'summary',
            'text'    => PostContentUtils::getSummary($post),
        ];

        $blocks = PostContentUtils::getAudioEnabledBlocks($post);

        $bodySegments = array_map(function ($block) {
            $marker = null;

            if (isset($block['attrs']) && isset($block['attrs']['beyondwordsMarker'])) {
                $marker = $block['attrs']['beyondwordsMarker'];
            }

            return (object) [
                'section' => 'body',
                'marker'  => $marker,
                'text'    => trim(render_block($block)),
            ];
        }, $blocks);

        // Merge title, summary and body segments
        $segments = array_values(array_merge([$titleSegment], [$summarySegment], $bodySegments));

        // TODO Consider removing this when API can handle it
        // Remove any segments with empty text
        $segments = array_values(array_filter($segments, function ($segment) {
            return (! empty($segment->text));
        }));

        return $segments;
    }

    /**
     * Get the post content without blocks which have been filtered.
     *
     * We have added buttons into the Gutenberg editor to optionally exclude selected
     * blocks from the source text for audio.
     *
     * This method filters all blocks, removing any which have been excluded.
     *
     * @param int|WP_Post $post The WordPress post ID, or post object.
     *
     * @since 3.8.0
     * @since 4.0.0 Replace for loop with array_reduce
     *
     * @return string The post body without excluded blocks.
     */
    public static function getContentWithoutExcludedBlocks($post)
    {
        if (! has_blocks($post)) {
            return trim($post->post_content);
        }

        $blocks = parse_blocks($post->post_content);

        if (! count($blocks)) {
            return trim($post->post_content);
        }

        $blocks = PostContentUtils::getAudioEnabledBlocks($post);

        return array_reduce($blocks, static function ($carry, $block) {
            return $carry . trim(render_block($block));
        }, '');
    }

    /**
     * Get audio-enabled blocks.
     *
     * @param int|WP_Post $post The WordPress post ID, or post object.
     *
     * @since 4.0.0
     *
     * @return array The blocks.
     */
    public static function getAudioEnabledBlocks($post)
    {
        $post = get_post($post);

        if (! ($post instanceof \WP_Post)) {
            return [];
        }

        if (! has_blocks($post)) {
            return [];
        }

        $allBlocks = parse_blocks($post->post_content);

        $blocks = array_filter($allBlocks, function ($block) {
            $enabled = true;

            if (is_array($block['attrs']) && isset($block['attrs']['beyondwordsAudio'])) {
                $enabled = (bool) $block['attrs']['beyondwordsAudio'];
            }

            return $enabled;
        });

        /**
         * Filters the audio-enabled blocks for a post.
         *
         * @since 4.0.0
         *
         * @param array $blocks    The audio-enabled post blocks.
         * @param array $allBlocks All post blocks including those with audio disabled.
         * @param int   $postId    The post ID.
         */
        $blocks = apply_filters('beyondwords_post_audio_enabled_blocks', $blocks, $allBlocks, $post->ID);

        return $blocks;
    }

    /**
     * Get the body param we pass to the API.
     *
     * @since 3.0.0
     * @since 3.3.0  Added metadata to aid custom playlist generation.
     * @since 3.5.0  Moved from Core\Utils to Component\Post\PostUtils.
     * @since 3.10.4 Rename `published_at` API param to `publish_date`.
     * @since 4.0.0  Use new API params.
     * @since 4.0.3  Ensure `image_url` is always a string.
     *
     * @static
     * @param int $postId WordPress Post ID.
     *
     * @return Response
     **/
    public static function getBodyJson($postId)
    {
        // https://beyondwords.postman.co/workspace/BeyondWords-Developers~169d849b-f6e1-4a78-8b66-5c87969588fc/request/23751538-99727151-c025-43b3-bd12-66951a042e2c
        $body = [
            'type'         => 'auto_segment',
            'title'        => get_the_title($postId),
            'summary'      => PostContentUtils::getSummary($postId),
            'body'         => PostContentUtils::getBody($postId),
            'source_url'   => get_the_permalink($postId),
            'source_id'    => strval($postId),
            'author'       => PostContentUtils::getAuthorName($postId),
            'image_url'    => strval(get_the_post_thumbnail_url($postId, [240, 240])),
            'metadata'     => PostContentUtils::getMetadata($postId),
            'published'    => true,
            'publish_date' => get_post_time(PostContentUtils::DATE_FORMAT, true, $postId),
        ];

        $status = get_post_status($postId);

        /*
         * If the post status is "pending" then we send { published: false } to
         * the BeyondWords API, to prevent the generated audio from being
         * published in playlists.
         *
         * We also omit { publish_date } because get_post_time() returns `false`
         * for posts which are "Pending Review".
         */
        if ($status === 'pending') {
            $body['published'] = false;
            unset($body['publish_date']);
        }

        $bodyVoiceId = intval(get_post_meta($postId, 'beyondwords_body_voice_id', true));

        if ($bodyVoiceId > 0) {
            $body['body_voice_id'] = $bodyVoiceId;
        }

        $titleVoiceId = intval(get_post_meta($postId, 'beyondwords_title_voice_id', true));

        if ($titleVoiceId > 0) {
            $body['title_voice_id'] = $titleVoiceId;
        }

        $summaryVoiceId = intval(get_post_meta($postId, 'beyondwords_summary_voice_id', true));

        if ($summaryVoiceId > 0) {
            $body['summary_voice_id'] = $summaryVoiceId;
        }

        /**
         * Filters the body params we send to the BeyondWords API when
         * processing audio.
         *
         * @since 4.0.0
         *
         * @param array $body   The params we send to the BeyondWords API.
         * @param array $postId WordPress post ID.
         */
        $body = apply_filters('beyondwords_body_params', $body, $postId);

        return wp_json_encode($body);
    }

    /**
     * Get the post metadata to send with BeyondWords API requests.
     *
     * The metadata key is defined by the BeyondWords API as "A custom object
     * for storing meta information".
     *
     * The metadata values are used to create filters for playlists in the
     * BeyondWords dashboard.
     *
     * We currently only include taxonomies by default, and the output of this
     * method can be filtered using the `beyondwords_post_metadata` filter.
     *
     * @since 3.3.0
     * @since 3.5.0 Moved from Core\Utils to Component\Post\PostUtils
     *
     * @param int $postId Post ID.
     *
     * @return array
     */
    public static function getMetadata($postId)
    {
        $metadata = new \stdClass();

        $taxonomy = PostContentUtils::getAllTaxonomiesAndTerms($postId);

        if (count((array)$taxonomy)) {
            $metadata->taxonomy = $taxonomy;
        }

        /**
         * Filters the post metadata sent to the BeyondWords API.
         *
         * @since 3.3.0
         *
         * @param object $metadata Post metadata. Defaults to the taxonomies and terms assigned to the post.
         * @param int    $postId   Post ID.
         */
        $metadata = apply_filters('beyondwords_post_metadata', $metadata, $postId);

        return $metadata;
    }

    /**
     * Get all taxonomies, and their selected terms, for a post.
     *
     * Returns an associative array of taxonomy names and terms.
     *
     * For example:
     *
     * array(
     *     "categories" => array("Category 1"),
     *     "post_tag" => array("Tag 1", "Tag 2", "Tag 3"),
     * )
     *
     * @since 3.3.0
     * @since 3.5.0 Moved from Core\Utils to Component\Post\PostUtils
     *
     * @param int $postId Post ID.
     *
     * @return array
     */
    public static function getAllTaxonomiesAndTerms($postId)
    {
        $postType = get_post_type($postId);

        $postTypeTaxonomies = get_object_taxonomies($postType);

        $taxonomies = new \stdClass();

        foreach ($postTypeTaxonomies as $postTypeTaxonomy) {
            $terms = get_the_terms($postId, $postTypeTaxonomy);

            if (! empty($terms) && ! is_wp_error($terms)) {
                $taxonomies->{(string)$postTypeTaxonomy} = wp_list_pluck($terms, 'name');
            }
        }

        return $taxonomies;
    }

    /**
     * Get author name for a post.
     *
     * @since 3.10.4
     *
     * @param int $postId Post ID.
     *
     * @return string
     */
    public static function getAuthorName($postId)
    {
        $authorId = get_post_field('post_author', $postId);

        return get_the_author_meta('display_name', $authorId);
    }
}
