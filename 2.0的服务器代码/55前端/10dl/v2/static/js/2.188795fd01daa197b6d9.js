webpackJsonp([2],{"22IC":function(t,e,a){"use strict";Object.defineProperty(e,"__esModule",{value:!0});var i=a("gyMJ"),n=a("mtWM"),s=a.n(n),o=a("mw3O"),r=a.n(o),d={name:"personAuthentication",data:function(){return{action:""+i.c,active:1,text:"应监管部门要求，网上发布APP必须进行实名登记， 我们采用了高于行业标准的要求来保障您的信息安全，为了进一步的保护您的个人信息，建议您在上传的实名信息中添加水印文字-仅供实名认证使用。",form:{identityfront:"",identityback:"",identityhold:""},scopeBoolean:!1,offet:!0,rules:{},emailCapCode:"获取邮箱验证码",identityfront:"",identityback:"",identityhold:"",timer:null}},mounted:function(){},methods:{newuploadfrontimg:function(t){var e=this,a=new FormData,n={headers:{token:localStorage.getItem("Authorization")}};s.a.get(i.a+"/api/common/ossToken",n).then(function(i){a.append("policy",i.data.data.policy),a.append("success_action_status",200),a.append("signature",i.data.data.signature),a.append("OSSAccessKeyId",i.data.data.accessid),a.append("name",t.file.name),a.append("key",i.data.data.dir+t.file.name),a.append("file",t.file),e.identityfront=URL.createObjectURL(t.file),e.form.identityfront=i.data.data.dir+t.file.name,s.a.post(i.data.data.host,a,n).then(function(t){},function(t){e.$message.error("上传图片失败")})},function(t){})},newuploadbackimg:function(t){var e=this,a=new FormData,n={headers:{token:localStorage.getItem("Authorization")}};s.a.get(i.a+"/api/common/ossToken",n).then(function(i){a.append("policy",i.data.data.policy),a.append("success_action_status",200),a.append("signature",i.data.data.signature),a.append("OSSAccessKeyId",i.data.data.accessid),a.append("name",t.file.name),a.append("key",i.data.data.dir+t.file.name),a.append("file",t.file),e.identityback=URL.createObjectURL(t.file),e.form.identityback=i.data.data.dir+t.file.name,s.a.post(i.data.data.host,a,n).then(function(t){},function(t){e.$message.error("上传图片失败")})},function(t){})},newuploadholdimg:function(t){var e=this,a=new FormData,n={headers:{token:localStorage.getItem("Authorization")}};s.a.get(i.a+"/api/common/ossToken",n).then(function(i){a.append("policy",i.data.data.policy),a.append("success_action_status",200),a.append("signature",i.data.data.signature),a.append("OSSAccessKeyId",i.data.data.accessid),a.append("name",t.file.name),a.append("key",i.data.data.dir+t.file.name),a.append("file",t.file),e.identityhold=URL.createObjectURL(t.file),e.form.identityhold=i.data.data.dir+t.file.name,s.a.post(i.data.data.host,a,n).then(function(t){},function(t){e.$message.error("上传图片失败")})},function(t){})},timesOut:function(){var t=this,e=60;this.timer=setTimeout(function a(){clearTimeout(t.timer),!0===t.offet?e>1?(e--,setTimeout(a,1e3),t.emailCapCode=e,t.scopeBoolean=!0):1===e&&(clearTimeout(t.timer),t.scopeBoolean=!1,t.emailCapCode="重新获取验证码"):(clearTimeout(t.timer),t.scopeBoolean=!1)},0)},emailCaptcha:function(){if(""==this.form.email)return this.$message({message:"请输入邮箱地址",type:"warning",duration:1500}),!1;this.timesOut()},lastStep:function(){this.$router.push("/realName")},refer:function(t){var e=this;this.$refs[t].validate(function(t){if(!t)return!1;var a,n=e.$loading({lock:!0,text:"拼命认证中",spinner:"el-icon-loading",background:"rgba(0, 0, 0, 0.7)"}),o=(""==e.form.identityfront?"请上传身份证正面照片":""==e.form.identityback&&"请上传身份证反面照片")||""==e.form.identityhold&&"请上传手持身份证照片";if(o.length>0)return e.$message({message:o,type:"warning",duration:1500}),n.close(),!1;a={identityfront:e.form.identityfront,identityback:e.form.identityback,identityhold:e.form.identityhold,type:1};var d={headers:{token:localStorage.getItem("Authorization")}};s.a.post(i.a+"/api/user/authentication",r.a.stringify(a),d).then(function(t){0===t.data.code?(e.$message({message:t.data.msg,type:"warning",duration:1500}),n.close()):(e.$message({message:"提交成功",type:"success",duration:1500}),n.close(),e.$router.push("/examine"))},function(t){console.log(t),e.$message({message:"上传失败",type:"warning",duration:1500}),n.close()})})},updateRouter:function(){this.$router.push("/accountSettings")},handleIdentityholdSuccess:function(t,e){this.form.identityfront=e.response.data.url,this.identityfront=URL.createObjectURL(e.raw)},beforeIdentityholdUpload:function(t){var e=t.size/1024/1024<5;return e||this.$message.error("上传头像图片大小不能超过 5MB!"),e},handleIdentitybackSuccess:function(t,e){this.form.identityback=e.response.data.url,this.identityback=URL.createObjectURL(e.raw)},beforeIdentitybackUpload:function(t){var e=t.size/1024/1024<5;return e||this.$message.error("上传头像图片大小不能超过 5MB!"),e},handleidentityholdSuccess:function(t,e){this.form.identityhold=e.response.data.url,this.identityhold=URL.createObjectURL(e.raw)},beforeidentityholdUpload:function(t){var e=t.size/1024/1024<5;return e||this.$message.error("上传头像图片大小不能超过 5MB!"),e}}},c={render:function(){var t=this,e=t.$createElement,a=t._self._c||e;return a("div",{staticClass:"personalCertificate"},[a("div",{staticClass:"firstDiv"},[a("div",{staticClass:"firstDiv_small"},[a("img",{attrs:{src:"//wbpp.h83k.net/v2/static/image/survey/shouye@2x.png",alt:""}}),t._v(" "),a("p",[t._v("您当前位置：")]),t._v(" "),a("el-breadcrumb",{attrs:{separator:"/"}},[a("el-breadcrumb-item",{attrs:{to:{path:"/superSignatureAread"}}},[t._v("首页")]),t._v(" "),a("el-breadcrumb-item",{attrs:{to:{path:"/survey"}}},[t._v("实名认证")])],1)],1)]),t._v(" "),a("div",{staticClass:"promptMessage"}),t._v(" "),a("el-form",{ref:"form",staticClass:"demo-form form-message",attrs:{rules:t.rules,"label-width":"134px",model:t.form}},[a("el-form-item",{attrs:{label:"身份证图片"}},[a("div",{staticClass:"card-img"},[a("div",{staticClass:"card-text"},[t._v(t._s(t.text))])])]),t._v(" "),a("div",{staticClass:"personalCertificate-contact"},[a("div",{staticClass:"contact-hold"},[a("el-upload",{staticClass:"avatar-uploader",attrs:{accept:".jpg,.png",action:"string","http-request":t.newuploadfrontimg,"show-file-list":!1,"before-upload":t.beforeIdentityholdUpload}},[t.identityfront?a("img",{staticClass:"contact-avatar",attrs:{src:t.identityfront}}):a("i",{staticClass:"el-icon-plus avatar-uploader-icon"})]),t._v(" "),a("p",[t._v("上传身份证正面照片")])],1),t._v(" "),a("div",{staticClass:"contact-hold"},[a("el-upload",{staticClass:"avatar-uploader",attrs:{accept:".jpg,.png",action:"string","http-request":t.newuploadbackimg,"show-file-list":!1,"before-upload":t.beforeIdentitybackUpload}},[t.identityback?a("img",{staticClass:"contact-avatar",attrs:{src:t.identityback}}):a("i",{staticClass:"el-icon-plus avatar-uploader-icon"})]),t._v(" "),a("p",[t._v("上传身份证反面照片")])],1),t._v(" "),a("div",{staticClass:"contact-hold"},[a("el-upload",{staticClass:"avatar-uploader",attrs:{accept:".jpg,.png",action:"string","http-request":t.newuploadholdimg,"show-file-list":!1,"before-upload":t.beforeidentityholdUpload}},[t.identityhold?a("img",{staticClass:"contact-avatar",attrs:{src:t.identityhold}}):a("i",{staticClass:"el-icon-plus avatar-uploader-icon"})]),t._v(" "),a("p",[t._v("上传手持身份证照片")])],1)]),t._v(" "),a("div",{staticClass:"enterpriseCertification-reminder"},[a("p",[t._v("提示：")]),t._v(" "),a("p",[t._v("1.请上传与手机号码身份信息一致的身份证照片，用于认证")]),t._v(" "),a("p",[t._v("2.单张照片大小不超过5M，支持PNG、JPG格式；")]),t._v(" "),a("p",[t._v("3.所上传的图片，保证文字和图片、人像清晰可见。")])]),t._v(" "),a("div",{staticClass:"enterpriseCertification-el-button"},[a("el-button",{staticClass:"last-step",on:{click:t.lastStep}},[t._v("上一步")]),t._v(" "),a("el-button",{staticClass:"enterprise-refer",on:{click:function(e){return t.refer("form")}}},[t._v("提交")])],1)],1)],1)},staticRenderFns:[]};var l=a("VU/8")(d,c,!1,function(t){a("LdEd"),a("aUaR")},"data-v-30f5cc16",null);e.default=l.exports},LdEd:function(t,e){},aUaR:function(t,e){}});