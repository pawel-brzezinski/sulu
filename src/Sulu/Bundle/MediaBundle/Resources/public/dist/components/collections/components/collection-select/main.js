define(function(){"use strict";var a={instanceName:"collection-select"},b={toggler:['<div id="show-ghost-pages"></div>','<label class="inline spacing-left" for="show-ghost-pages"><%= label %></label>'].join(""),columnNavigation:function(){return['<div id="child-column-navigation"/>','<div id="wait-container" style="margin-top: 50px; margin-bottom: 200px; display: none;"></div>'].join("")}},c="sulu.media.collection-select.",d=function(){return g.call(this,"open")},e=function(){return g.call(this,"close")},f=function(){return g.call(this,"selected")},g=function(a){return c+this.options.instanceName+a};return{initialize:function(){this.options=this.sandbox.util.extend(!0,{},a,this.options),this.bindCustomEvents(),this.render()},bindCustomEvents:function(){this.sandbox.on(d.call(this),function(){this.sandbox.emit("husky.overlay."+this.options.instanceName+".open")}.bind(this)),this.sandbox.on(e.call(this),function(){this.sandbox.emit("husky.overlay."+this.options.instanceName+".close")}.bind(this)),this.sandbox.once("husky.overlay."+this.options.instanceName+".initialized",function(){this.startOverlayColumnNavigation()}.bind(this)),this.sandbox.on("husky.column-navigation."+this.options.instanceName+".edit",function(a){this.sandbox.emit(f.call(this),a)}.bind(this)),this.sandbox.once("husky.column-navigation."+this.options.instanceName+".initialized",function(){this.sandbox.emit("husky.overlay."+this.options.instanceName+".set-position")}.bind(this))},render:function(){this.renderOverlay()},renderOverlay:function(){var a=this.sandbox.dom.createElement('<div class="overlay-container"/>'),c=[{type:"cancel",align:"center"}];this.sandbox.dom.append(this.$el,a),this.sandbox.start([{name:"overlay@husky",options:{cssClass:"collection-select",el:a,removeOnClose:!1,container:this.$el,instanceName:this.options.instanceName,skin:"wide",slides:[{title:this.sandbox.translate("sulu.media.move.overlay-title"),data:b.columnNavigation(),buttons:c}]}}])},startOverlayColumnNavigation:function(){this.sandbox.start([{name:"column-navigation@husky",options:{el:"#child-column-navigation",url:"/admin/api/collections",instanceName:this.options.instanceName,editIcon:"fa-check-circle",resultKey:"collections",showEdit:!1,showStatus:!1,responsive:!1,skin:"fixed-height-small"}}])}}});