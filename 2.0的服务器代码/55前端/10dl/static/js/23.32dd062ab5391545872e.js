webpackJsonp([23],{"5z5t":function(i,e,t){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var s=t("gyMJ"),a=t("mtWM"),m=t.n(a),r=t("mw3O"),p=t.n(r),n=t("TX6m"),c=t("35HW"),u={name:"myAppDetail",data:function(){return{title:[{msg:"应用概述",isclass:!1,img1:"//wpp.m2ld4.com/static/image/myApp/gaishu_s.png",img2:"//wpp.m2ld4.com/static/image/myApp/gaishu_n.png",isHide:!1},{msg:"版本记录",isclass:!1,img1:"//wpp.m2ld4.com/static/image/myApp/banben_s.png",img2:"//wpp.m2ld4.com/static/image/myApp/banben_n.png",isHide:!1},{msg:"消费记录",isclass:!1,img1:"//wpp.m2ld4.com/static/image/myApp/xiaofei_s.png",img2:"//wpp.m2ld4.com/static/image/myApp/xiaofei_n.png",isHide:!1},{msg:"异常预警",isclass:!1,img1:"//wpp.m2ld4.com/static/image/myApp/xiazai_s(1).png",img2:"//wpp.m2ld4.com/static/image/myApp/xiazai_n(1).png",isHide:!1},{msg:"应用合并",isclass:!1,img1:"//wpp.m2ld4.com/static/image/myApp/hebing_s.png",img2:"//wpp.m2ld4.com/static/image/myApp/hebing_n.png",isHide:!1},{msg:"下载码",isclass:!1,img1:"//wpp.m2ld4.com/static/image/myApp/xiazai_s(1).png",img2:"//wpp.m2ld4.com/static/image/myApp/xiazai_n(1).png",isHide:!1},{msg:"消息推送",isclass:!1,img1:"//wpp.m2ld4.com/static/image/myApp/banben_s.png",img2:"//wpp.m2ld4.com/static/image/myApp/banben_n.png",isHide:!0}],headers:{headers:{token:localStorage.getItem("token")}},crumbs:[{name:"应用概述"}]}},components:{Header:n.a,Footer:c.a},methods:{titleName:function(i){switch(this.title.forEach(function(i){i.isclass=!1}),this.title[i].isclass=!0,i){case 0:this.$router.push({path:"/myAppBrief",query:{id:this.$route.query.id}});break;case 1:this.$router.push({path:"/versionRecord",query:{id:this.$route.query.id}});break;case 2:this.$router.push({path:"/consumptionRecord",query:{id:this.$route.query.id}});break;case 3:this.$router.push({path:"/brush",query:{id:this.$route.query.id}});break;case 4:this.$router.push({path:"/appMerging",query:{id:this.$route.query.id}});break;case 5:this.$router.push({path:"/downloadCode",query:{id:this.$route.query.id}});break;case 6:this.$router.push({path:"/messagePush",query:{id:this.$route.query.id}})}}},mounted:function(){var i=this;"aa"==this.$route.query.index?(this.$router.push("/appMerging?id="+this.$route.query.id),this.title[4].isclass=!0):(this.$router.push("/myAppBrief?id="+this.$route.query.id),this.title[0].isclass=!0),console.log(this.$route.query.index);var e={id:this.$route.query.id};m.a.post(s.a+"/api/app/appDes",p.a.stringify(e),this.headers).then(function(e){0==e.data.data.push_type?i.title[6].isHide=!0:i.title[6].isHide=!1},function(e){i.$message.error("系统错误")})},watch:{$route:function(i,e){"myAppBrief"==i.name?this.crumbs[0].name="应用概述":"versionRecord"==i.name?this.crumbs[0].name="版本记录":"consumptionRecord"==i.name?this.crumbs[0].name="消费记录":"brush"==i.name?this.crumbs[0].name="异常预警":"appMerging"==i.name?this.crumbs[0].name="应用合并":"downloadCode"==i.name?this.crumbs[0].name="下载码":"messagePush"==i.name&&(this.crumbs[0].name="消息推送")}}},o={render:function(){var i=this,e=i.$createElement,t=i._self._c||e;return t("div",{attrs:{id:"myAppDetail"}},[t("Header"),i._v(" "),t("div",{staticClass:"myAppDetailMain"},[t("el-breadcrumb",{attrs:{separator:"/"}},[t("el-breadcrumb-item",{attrs:{to:{path:"/"}}},[i._v("超级签管理系统")]),i._v(" "),t("el-breadcrumb-item",{attrs:{to:{path:"/myApp"}}},[i._v("我的应用")]),i._v(" "),i._l(i.crumbs,function(e,s){return t("el-breadcrumb-item",{key:s},[i._v(i._s(e.name))])})],2),i._v(" "),t("div",{staticClass:"myAppDetailFirst"},[t("div",{staticClass:"myAppDetailFirstMain"},[t("div",{staticClass:"myAppDetailFirstMainOne"},i._l(i.title,function(e,s){return t("div",{key:s,staticClass:"myAppDetailFirstMainOneTitle",class:{ishide:e.isHide},on:{click:function(e){return i.titleName(s)}}},[e.isclass?t("img",{attrs:{src:e.img1,alt:""}}):t("img",{attrs:{src:e.img2,alt:""}}),i._v(" "),t("p",{class:{isColor:e.isclass}},[i._v(i._s(e.msg))])])}),0),i._v(" "),t("router-view")],1)])],1),i._v(" "),t("Footer")],1)},staticRenderFns:[]};var d=t("VU/8")(u,o,!1,function(i){t("ZfMz")},"data-v-2b95b4d4",null);e.default=d.exports},ZfMz:function(i,e){}});