webpackJsonp([13],{"6f6d":function(t,i){},"971J":function(t,i){},ZdtO:function(t,i,a){"use strict";Object.defineProperty(i,"__esModule",{value:!0});var n=a("gyMJ"),s=a("mtWM"),e=a.n(s),c=a("mw3O"),r=a.n(c),l={name:"brush",data:function(){return{headers:{headers:{token:localStorage.getItem("token")}},phoneNum:"",downNum:"",warningSwitch:!1,warningSwitch1:!1,warningSwitch2:!1,warningSwitch3:!1,input:"",input2:"",input3:"",input4:"",input5:"",input6:"",oneBack:!1,twoBack:!1,threeBack:!1,four1Back:!1,fourBack:!1,FiveBack:!1,disabledInput:!0,disabledInput1:!0,disabledInput2:!0,disabledInput3:!0,is_vaptcha:"",disabled:!0}},methods:{warningSwitchChange:function(){1==this.warningSwitch?(this.oneBack=!0,this.FiveBack=!0,this.disabledInput=!1):(this.oneBack=!1,this.disabledInput=!0),0==this.warningSwitch&&0==this.warningSwitch1&&0==this.warningSwitch2&&0==this.warningSwitch3&&(this.FiveBack=!1)},warningSwitchChange1:function(){1==this.warningSwitch1?(this.twoBack=!0,this.FiveBack=!0,this.disabledInput1=!1):(this.twoBack=!1,this.disabledInput1=!0),0==this.warningSwitch&&0==this.warningSwitch1&&0==this.warningSwitch2&&0==this.warningSwitch3&&(this.FiveBack=!1)},warningSwitchChange2:function(){1==this.warningSwitch2?(this.threeBack=!0,this.FiveBack=!0,this.disabledInput2=!1):(this.threeBack=!1,this.disabledInput2=!0),0==this.warningSwitch&&0==this.warningSwitch1&&0==this.warningSwitch2&&0==this.warningSwitch3&&(this.FiveBack=!1)},warningSwitchChange3:function(){1==this.warningSwitch3?(this.four1Back=!0,this.FiveBack=!0,this.disabledInput3=!1):(this.four1Back=!1,this.disabledInput3=!0),0==this.warningSwitch&&0==this.warningSwitch1&&0==this.warningSwitch2&&0==this.warningSwitch3&&(this.FiveBack=!1)},preservation:function(){var t=this,i={id:this.$route.query.id,mobile:this.phoneNum,is_vaptcha:this.is_vaptcha,download_code:this.downNum,down_frequency:this.input,down_times:this.input2,auto_close:this.input3,auto_times:this.input4,day_consume:0,day_times:this.input5,download_limit:this.input6};e.a.post(n.a+"/api/app/appEarlyWarning",i,this.headers).then(function(i){200==i.data.code?t.$message.success(i.data.msg):t.$message.error(i.data.msg)},function(t){})}},mounted:function(){var t=this,i={id:this.$route.query.id};e.a.post(n.a+"/api/app/appEarlyWarningInfo",r.a.stringify(i),this.headers).then(function(i){200==i.data.code&&(t.phoneNum=i.data.data.mobile,t.downNum=i.data.data.download_code,t.input=i.data.data.down_frequency,t.input2=i.data.data.down_times,t.input3=i.data.data.auto_close,t.input4=i.data.data.auto_times,t.input5=i.data.data.day_times,t.input6=i.data.data.download_limit,0!=t.input&&0!=t.input2?(t.warningSwitch=!0,t.oneBack=!0,t.disabledInput=!1):(t.warningSwitch=!1,t.oneBack=!1,t.disabledInput=!0),0!=t.input3&&0!=t.input4?(t.warningSwitch1=!0,t.twoBack=!0,t.disabledInput1=!1):(t.warningSwitch1=!1,t.twoBack=!1,t.disabledInput1=!0),0!=t.input5?(t.warningSwitch2=!0,t.threeBack=!0,t.disabledInput2=!1):(t.warningSwitch2=!1,t.threeBack=!1,t.disabledInput2=!0),""!=t.input6?(t.warningSwitch3=!0,t.four1Back=!0,t.disabledInput3=!1):(t.warningSwitch3=!1,t.four1Back=!1,t.disabledInput3=!0),1==i.data.data.is_vaptcha?(t.theftSwitch=!0,t.FiveBack=!0):(t.theftSwitch=!1,t.FiveBack=!1))},function(t){})}},o={render:function(){var t=this,i=t.$createElement,a=t._self._c||i;return a("div",{attrs:{id:"brush"}},[a("div",{staticClass:"anomaly"},[a("div",{staticClass:"anomalyDivThird"},[a("div",{staticClass:"anomalyDivThirdFirst",class:{isBackColor:t.oneBack}},[a("div",{staticClass:"anomalyDivThirdFirstOne"},[a("p",[t._v("下载超量预警")]),t._v(" "),a("div",[a("el-switch",{attrs:{"active-color":"#157df1","inactive-color":"#DCDCDC"},on:{change:t.warningSwitchChange},model:{value:t.warningSwitch,callback:function(i){t.warningSwitch=i},expression:"warningSwitch"}})],1)]),t._v(" "),t._m(0),t._v(" "),a("div",{staticClass:"anomalyDivThirdFirstThree"},[a("div",{staticClass:"firstDivOne"},[t._m(1),t._v(" "),a("el-input",{staticClass:"firstInput",attrs:{disabled:t.disabledInput},model:{value:t.input,callback:function(i){t.input=i},expression:"input"}})],1),t._v(" "),a("div",{staticClass:"firstDivTwo"},[t._m(2),t._v(" "),a("el-input",{staticClass:"secondInput",attrs:{disabled:t.disabledInput},model:{value:t.input2,callback:function(i){t.input2=i},expression:"input2"}})],1),t._v(" "),a("div",{staticClass:"newbtn",class:{isBtnColor:t.oneBack},on:{click:function(i){t.oneBack&&t.preservation()}}},[a("p",[t._v("保存")])])])]),t._v(" "),a("div",{staticClass:"anomalyDivThirdSecond",class:{isBackColor:t.twoBack}},[a("div",{staticClass:"anomalyDivThirdFirstOne"},[a("p",[t._v("下载超量自动下架")]),t._v(" "),a("div",[a("el-switch",{attrs:{"active-color":"#157df1","inactive-color":"#DCDCDC"},on:{change:t.warningSwitchChange1},model:{value:t.warningSwitch1,callback:function(i){t.warningSwitch1=i},expression:"warningSwitch1"}})],1)]),t._v(" "),t._m(3),t._v(" "),a("div",{staticClass:"anomalyDivThirdFirstThree"},[a("div",{staticClass:"firstDivOne"},[t._m(4),t._v(" "),a("el-input",{staticClass:"firstInput",attrs:{disabled:t.disabledInput1},model:{value:t.input3,callback:function(i){t.input3=i},expression:"input3"}})],1),t._v(" "),a("div",{staticClass:"firstDivTwo"},[t._m(5),t._v(" "),a("el-input",{staticClass:"secondInput",attrs:{disabled:t.disabledInput1},model:{value:t.input4,callback:function(i){t.input4=i},expression:"input4"}})],1),t._v(" "),a("div",{staticClass:"newbtn",class:{isBtnColor:t.twoBack},on:{click:function(i){t.twoBack&&t.preservation()}}},[a("p",[t._v("保存")])])])])]),t._v(" "),a("div",{staticClass:"anomalyDivThird footered"},[a("div",{staticClass:"anomalyDivThirdFirst",class:{isBackColor:t.threeBack}},[a("div",{staticClass:"anomalyDivThirdFirstOne"},[a("p",[t._v("每日消费限制")]),t._v(" "),a("div",[a("el-switch",{attrs:{"active-color":"#157df1","inactive-color":"#DCDCDC"},on:{change:t.warningSwitchChange2},model:{value:t.warningSwitch2,callback:function(i){t.warningSwitch2=i},expression:"warningSwitch2"}})],1)]),t._v(" "),t._m(6),t._v(" "),a("div",{staticClass:"anomalyDivThirdFirstThree"},[a("div",{staticClass:"firstDivTwo",staticStyle:{"margin-top":"60px"}},[t._m(7),t._v(" "),a("el-input",{staticClass:"secondInput",attrs:{disabled:t.disabledInput2},model:{value:t.input5,callback:function(i){t.input5=i},expression:"input5"}})],1),t._v(" "),a("div",{staticClass:"newbtn",class:{isBtnColor:t.threeBack},on:{click:function(i){t.threeBack&&t.preservation()}}},[a("p",[t._v("保存")])])])]),t._v(" "),a("div",{staticClass:"anomalyDivThirdSecond",class:{isBackColor:t.four1Back}},[a("div",{staticClass:"anomalyDivThirdFirstOne"},[a("p",[t._v("下载超量自动下架")]),t._v(" "),a("div",[a("el-switch",{attrs:{"active-color":"#157df1","inactive-color":"#DCDCDC"},on:{change:t.warningSwitchChange3},model:{value:t.warningSwitch3,callback:function(i){t.warningSwitch3=i},expression:"warningSwitch3"}})],1)]),t._v(" "),t._m(8),t._v(" "),a("div",{staticClass:"anomalyDivThirdFirstThree"},[a("div",{staticClass:"firstDivTwo",staticStyle:{"margin-top":"60px"}},[t._m(9),t._v(" "),a("el-input",{staticClass:"secondInput",attrs:{disabled:t.disabledInput3},model:{value:t.input6,callback:function(i){t.input6=i},expression:"input6"}})],1),t._v(" "),a("div",{staticClass:"newbtn",class:{isBtnColor:t.four1Back},on:{click:function(i){t.four1Back&&t.preservation()}}},[a("p",[t._v("保存")])])])])])])])},staticRenderFns:[function(){var t=this.$createElement,i=this._self._c||t;return i("div",{staticClass:"anomalyDivThirdFirstTwo"},[i("p",[this._v("\n            按照您设置的规则，新用户下载达到您设置的下载数时，将会给您发送一条\n            预警短信。\n          ")])])},function(){var t=this.$createElement,i=this._self._c||t;return i("p",{staticClass:"fff"},[this._v("检测频率/分钟"),i("span",{staticStyle:{color:"red"}},[this._v("*")])])},function(){var t=this.$createElement,i=this._self._c||t;return i("p",{staticClass:"fff"},[this._v("下载次数"),i("span",{staticStyle:{color:"red"}},[this._v("*")])])},function(){var t=this.$createElement,i=this._self._c||t;return i("div",{staticClass:"anomalyDivThirdFirstTwo"},[i("p",[this._v("\n            按照您设置的规则，新用户下载达到您设置的下载数时，该应用将自动下架，\n            您可重新下架，您可重新手动上架。\n          ")])])},function(){var t=this.$createElement,i=this._self._c||t;return i("p",{staticClass:"fff"},[this._v("检测频率/分钟"),i("span",{staticStyle:{color:"red"}},[this._v("*")])])},function(){var t=this.$createElement,i=this._self._c||t;return i("p",{staticClass:"fff"},[this._v("下载次数"),i("span",{staticStyle:{color:"red"}},[this._v("*")])])},function(){var t=this.$createElement,i=this._self._c||t;return i("div",{staticClass:"anomalyDivThirdFirstTwo"},[i("p",[this._v("\n            每日该应用的消费达到您的预设值时，该应用将自动下架，您可修改消费上\n            限扣再次手动上架，或等待第二日自动重新上架。\n          ")])])},function(){var t=this.$createElement,i=this._self._c||t;return i("p",{staticClass:"fff"},[this._v("下载次数"),i("span",{staticStyle:{color:"red"}},[this._v("*")])])},function(){var t=this.$createElement,i=this._self._c||t;return i("div",{staticClass:"anomalyDivThirdFirstTwo"},[i("p",[this._v("\n            总下载该应用的消费达到您的预设值时，该应用将自动下架，您可修改上限\n            手机上架。\n          ")])])},function(){var t=this.$createElement,i=this._self._c||t;return i("p",{staticClass:"fff"},[this._v("下载次数"),i("span",{staticStyle:{color:"red"}},[this._v("*")])])}]};var h=a("VU/8")(l,o,!1,function(t){a("6f6d"),a("971J")},"data-v-334b695e",null);i.default=h.exports}});