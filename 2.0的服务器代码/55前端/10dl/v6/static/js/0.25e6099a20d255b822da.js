webpackJsonp([0],{FZAk:function(e,t){},W50x:function(e,t){},Wd2j:function(e,t,a){"use strict";Object.defineProperty(t,"__esModule",{value:!0});var s=a("gyMJ"),i=a("mtWM"),r=a.n(i),o=a("mw3O"),l=a.n(o),n={name:"enterpriseAuthentication",data:function(){return{action:""+s.d,active:1,msg:"请使用与账号信息中的手机号码一致的身份证信息提交认证。",text:"应监管部门要求，网上发布APP必须进行实名登记， 我们采用了高于行业标准的要求来保障您的信息安全，为了进一步的保护您的个人信息，建议您在上传的实名信息中添加水印文字-仅供实名认证使用。",form:{cname:"",license:"",businesslicense:"",email:"",captcha:"",identityhold:""},rules:{cname:[{required:!0,message:"请输入企业名称",trigger:"blur"}],license:[{required:!0,message:"请输入企业营业执照号码",trigger:"blur"}],email:[{required:!0,message:"请填写邮箱地址",trigger:"blur"}],captcha:[{required:!0,message:"请填写邮箱验证码",trigger:"blur"}]},businesslicense:"",identityhold:"",phone:"",emailCapCode:"获取邮箱验证码",timer:null,offet:!0,scopeBoolean:!1}},mounted:function(){this.phone=localStorage.getItem("")},methods:{lastStep:function(){this.$router.push("/realName")},refer:function(e){var t=this;this.$refs[e].validate(function(e){if(!e)return!1;var a,i=""==t.businesslicense?"请上传营业执照照片":""==t.identityhold&&"请上传联系人手持身份证照片";if(i.length>0)return t.$message({message:i,type:"warning",duration:1500}),!1;a={cname:t.form.cname,license:t.form.license,businesslicense:t.form.businesslicense,mobile:t.phone,email:t.form.email,identityhold:t.form.identityhold,captcha:t.form.captcha,type:2};var o={headers:{token:localStorage.getItem("Authorization")}};r.a.post(s.a+"/api/user/authentication",l.a.stringify(a),o).then(function(e){0!==e.data.code?t.$message({message:"提交成功",type:"success",duration:1500}):t.$message({message:e.data.msg,type:"warning",duration:1500})})})},updateRouter:function(){this.$router.push("/accountSettings")},timesOut:function(){var e=this,t=60;this.timer=setTimeout(function a(){clearTimeout(e.timer),!0===e.offet?t>1?(t--,setTimeout(a,1e3),e.emailCapCode=t,e.scopeBoolean=!0):1===t&&(clearTimeout(e.timer),e.scopeBoolean=!1,e.emailCapCode="重新获取验证码"):(clearTimeout(e.timer),e.scopeBoolean=!1)},0)},emailCaptcha:function(){if(""==this.form.email)return this.$message({message:"请输入邮箱地址",type:"warning",duration:1500}),!1;this.timesOut()},handleAvatarSuccess:function(e,t){this.form.businesslicense=t.response.data.domain+t.response.data.url,this.businesslicense=URL.createObjectURL(t.raw)},beforeAvatarUpload:function(e){var t=e.size/1024/1024<2;return t||this.$message.error("营业执照照片大小不能超过 2MB!"),t},handleContactSuccess:function(e,t){this.form.identityhold=t.response.data.domain+t.response.data.url,this.identityhold=URL.createObjectURL(t.raw)},beforeContactUpload:function(e){var t=e.size/1024/1024<2;return t||this.$message.error("联系人手持身份证照片大小不能超过 2MB!"),t}}},c={render:function(){var e=this,t=e.$createElement,a=e._self._c||t;return a("div",{staticClass:"enterpriseCertification"},[a("div",{staticClass:"promptMessage"},[a("p",[a("i",{staticClass:"el-icon-warning"}),e._v(e._s(e.msg))])]),e._v(" "),a("el-form",{ref:"form",staticClass:"demo-form form-message",attrs:{rules:e.rules,"label-width":"134px",model:e.form}},[a("el-form-item",{attrs:{label:"企业名称",prop:"cname"}},[a("el-input",{attrs:{clearable:"",placeholder:"请输入企业名称"},model:{value:e.form.cname,callback:function(t){e.$set(e.form,"cname",t)},expression:"form.cname"}})],1),e._v(" "),a("el-form-item",{attrs:{label:"企业营业执照号码",prop:"license"}},[a("el-input",{attrs:{clearable:"",placeholder:"请输入企业营业执照号码"},model:{value:e.form.license,callback:function(t){e.$set(e.form,"license",t)},expression:"form.license"}})],1),e._v(" "),a("el-form-item",{attrs:{label:"上传营业执照照片"}},[a("div",{staticClass:"promptMessage-uploader"},[a("div",{staticClass:"promptMessage-avatar-uploader"},[a("el-upload",{staticClass:"avatar-uploader",attrs:{action:e.action,accept:".jpg,.png","show-file-list":!1,"on-success":e.handleAvatarSuccess,"before-upload":e.beforeAvatarUpload}},[e.businesslicense?a("img",{staticClass:"avatar",attrs:{src:e.businesslicense}}):a("div",{staticClass:"avatar-uploader-icon"},[a("i",{staticClass:"el-icon-circle-plus"}),e._v(" "),a("p",{staticStyle:{"font-size":"16px","margin-bottom":"20px"}},[e._v("上传营业执照照片")])])])],1),e._v(" "),a("div",{staticClass:"promptMessage-Exemplary"},[a("img",{attrs:{src:"//wbpp.h83k.net/v6/static/image/card/s_zhizhao.png",alt:"示例"}})])])]),e._v(" "),a("el-form-item",{attrs:{label:"手机号码",prop:"mobile"}},[a("div",{staticClass:"phone-router"},[a("div",{staticClass:"phone-color"},[e._v(e._s(e.phone))])])]),e._v(" "),a("el-form-item",{attrs:{label:"邮箱地址",prop:"email"}},[a("el-input",{attrs:{clearable:"",placeholder:"请输入邮箱地址"},model:{value:e.form.email,callback:function(t){e.$set(e.form,"email",t)},expression:"form.email"}})],1),e._v(" "),a("el-form-item",{attrs:{label:"",prop:"captcha"}},[a("div",{staticClass:"email-code"},[a("el-input",{attrs:{clearable:"",placeholder:"请填写邮箱验证码"},model:{value:e.form.captcha,callback:function(t){e.$set(e.form,"captcha",t)},expression:"form.captcha"}}),e._v(" "),a("el-button",{staticClass:"col-email-code-button",attrs:{plain:"",disabled:e.scopeBoolean},on:{click:e.emailCaptcha}},[e._v("\n          "+e._s(e.emailCapCode)+"\n        ")])],1)]),e._v(" "),a("el-form-item",{attrs:{label:"身份证图片"}},[a("div",{staticClass:"card-img"},[a("div",{staticClass:"card-text"},[e._v(e._s(e.text))]),e._v(" "),a("div",{staticClass:"contact-hold"},[a("el-upload",{staticClass:"avatar-uploader",attrs:{action:e.action,accept:".jpg,.png","show-file-list":!1,"on-success":e.handleContactSuccess,"before-upload":e.beforeContactUpload}},[e.identityhold?a("img",{staticClass:"contact-avatar",attrs:{src:e.identityhold}}):a("div",{staticClass:"avatar-uploader-icon"},[a("i",{staticClass:"el-icon-circle-plus"})])]),e._v(" "),a("p",{staticStyle:{"font-size":"16px","margin-bottom":"20px"}},[e._v("联系人手持身份证照片")])],1)])]),e._v(" "),a("div",{staticClass:"enterpriseCertification-reminder"},[a("p",[e._v("提示：")]),e._v(" "),a("p",[e._v("1.请上传与手机号码身份信息一致的身份证照片，用于认证")]),e._v(" "),a("p",[e._v("2.单张照片大小不超过2M，支持PNG、JPG格式；")]),e._v(" "),a("p",[e._v("3.所上传的图片，保证文字和图片、人像清晰可见。")])]),e._v(" "),a("div",{staticClass:"enterpriseCertification-el-button"},[a("el-button",{staticClass:"last-step",on:{click:e.lastStep}},[e._v("上一步")]),e._v(" "),a("el-button",{staticClass:"enterprise-refer",on:{click:function(t){return e.refer("form")}}},[e._v("提交")])],1)],1)],1)},staticRenderFns:[]};var m=a("VU/8")(n,c,!1,function(e){a("FZAk"),a("W50x")},"data-v-e9363212",null);t.default=m.exports}});