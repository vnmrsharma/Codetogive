"use strict";(self.webpackChunkbeyondwords_wordpress_plugin=self.webpackChunkbeyondwords_wordpress_plugin||[]).push([[719],{5719:function(e,t,s){s.r(t);var i=s(614);s(1978),t.default=class{constructor(e){this._src=e||"",this._handlers={},this._attribute={},this.volume=0,this._currentTime=0,this._paused=!0,this._ended=!1,this._playbackRate=1,this._fakePlayingId=null}get src(){return this._src}set src(e){this._src=e||""}get playbackRate(){return this._playbackRate}set playbackRate(e){this._playbackRate=e||1}get currentTime(){return this._currentTime}set currentTime(e){this._currentTime=e}get played(){return!this._paused}get paused(){return this._paused}get ended(){return this._ended}_updateMedia({raw:e}){this._duration=e.duration||0}emit(e){const t=this._handlers[e];(0,i.n)(t&&t.length)||t.forEach((t=>{"function"==typeof t&&t({type:e})}))}play(){return new Promise((e=>(this._fakePlayingId&&(window.clearInterval(this._fakePlayingId),this._fakePlayingId=null),this._paused=!1,this._ended&&(this._ended=!1,this.currentTime=0),this.emit(i.b6.play),this.emit(i.b6.playing),this._fakePlayingId=window.setInterval((()=>{this.emit(i.b6.timeupdate),this.currentTime+=.1*this.playbackRate,this.currentTime>=this._duration&&(window.clearInterval(this._fakePlayingId),this._fakePlayingId=null,this._paused=!0,this.currentTime=this._duration,this._ended=!0,this.emit(i.b6.ended))}),100),e(!0))))}pause(){return this._fakePlayingId&&(window.clearInterval(this._fakePlayingId),this._fakePlayingId=null),this._paused=!0,this.emit(i.b6.pause),Promise.resolve(!0)}canPlayType(){return!0}addEventListener(e,t){return this._handlers[e]?this._handlers[e].push(t):this._handlers[e]=[t],this._handlers[e][this._handlers[e].length-1]}removeEventListener(e,t){const s=this._handlers[e];if(s&&s.length){const i=s.findIndex((e=>e===t));i>=0&&(s.splice(i,1),this._handlers[e]=s)}}setAttribute(e,t){this._attribute[e]=t}}}}]);