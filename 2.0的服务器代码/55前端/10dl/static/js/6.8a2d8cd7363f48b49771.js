webpackJsonp([6],{nU8l:function(t,a,s){"use strict";Object.defineProperty(a,"__esModule",{value:!0});var i=s("bOdI"),e=s.n(i),n=s("gyMJ"),l=s("mtWM"),v=s.n(l),c=s("mw3O"),o=s.n(c),r=s("TX6m"),p=s("35HW"),d={name:"index",data:function(){var t;return t={maskShow:!1,isChat:!1,isqqDiv:!1,skypDiv:!1,planeDiv:!1,qqValue:[],skypeValue:[],ffValue:[],isbgone:!1,isbgtwo:!1,isbgthree:!1,isbgfour:!1,modalnew:!1,isTongGao:"",crash:"",bulletin:"",newSkValue:"",qqxianshi:!1},e()(t,"skypeValue",[]),e()(t,"qqValue",[]),e()(t,"ffValue",[]),e()(t,"skypeNum",""),e()(t,"usedPass",""),e()(t,"newPass",""),e()(t,"confirmPass",""),e()(t,"modal1",!1),e()(t,"imgLogo",""),e()(t,"logo_name",""),e()(t,"aUrl",""),e()(t,"qqValue",[]),e()(t,"title",[{msg:"超级签名",isclass:!0},{msg:"私有池",isclass:!1},{msg:"专属签名",isclass:!1},{msg:"企业签名",isclass:!1},{msg:"发布应用",isclass:!1}]),e()(t,"isAdvantageOne",!0),e()(t,"isAdvantageTwo",!0),e()(t,"isAdvantageThree",!0),e()(t,"isAdvantageFourth",!0),e()(t,"money",""),e()(t,"userName",""),t},components:{Header:r.a,Footer:p.a},mounted:function(){var t=this;window.addEventListener("scroll",this.handleScroll,!0);var a={domain:n.b};v.a.post(n.a+"/api/index/init",o.a.stringify(a)).then(function(a){var s=t;if(1==a.data.data.is_kefu){if(t.isChat=!0,null!=a.data.data.skype){t.skypDiv=!0;for(var i=0;i<a.data.data.skype.length;i++){(e={}).newskype=a.data.data.skype[i],s.skypeValue.push(e)}}if(null!=a.data.data.telegram){t.planeDiv=!0;for(i=0;i<a.data.data.telegram.length;i++){(e={}).telegram=a.data.data.telegram[i],s.ffValue.push(e)}}if(null!=a.data.data.qq){t.isqqDiv=!0;for(i=0;i<a.data.data.qq.length;i++){var e;(e={}).newqq=a.data.data.qq[i],e.newurl="http://wpa.qq.com/msgrd?v=3&uin="+a.data.data.qq[i]+"&site=qq&menu=yes",s.qqValue.push(e)}}}else t.isChat=!1},function(t){console.log(t)})},methods:{handleScroll:function(t){},zhanOk:function(){this.modalnew=!1},play:function(){this.maskShow=!0},mask:function(){this.maskShow=!1},returnTop:function(){container.scrollIntoView()},goMyapp:function(){this.$router.push("/myApp")},rightnow:function(){this.$router.push({name:"myApp",params:{newid:1}})},loginBtn:function(){this.$router.push({path:"/login"})},registerBtn:function(){this.$router.push({path:"/register"})},enter:function(t){for(var a=0;a<this.title.length;a++)this.title[a].isclass=!1;this.title[t].isclass=!0},leave:function(){for(var t=0;t<this.title.length;t++)this.title[t].isclass=!1;this.title[0].isclass=!0},myappBtn:function(){this.$router.push({path:"/myApp"})},modify:function(){this.modal1=!0},ok:function(){var t=this,a={oldpwd:this.usedPass,pwd:this.newPass,repwd:this.confirmPass},s={headers:{token:localStorage.getItem("Authorization")}};v.a.post(n.a+"/api/user/changePwd",o.a.stringify(a),s).then(function(a){if(0==a.data.code)t.$message.error(a.data.msg);else{var s=localStorage.getItem("Authorization");t.$store.commit("del_token",s),t.$message({message:"修改成功",type:"success"}),t.modal1=!1,t.$router.push({path:"/login"})}},function(t){console.log(t)})},realName:function(){this.$router.push({path:"/realName"})},signOut:function(){var t=localStorage.getItem("Authorization");this.$store.commit("del_token",t),this.$router.push({path:"/"})}}},_={render:function(){var t=this,a=t.$createElement,s=t._self._c||a;return s("div",{staticClass:"login",attrs:{id:"container"}},[t.isChat?s("div",{staticClass:"chat"},[t.isqqDiv?s("div",{staticClass:"qqDiv"},[s("img",{attrs:{src:"//wpp.m2ld4.com/static/image/home/qq2.png",alt:""}}),t._v(" "),t._l(t.qqValue,function(a,i){return s("p",{key:i},[t._v("咨询QQ："),s("a",{attrs:{href:a.newurl,target:"_blank"}},[t._v(t._s(a.newqq))])])})],2):t._e(),t._v(" "),t.skypDiv?s("div",{staticClass:"skypDiv"},[s("img",{attrs:{src:"//wpp.m2ld4.com/static/image/home/skp.png",alt:""}}),t._v(" "),t._l(t.skypeValue,function(a,i){return s("div",{key:i+1,staticClass:"skypDivSmall"},[s("p",[t._v("SKP:")]),t._v(" "),s("p",[t._v(t._s(a.newskype))])])})],2):t._e(),t._v(" "),t.planeDiv?s("div",{staticClass:"planeDiv"},[s("img",{attrs:{src:"//wpp.m2ld4.com/static/image/home/feiji.png",alt:""}}),t._v(" "),t._l(t.ffValue,function(a,i){return s("div",{key:i+2,staticClass:"planeDivSmall"},[s("p",[t._v("飞机:")]),t._v(" "),s("p",[t._v(t._s(a.telegram))])])})],2):t._e(),t._v(" "),s("div",{staticClass:"chatFooter",on:{click:t.returnTop}},[s("img",{attrs:{src:"//wpp.m2ld4.com/static/image/home/shangla.png",alt:""}})])]):t._e(),t._v(" "),s("div",{staticClass:"loginBG"},[s("Header"),t._v(" "),s("div",{staticClass:"banner"},[s("div",{staticClass:"bannerDiv"},[s("main",{staticClass:"animate__animated animate__zoomIn"},[s("p",{staticClass:"bannerDivone"},[t._v("超级签名一次 安装永不受影响")]),t._v(" "),s("p",{staticClass:"bannerDivnew"},[t._v("iOS APP 超级签名")]),t._v(" "),s("p",{staticClass:"bannerDivtwo"},[t._v("国内ios应用商店，申请账号、上传应用费时费力？")]),t._v(" "),s("p",{staticClass:"bannerDivthree"},[t._v("不一样的ios签名。让你告别掉签烦恼，提高您的应用分发效率，节省大量时间，帮您轻松节省大量获客成本")]),t._v(" "),s("div",{staticStyle:{display:"flex",width:"20vw","justify-content":"space-between","min-width":"15vw"}},[s("div",{staticClass:"rightnow",on:{click:t.rightnow}},[t._v("立即签名")])])])])]),t._v(" "),t.maskShow?s("div",{staticClass:"mask",staticStyle:{display:"none"},on:{click:t.mask}},[t._m(0)]):t._e()],1),t._v(" "),s("div",{staticClass:"advantage"},[t._m(1),t._v(" "),s("div",{staticClass:"advantageBig"},[s("div",{staticClass:"advantageOne advantageBig_div"},[t.isAdvantageOne?s("div",{staticClass:"advantageOneBig"},[s("img",{staticClass:"hpadvantageOneBig",attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/hpzidonghua.png",alt:""}}),t._v(" "),s("p",{staticClass:"LaBel"},[t._v("自动化签名 方便快捷")]),t._v(" "),s("p",{staticClass:"LaBel2"},[t._v("根据自身需要，选择服务类型，上传IPA包，快速分发,10分钟内完成所有流程，全程自动化，操作简单")])]):t._e()]),t._v(" "),s("div",{staticClass:"advantageTwo advantageBig_div"},[t.isAdvantageTwo?s("div",{staticClass:"advantageTwoBig"},[s("img",{staticClass:"hpadvantageTwoBig",attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/hpjizhi.png",alt:""}}),t._v(" "),s("p",{staticClass:"LaBel"},[t._v("特有机制 告别掉签")]),t._v(" "),s("p",{staticClass:"LaBel2"},[t._v("我们采取的iOS超级签名和企业签名机制不同，掉签概率远低于其他传统企业签名")])]):t._e()]),t._v(" "),s("div",{staticClass:"advantageThree advantageBig_div"},[t.isAdvantageThree?s("div",{staticClass:"advantageTwoBig"},[s("img",{staticClass:"hpadvantageThreeBig",attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/hpanzhuang.png",alt:""}}),t._v(" "),s("p",{staticClass:"LaBel"},[t._v("无需越狱 安装即用")]),t._v(" "),s("p",{staticClass:"LaBel2"},[t._v("无需企业签名，无需越狱，无需苹果审核，无需上架App Store，下载后安装即用")])]):t._e()]),t._v(" "),s("div",{staticClass:"advantageFourth advantageBig_div"},[t.isAdvantageFourth?s("div",{staticClass:"advantageFourthBig"},[s("img",{staticClass:"hpadvantageFourthBig",attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/hpgoumai.png",alt:""}}),t._v(" "),s("p",{staticClass:"LaBel"},[t._v("按需购买 性价比高")]),t._v(" "),s("p",{staticClass:"LaBel2"},[t._v("按需购买，未使用设备不过期同设备多次下载，或下载多款应用下载，只收费一次")])]):t._e()])])]),t._v(" "),t._m(2),t._v(" "),t._m(3),t._v(" "),t._m(4),t._v(" "),t._m(5),t._v(" "),s("Footer")],1)},staticRenderFns:[function(){var t=this.$createElement,a=this._self._c||t;return a("video",{staticClass:"video-js vjs-default-skin vjs-big-play-centered",staticStyle:{"object-fit":"fill"},attrs:{controls:""}},[a("source",{attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/ios1.mp4",type:"video/mp4"}}),this._v("\n        您的浏览器不支持 video 标签。\n      ")])},function(){var t=this.$createElement,a=this._self._c||t;return a("div",{staticClass:"advantageImg"},[a("img",{attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/hpyoushizi.png",alt:""}})])},function(){var t=this,a=t.$createElement,s=t._self._c||a;return s("div",{staticClass:"lowprize"},[s("div",{staticClass:"lowprizeImg"},[s("img",{attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/jiagezi.png",alt:""}})]),t._v(" "),s("div",{staticClass:"lowprizeDiv"},[s("div",{staticClass:"lowprizeDiv_one"},[s("div",[s("p",[t._v("同样的iOS签名、超低的价格 ")]),t._v(" "),s("p",[t._v("1、因机制与企业签名不同，告别掉签")]),t._v(" "),s("p",[t._v("2、告别掉签风险，只需支付一次获客成本即可")]),t._v(" "),s("p",[t._v("3、同一台设备下载安装该应用不限制下载次数")])])])])])},function(){var t=this.$createElement,a=this._self._c||t;return a("div",{staticClass:"service"},[a("div",{staticClass:"serviceImg"},[a("img",{attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/fuwuzi.png",alt:""}})]),this._v(" "),a("div",{staticClass:"serviceDiv"},[a("img",{attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/hpfuwuduibi.png",alt:""}})])])},function(){var t=this.$createElement,a=this._self._c||t;return a("div",{staticClass:"operation"},[a("div",{staticClass:"operationDiv"},[a("img",{attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/liuchengzi.png",alt:""}})]),this._v(" "),a("div",{staticClass:"operationSmall"},[a("img",{attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/newhp.png",alt:""}})])])},function(){var t=this,a=t.$createElement,s=t._self._c||a;return s("div",{staticClass:"problem"},[s("div",{staticClass:"problemDiv"},[s("img",{attrs:{src:"//wpp.m2ld4.com/static/image/superSignature/wentizi.png",alt:""}})]),t._v(" "),s("div",{staticClass:"problemImg"},[s("div",{staticClass:"textMain"},[s("div",{staticClass:"textMainTop"},[s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textQ"},[t._v("Q")]),t._v(" "),s("p",{staticClass:"textOne"},[t._v("需要提供 App 的源码吗?")])]),t._v(" "),s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textA"},[t._v("A")]),t._v(" "),s("p",{staticClass:"textTwo"},[t._v("不需要提供，仅需要提供adhoc版本的ipa格式的安装包即可。")])])]),t._v(" "),s("div",{staticClass:"textMainTop"},[s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textQ"},[t._v("Q")]),t._v(" "),s("p",{staticClass:"textOne"},[t._v("对安装包的大小是否有限制？")])]),t._v(" "),s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textA"},[t._v("A")]),t._v(" "),s("p",{staticClass:"textTwo"},[t._v("应用包大小最高不超过2048M。")])])]),t._v(" "),s("div",{staticClass:"textMainTop"},[s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textQ"},[t._v("Q")]),t._v(" "),s("p",{staticClass:"textOne"},[t._v("付款方式有哪些？")])]),t._v(" "),s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textA"},[t._v("A")]),t._v(" "),s("p",{staticClass:"textTwo"},[t._v("支持支付宝、微信支付。")])])]),t._v(" "),s("div",{staticClass:"textMainTop"},[s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textQ"},[t._v("Q")]),t._v(" "),s("p",{staticClass:"textOne"},[t._v("签名需要多长时间？")])]),t._v(" "),s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textA"},[t._v("A")]),t._v(" "),s("p",{staticClass:"textTwo"},[t._v("上传IPA安装包后开始签名，一般来说5分钟内完成签名。")])])]),t._v(" "),s("div",{staticClass:"textMainTop"},[s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textQ"},[t._v("Q")]),t._v(" "),s("p",{staticClass:"textOne"},[t._v("充值后如何上传IPA安装包？")])]),t._v(" "),s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textA"},[t._v("A")]),t._v(" "),s("p",{staticClass:"textTwo"},[t._v("\n              进入后台系统“应用管理”页面，点击“新增应用”，按流程进行操作即可上传成功。若未登录，可点击页面中“登录”或banner中“立即签名”进入后台登录页面，\n              输入账号密码登录后再进行上传。\n            ")])])]),t._v(" "),s("div",{staticClass:"textMainTop"},[s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textQ"},[t._v("Q")]),t._v(" "),s("p",{staticClass:"textOne"},[t._v("签名的 App 可以在商店搜索到吗?")])]),t._v(" "),s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textA"},[t._v("A")]),t._v(" "),s("p",{staticClass:"textTwo"},[t._v("不能，超级签名后的 App 可以直接将链接发给用户安装，无需越狱，无需账号，无需审核。")])])]),t._v(" "),s("div",{staticClass:"textMainTop"},[s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textQ"},[t._v("Q")]),t._v(" "),s("p",{staticClass:"textOne"},[t._v("如果同一台设备安装应用后卸载，再重新安装，计费时算一台设备还是两台？")])]),t._v(" "),s("div",{staticClass:"textfooter"},[s("p",{staticClass:"textA"},[t._v("A")]),t._v(" "),s("p",{staticClass:"textTwo"},[t._v("按照真实设备数量计算，只计算一台设备。")])])])])])])}]};var g=s("VU/8")(d,_,!1,function(t){s("yjGB"),s("oIKL")},"data-v-8c08dafc",null);a.default=g.exports},oIKL:function(t,a){},yjGB:function(t,a){}});