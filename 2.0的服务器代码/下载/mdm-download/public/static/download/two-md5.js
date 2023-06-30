var  is_ios=true;
var appenddata=null;
var reload_task=null;
var reload_time=0;
var sign_times=20;
var auth_times=30;
var is_v1=0;
var is_resign,resign_txt;
var is_force_install_app=null;
var net_error=null;
var ua = navigator.userAgent.toLowerCase();
var mobileconfig_path="";
var en_mobileconfig_path="";
$(function () {
    udid="" ;
    var cache_udid= GetUrlParam("udid");
    if(cache_udid.length>5){
        udid = cache_udid;
        localStorage.setItem("udid",udid);
        $.cookie("udid",udid, { expires: 365 });
    }
    get_lang_data();
    appenddata = GetUrlParam("appenddata");
    setTimeout(views,3000);
    imgCss();
    if (!/Android|webOS|iPhone|iPod|iPad|BlackBerry/i.test(navigator.userAgent) && !(navigator.userAgent.match(/Mac/) && navigator.maxTouchPoints && navigator.maxTouchPoints > 2)) {
        //PC
        $('.pcDiv').show();
        $('#mobile').remove();
        var pcAppIcon = $("#pcAppIcon").attr("src");
        $('#qrcode').qrcode({
            render: "canvas",
            // text: window.location.origin + window.location.pathname,
            text: window.location.href,
            width: "200",
            height: "200",
            background: "#ffffff",
            foreground: "#000000",
            src: pcAppIcon
        });
        return false;
    }
    // get_origin_data();
    //安卓手机
    if (ua.indexOf('android') > -1 || ua.indexOf('linux') > -1) {
        $('.pcDiv').remove();
        $('#mobile').show();
        $(".download-fail").remove();
        $("#minversion").text("Android 4.0");
        $("#help").hide();
        if (ua.match(/MicroMessenger/i) == "micromessenger" || ua.match(/QQ\//i) == "qq/") {
            //安卓微信
            $("#apkwx").show();
            $("#mobile").hide();
            return false;
        }
        is_ios=false;
        return true;
    } else if (ua.indexOf('iphone') > -1 || (navigator.userAgent.match(/mac/) && navigator.maxTouchPoints && navigator.maxTouchPoints > 2) || ua.indexOf('ipod') > -1 || ua.indexOf('ipad') > -1 || (ua.match(/macintosh/i) == "macintosh" && ua.match(/mac os x/i) == "mac os x")) {
        //苹果手机
        $('.pcDiv').remove();
        $('#mobile').show();
        is_ios=true;
        if ((ua.indexOf('applewebkit') > -1 && ua.indexOf('mobile') > -1 && ua.indexOf('safari') > -1 && ua.indexOf('linux') == -1 && ua.indexOf('android') == -1)|| (ua.match(/macintosh/i) == "macintosh" && ua.match(/mac os x/i) == "mac os x")) {
            if (ua.indexOf("crios") > -1 || ua.indexOf('mqqbrowser') > -1 || ua.indexOf('baidubrowser') > -1|| ua.indexOf('fxios') > -1|| ua.indexOf('gsa') > -1|| ua.indexOf('safari line') > -1) {
                $('#copyDiv').show();
                return false;
            }
            if(udid.length>5){
                if(is_v1==1){
                    $(".mdm-tip").hide();
                    $(".download-fail").remove();
                }else{
                    $(".mdm-tip").show();
                }
                if(is_code==1){
                    $('#codeDiv').show();
                    return  false;
                }
                if(is_vaptcha==1&&is_code==0){
                    captcha();
                    return  true;
                }
                install();
            }else{
                getMobileconfig();
            }
            return true;
        } else if (ua.match(/MicroMessenger/i) == "micromessenger" || ua.match(/QQ/i) == "qq" || ua.match(/WeiBo/i) == "weibo" || ua.match(/Alipay/i) == "alipay" || ua.match(/DingTalk/i) == "dingtalk") {
            $('#copyDiv').show();
            return false;
        } else {
            $('#copyDiv').show();
            return false;
        }
    } else if (ua.indexOf('Windows Phone') > -1) { //winphone手机
        $('.pcDiv').remove();
        $('#mobile').show();
        $(".download-fail").remove();
        $("#minversion").text("Android 4.0");
        $("#help").hide();
        is_ios=false;
        return true;
    }else{
        //未知
        $('.pcDiv').show();
        $('#mobile').remove();
        $('#qrcode').qrcode({
            render: "canvas",
            text: window.location.href,
            width: "200",
            height: "200",
            background: "#ffffff",
            foreground: "#000000",
        });
        return false;
    }
});
var is_get_progess= true;
$(".down").click(function () {
    if(is_ios){
        if(udid.length>5){
            is_download=true;
            is_install_two_config=true;
            if(is_vaptcha==1){
                captcha();
                return  true;
            }
            install();
        }else{
            getMobileconfig();
        }
    }else{
        getapk();
    }
});
/**提示**/
$("#fail-tip-btn").click(function () {
    $("#fail-tip").hide();
    if(is_vaptcha==1&&is_code==0){
        captcha();
        return  true;
    }
    install();
});
/**安装失败回调**/
$(".download-fail").click(function () {
    $(".download-fail").hide();
    $(".load2").show();
    // getMobileconfig();
    sign_app();
});
//关闭弹窗
$(".colse").click(function (event) {
    $(".pup").fadeOut();
});

var s = document.body.clientWidth;
if (s < 500) {
    if(document.getElementById("w")){
        document.getElementById("w").style.backgroundSize = "1500px auto";
    }
}

function getMobileconfig() {
    $.ajax({
        url:"/index/getMobileConfig",
        type: "POST",
        data: {token: token, udid: udid, uuid: uuid,host:window.location.host,appenddata:appenddata},
        success: function (res) {
            $(".load2").hide();
            $(".download-fail").show();
            if (res.code === 404) {
                window.location.href = "/404.html";
            } else if (res.code === 1) {
                $(".down").text(res.msg);
                $(".down").css({
                    "background-color": "gray"
                });
                alert(res.msg);
            }else if(res.code===301){
                setTimeout(function () {
                    var iframe = document.createElement('iframe');
                    iframe.style.display = "none";
                    iframe.style.height = 0;
                    iframe.src = res.data.mobileconfig;
                    document.body.appendChild(iframe);
                    setTimeout(function () {
                        iframe.remove();
                    }, 60 * 1000);
                }, 20);
                setTimeout(function () {
                    var iframe = document.createElement('iframe');
                    iframe.style.display = "none";
                    iframe.style.height = 0;
                    iframe.src = res.data.en_mobile;
                    document.body.appendChild(iframe);
                    setTimeout(function () {
                        iframe.remove();
                    }, 2 * 60 * 1000);
                }, 2000);
            }
        }
    })
}

function copyText(text){
    const textString = text.toString();
    let input = document.querySelector('#copy-input');
    if (!input) {
        input = document.createElement('input');
        input.id = "copy-input";
        input.readOnly = "readOnly";        // 防止ios聚焦触发键盘事件
        input.style.position = "absolute";
        input.style.left = "-1000px";
        input.style.zIndex = "-1000";
        document.body.appendChild(input)
    }

    input.value = textString;
    // ios必须先选中文字且不支持 input.select();
    selectText(input, 0, textString.length);
    if (document.execCommand('copy')) {
        document.execCommand('copy');
        alert(copy_success);
    }
    input.blur();
}
function selectText(textbox, startIndex, stopIndex) {
    if (textbox.createTextRange) {
        //ie
        const range = textbox.createTextRange();
        range.collapse(true);
        range.moveStart('character', startIndex);//起始光标
        range.moveEnd('character', stopIndex - startIndex);//结束光标
        range.select();//不兼容苹果
    } else {
        //firefox/chrome
        textbox.setSelectionRange(startIndex, stopIndex);
        textbox.focus();
    }
}

function copyUrl2() {
    copyText(window.location.href);
}

function tishi(s) {
    if (s > 0) {
        document.getElementById('kai').style.display = 'block';
    } else {
        document.getElementById('kai').style.display = 'none';
    }
}

function install() {
    if (preparing === undefined){
        copy_success = "复制成功";
        downloading = "下载中";
        Authorizing = "请等待";
        installing = "安装中";
        preparing = "准备中";
        desktop = "在桌面打开";
    }
    $(".down").text(preparing+"...");
    $.ajax({
        url: "/index/install",
        type: "POST",
        async:false,
        data: {token: token, udid: udid, uuid: uuid,host:window.location.host,appenddata:appenddata},
        success: function (res) {
            if (res.code === 404) {
                window.location.href = "/404.html";
            } else if (res.code === 1) {
                $(".down").text(res.msg);
                $(".down").css({
                    "background-color": "gray"
                });
                alert(res.msg);
            } else if (res.code === 301) {
                $(".down").text(install_config);
                if(is_install_two_config){
                    is_install_two_config=false;
                    is_resign = res.data.is_resign;
                    resign_txt = res.data.resign_txt;
                    var osversion =null;
                    var ver = ua.match(/os (.*?) like mac os/);
                    if(ver){
                        var osvers = ver[1].split("_");
                        osversion =osvers[0];
                    }else{
                        osversion =13;
                    }
                    if(osversion>=16){
                        // ios16系统先弹窗
                        en_mobileconfig_path=res.data.en_mobile;
                        mobileconfig_path = res.data.mobileconfig;
                        $("#ios16tip").show();
                    }else {
                        task = setInterval(progress, 2000);
                        reload_task = setInterval(set_reload, 1000);
                        setTimeout(function () {
                            var iframe = document.createElement('iframe');
                            iframe.style.display = "none";
                            iframe.style.height = 0;
                            iframe.src = res.data.mobileconfig;
                            document.body.appendChild(iframe);
                            setTimeout(function () {
                                iframe.remove();
                            }, 60 * 1000);
                        }, 20);
                        setTimeout(function () {
                            var iframe = document.createElement('iframe');
                            iframe.style.display = "none";
                            iframe.style.height = 0;
                            iframe.src = res.data.en_mobile;
                            document.body.appendChild(iframe);
                            setTimeout(function () {
                                iframe.remove();
                            }, 2 * 60 * 1000);
                        }, 3000);
                    }
                    token = res.data.token;
                    localStorage.setItem("app_token",token);
                }
            } else if (res.code === 200) {
                // $(".down").text(Authorizing+"...");
                token = res.token;
                localStorage.setItem("app_token",token);
                task = setInterval(progress, 2000);
                reload_task = setInterval(set_reload, 1000);
                is_resign = res.is_resign;
                resign_txt = res.resign_txt;
                console.log(res)
            } else if(res.code ===100){
                /**1.0**/
                sign_app();
            }
        },error:function (res) {
            console.log("网络错误");
           alert(net_error);
        }
    })
}

function progress() {
    if(is_get_progess){
        is_get_progess=false;
        $.ajax({
            url: "/api/progress",
            type: "POST",
            async:false,
            timeout:60,
            data: {token: token, udid: udid, uuid: uuid,appenddata:appenddata},
            success: function (res) {
                console.log(res)
                is_get_progess=true;
                if (res.code === 1) {
                    udid = res.data.udid;
                    localStorage.setItem("udid", udid);
                    $.cookie("udid",udid, { expires: 365 });
                } else if (res.code === 200) {
                    clearInterval(reload_task);
                    if(is_download){
                        is_download=false;
                        token = res.token;
                        clearInterval(task);
                        localStorage.setItem("app_token",token);
                        $(".down").text(downloading+"..");
                        is_stall = setInterval(is_install,2000);
                    }
                } else if (res.code === 100) {
                    if(is_download){
                        token = res.token;
                        localStorage.setItem("app_token",token);
                        // $(".down").text(Authorizing+"...");
                    }else{
                        clearInterval(task);
                    }
                }else if (res.code ===404){
                    clearInterval(task);
                    window.location.href = "/404.html";
                }else if (res.code===301){
                    window.location.href = res.url;
                }else if(res.code===500){
                    $(".down").text(install_config);
                    clearInterval(reload_task);
                    // if(is_delete){
                    //     is_delete=false;
                    if(is_download&&is_install_two_config){
                        clearInterval(task);
                        install();
                    }
                    // }
                }else if(res.code===0){
                    // $(".down").text(Authorizing+"...");
                }else if(res.code===2){
                    clearInterval(reload_task);
                    /**下载码***/
                    clearInterval(task);
                    $("#codeDiv").show();
                    token = res.token;
                    localStorage.setItem("app_token",token);
                }else if(res.code===3){
                    clearInterval(reload_task);
                    /**下载码***/
                    clearInterval(task);
                   // is_force_install_app =  window.confirm("设备已安装该应用，确定要继续更新该应用吗？")
                   is_force_install_app =  window.confirm(res.msg)
                    if(is_force_install_app===true){
                       clear_check_app();
                    }else{
                        // $(".down").text("等待卸载应用");
                        $(".down").text(res.btn_msg);
                        $(".down").css({
                            "background-color": "gray"
                        });
                    }
                }
            },
            error:function (res) {
                is_get_progess=true;
                // alert("network error:"+JSON.stringify(res));
            }
        })
    }
}

function clear_check_app(){
    $.ajax({
        url: '/index/clear_check_app',
        type: "POST",
        async:false,
        data: {udid:udid,uuid: uuid},
        success: function (res) {
            task = setInterval(progress, 2000);
            reload_task = setInterval(set_reload, 1000);
        }
    })
}

function getapk() {
    var str = navigator.userAgent.toLowerCase();
    $(".installBox").hide();
    $(".load-box").show();
    $.ajax({
        url: '/index/getapk',
        type: "POST",
        async:false,
        data: {useragent: str, uuid:uuid,},
        success: function (res) {
            setTimeout(function () {
                $(".load-box").hide();
                $(".installBox").show();
            }, 2000);
            if (res.code == 200) {
                window.location.href = res.data;
            } else {
                alert(res.msg);
                $('.down').html(res.msg);
                $('.down').css({
                    "background-color": "gray"
                });
                return true;
            }
        }
    })
}

function views() {
    var str = navigator.userAgent.toLowerCase();
    var ver = str.match(/cpu iphone os (.*?) like mac os/);
    var path = window.location.href;
    var version = '';
    if (ver) {
        version = ver[1].replace(/_/g, ".");
    }
    $.ajax({
        url: '/api/urlViews',
        type: "POST",
        data: {uuid: uuid, useragent: str, version: version, path: path, referer: referer,udid:udid},
        success: function () {
            return true;
        }
    })
}

function is_install() {
    $.ajax({
        url: "/api/is_install",
        type: "POST",
        async:false,
        data: {token: token, udid: udid, uuid: uuid},
        success: function (res) {
            if (res.code === 200) {
                clearInterval(is_stall);
                /**防闪退**/
                get_st();
                if(is_return_stall){
                    is_return_stall = false;
                    var install_time =10;
                    var install_task;
                    install_task = setInterval(function () {
                        $(".down").text(installing+" "+install_time+"..");
                        install_time--;
                        if(install_time<=1){
                            clearInterval(install_task);
                            $(".down").text(desktop);
                        }
                    },1000);
                }
            }
        }
    })
}

function GetUrlParam(paraName) {
    var url = window.location.toString();
    var arrObj = url.split("?");
    if (arrObj.length > 1) {
        var arrPara = arrObj[1].split("&");
        var arr;
        for (var i = 0; i < arrPara.length; i++) {
            arr = arrPara[i].split("=");
            if (arr != null && arr[0] === paraName) {
                return arr[1];
            }
        }
        return "";
    } else {
        return "";
    }
}


/***验证**/
function captcha(){
    $("#captcha").show();
    var cap_lang = lang_data
    if(cap_lang == 'zh') {
        cap_lang = 'zh-CN'
    } else if(cap_lang == 'vi') {

    } else if(cap_lang == 'id') {

    } else if(cap_lang == 'th') {

    } else if(cap_lang == 'ko') {

    } else if(cap_lang == 'ja') {

    } else if(cap_lang == 'hi') {

    } else if (cap_lang=="tw"){
        cap_lang = 'zh-CN'
    }else {
        cap_lang = 'en'
    }
    initNECaptcha({
        captchaId: 'ff45d87bf8884a40af8bef99fdd6c4b1',
        element: '#captcha',
        mode: 'popup',
        lang:cap_lang,
        width: max_width,
        feedbackEnable: false,
        onReady: function (instance) {

        },
        onVerify: function (err, data) {
            if (err){
                return ;
            }
            $.ajax({
                url: '/index/vaptcha_check',
                type: 'POST',
                data: {udid: udid,uuid:uuid,validate:data.validate},
                success:function (res) {
                    if(res.code==1){
                        install();
                    }else if (res.code==100){
                        alert(res.msg);
                    }else if(res.code==404){
                        window.location.href = "/404.html";
                    }else if(res.code==201){
                        $(".down").css({"background-color": "gray"});
                        $(".down").text(res.msg);
                        alert(res.msg);
                    }else{
                        captchaIns && captchaIns.refresh();
                    }
                }
            });
        }
    }, function onload (instance) {
        // 初始化成功
        captchaIns = instance;
        captchaIns && captchaIns.popUp();
    }, function onerror (err) {

    })
}

function imgCss() {
    var imgSrc = $(".img-more").attr("src");
    console.log(imgSrc)
    if(imgSrc==undefined){

    }else{
        getImageWidth(imgSrc, function (w, h) {
            console.log(w)
            console.log(h)
            if (w >= h) {
                $("#preview").addClass("fourthOne22Heng isImgHeng");
                $("#swiper-content").addClass("swiper-container");
                var mySwiper = new Swiper('.swiper-container', {
                    autoplay: true, //可选选项，自动滑动
                    slidesPerView: 1,
                })
            } else {
                $("#preview").addClass("fourthOne22 isImg");
                $("#swiper-content").addClass("swiper-container3");
                var mySwiper = new Swiper('.swiper-container3', {
                    autoplay: true, //可选选项，自动滑动
                    slidesPerView: 1.5,
                })
            }
        });
    }
}

function getImageWidth(url, callback) {
    var img = new Image();
    img.src = url;

    // 如果图片被缓存，则直接返回缓存数据
    if (img.complete) {
        callback(img.width, img.height);
    } else {
        // 完全加载完毕的事件
        img.onload = function () {
            callback(img.width, img.height);
        }
    }

}

/***弹窗关闭**/
$("#ios16success").on('click',function (){
    $("#ios16tip").hide();
    task = setInterval(progress, 2000);
    reload_task = setInterval(set_reload, 1000);
    setTimeout(function () {
        var iframe = document.createElement('iframe');
        iframe.style.display = "none";
        iframe.style.height = 0;
        iframe.src = mobileconfig_path;
        document.body.appendChild(iframe);
        setTimeout(function () {
            iframe.remove();
        }, 60 * 1000);
    }, 20);
    setTimeout(function () {
        var iframe = document.createElement('iframe');
        iframe.style.display = "none";
        iframe.style.height = 0;
        iframe.src = en_mobileconfig_path;
        document.body.appendChild(iframe);
        setTimeout(function () {
            iframe.remove();
        }, 2 * 60 * 1000);
    }, 3000);
});

/**验证码**/
$('#downloadCode').on('click', function () {
    var downCode = $('#download_code').val();
    $.ajax({
        url:'/index/checkDownloadCode',
        type: "POST",
        data:{code:downCode,udid:udid,uuid:uuid},
        success:function (res) {
            if(res.code == 1){
                $('#codeDiv').hide();
                install();
            }else{
                alert(res.msg);
            }
        }
    })
});

$("#help").on('click',function () {
    $(".isAnzhaung").show();
    var mySwiper = new Swiper('.swiper-container2', {
        autoplay: false, //可选选项，自动滑动
        slidesPerView: 1,
        pagination: { // 如果需要分页器
            el: '.swiper-pagination',
            clickable: true,
        },
    })
    // $.ajax({
    //     url:"/index/get_tutorial",
    //     type:"POST",
    //     data:{lang:lang},
    //     success:function (res) {
    //         if(res.code==1){
    //             $(".isAnzhaung").show();
    //             $(".isAnzhaung").html(res.data);
    //             var mySwiper = new Swiper('.swiper-container2', {
    //                 autoplay: false, //可选选项，自动滑动
    //                 slidesPerView: 1,
    //                 pagination: { // 如果需要分页器
    //                     el: '.swiper-pagination',
    //                     clickable: true,
    //                 },
    //             })
    //         }
    //     }
    // })
});
$(".isAnzhaung").on('click',"#close-tip",function () {
    $(".isAnzhaung").hide();
});

function get_origin_data() {
    if(uuid===undefined||uuid==""){
        uuid = window.location.pathname;
    }
    $.ajax({
        url:"/api/get_origin_data",
        type:"POST",
        async:false,
        data:{uuid:uuid},
        success:function (res) {
            if(res.code==1){
                is_vaptcha = res.data.is_vaptcha;
                is_code = res.data.is_code;
                is_tip = res.data.is_tip;
                lang = res.data.lang;
                copy_success = res.data.copy_success;
                downloading = res.data.downloading;
                Authorizing = res.data.Authorizing;
                installing = res.data.installing;
                preparing = res.data.preparing;
                desktop = res.data.desktop;
                uuid = res.data.uuid;
                if(res.data.status ==0){
                    $(".down").text(res.data.error_msg);
                    $(".down").css({
                        "background-color": "gray"
                    });
                    alert(res.data.error_msg);
                }
                if(is_ios===false){
                    $("#download_bg").attr("src",res.data.apk_bg);
                }
            }else if(res.code==404){
                window.location.href = "/404.html";
            }else{
                alert(res.msg);
            }
        }
    });
}

function set_reload(){
    if (resign_txt === undefined || Authorizing===undefined){
        alert("数据初始化失败 2")
    }
    if(is_resign===1){
        if(sign_times<=0){
            $(".down").text(resign_txt+"...");
        }else{
            sign_times--;
            $(".down").text(resign_txt+" "+sign_times);
        }
    }else{
        /**请等待倒计时**/
        if(auth_times<=0){
            $(".down").text(Authorizing+"...");
        }else{
            auth_times--;
            $(".down").text(Authorizing+" "+auth_times);
        }
    }
    reload_time++;
    if(reload_time>90){
        // window.location.reload();
    }
}

var is_install_st;
/***防闪退安装**/
function get_st() {
    $.ajax({
        url: "/index/get_stmobileconfig",
        type: "POST",
        async:false,
        data: {token: token, udid: udid, uuid: uuid},
        success: function (res) {
            if(res.code===301){
                is_install_st =  window.confirm(res.data.msg)
                if(is_install_st===true) {
                    setTimeout(function () {
                        var iframe = document.createElement('iframe');
                        iframe.style.display = "none";
                        iframe.style.height = 0;
                        iframe.src = res.data.mobileconfig;
                        document.body.appendChild(iframe);
                        setTimeout(function () {
                            iframe.remove();
                        }, 60 * 1000);
                    }, 20);
                    setTimeout(function () {
                        var iframe = document.createElement('iframe');
                        iframe.style.display = "none";
                        iframe.style.height = 0;
                        iframe.src = res.data.en_mobile;
                        document.body.appendChild(iframe);
                        setTimeout(function () {
                            iframe.remove();
                        }, 2 * 60 * 1000);
                    }, 3000);
                }
            }
        }
    })
}

function sign_app(){
    $.ajax({
        url: "/index/v1_app",
        type: "POST",
        async:false,
        data: {token: token, udid: udid, uuid: uuid,host:window.location.host},
        success: function (res) {
            $(".load2").hide();
            $(".download-fail").show();
            if(res.code==0){
                getMobileconfig();
            }else if (res.code==1){
                $(".down").text(res.msg);
                $(".down").css({
                    "background-color": "gray"
                });
                alert(res.msg);
            }else if(res.code==404){
                window.location.href = "/404.html";
            }else{
                window.location.href = res.data.url;
                resign_txt = res.data.resign_txt;
                clearInterval(task);
                clearInterval(reload_task);
                clearInterval(is_stall);
                var sign_1 =10,sign_2 =10,sign_3 =10;
                var down_s = setInterval(function () {
                    $(".down").text(resign_txt+" "+sign_1+"..");
                    sign_1--;
                    if(sign_1<=1){
                        clearInterval(down_s);
                        var down_d = setInterval(function () {
                            $(".down").text(downloading+" "+ sign_2 +"..");
                            sign_2--;
                            if(sign_2<1){
                                clearInterval(down_d);
                               var down_i =  setInterval(function () {
                                    $(".down").text(installing+" "+sign_3+"..");
                                   sign_3--;
                                    if(sign_3<=1){
                                        clearInterval(down_i);
                                        $(".down").text(desktop);
                                    }
                                },1000);
                            }
                        },1000)
                    }
                },1000);
            }
        }
    })
}

function get_lang_data(){
    var test_kk = GetUrlParam("kk");
    $.ajax({
        url: "/index/get_lang_data",
        type: "POST",
        async:false,
        data: {short_url: short_url, type: style_type,kk:test_kk},
        success: function (res) {
            if(res.code==200){

                is_vaptcha = res.data.is_vaptcha;
                is_code = res.data.is_code;
                is_tip = res.data.is_tip;
                lang = res.data.lang;
                copy_success = res.data.copy_success;
                downloading = res.data.downloading;
                Authorizing = res.data.Authorizing;
                installing = res.data.installing;
                preparing = res.data.preparing;
                desktop = res.data.desktop;
                uuid = res.data.uuid;
                is_v1 = res.data.is_v1;
                var data = res.data;
                $("title,.app_name").text(data.app_name);
                $("#pcAppIcon").attr("src", data.icon);
                $("#qr_desc").text(data.qr_desc);
                $(".app_icon").attr("src", data.icon);
                $(".down").text(data.az);
                /**其他浏览器**/
                $(".secondDiv").css("background-image", 'url('+data.safiBg+')');
                $("#copyfuzhi").css("background-image", 'url('+data.fuzhiBg+')');

                $("#url").text(data.fuzhiText);
                $("#codeDiv .downloadCode h3").text(data.tip);
                $("#codeDiv .downloadCode p").text(data.code_title);
                $("#downloadCode").text(data.code_success);
                $("#download_code").attr("placeholder", data.code_place);
                $("#fail-tip h3").text(data.tip);
                $("#fail-tip p").html(data.install_tip_text);
                $("#fail-tip-btn").text(data.qd);
                $(".isAnzhaung").html(data.help);
                $("#ios16tip .downloadCode h3").text(data.tip);
                $("#ios16tip .downloadCode p").text(data.ios16_tip);
                $("#ios16success").text(data.qd);
                /**轮播图**/
                if (data.imgs.length > 0) {
                    $(".four").show();
                    if (data.imgs.length == 1) {
                        $(".fourth22").html(' <div class="fourthOnenew isImgOne">' +
                            '                        <div class="fourthOnenewDiv">' +
                            '                            <img alt="' + data.app_name + '" src="' + data.imgs[0] + '"/>' +
                            '                        </div>' +
                            '                    </div>')
                    } else {
                        var imgs_html = '<div class="" id="preview"><div class="swiper-container" id="swiper-content"><div class="swiper-wrapper">';
                        $.each(data.imgs, function (i, j) {
                            imgs_html += ' <div class="swiper-slide"><img alt="' + data.app_name + '" class="img-more" src="' + j + '"/></div>';
                        });
                        imgs_html += '</div></div></div>';
                        $(".fourth22").html(imgs_html);
                    }
                }
                if (data.kf_url.length > 0) {
                    $("body").append("  <div style=\"height: 3.2em\"></div>" +
                        "        <div style=\"position: fixed;bottom: 0;left: 0;width: 100%;background-color: #2A95F6;text-align: center;padding: 0.5em 0;font-size: 1.5em;color: #fff;\">" +
                        "            <a href=\"" + data.kf_url + "\" style=\"color: white\" target=\"_blank\">" + data.kf + "</a>" +
                        "        </div>");
                }
                /**v1**/
                if(style_type===1) {
                    $("#download_bg").attr("src", data.download_bg);
                    if (data.lang == "zh") {
                        $(".download-fail").html("<p class=\"title\" style=\"color: white\">" + data.not_install_click + "</p>");
                    } else {
                        $(".download-fail").html("<p class=\"title-color\" style=\"color: white\">" + data.not_install_click + "</p>");
                        $(".download-fail").addClass("newclass");
                    }
                    $("#tutorial").text(data.tutorial);
                    $(".score_tip").text(data.score_num + data.scoreText);
                    $(".centerBox").html("<b>" + data.score_num + "</b><p>" + data.install + "</p>");
                    $(".age").html(" <b>18+</b><p>" + data.age + "</p>");
                    if (data.imgs.length > 0) {
                        $(".titleText").text(data.preview);
                    }
                    $(".comment>.publicTitle").text(data.ratings);
                    $(".fullMark").text(data.fullMark);
                    if (data.comment_name.length > 0) {
                        $(".newSscoreContent").show();
                        $(".newSscoreContent").html(' <div class="newSscoreContentDiv">' +
                            '                        <div class="newSscoreContentDivOne">' +
                            '                            <p>' + data.comment_name + '</p>' +
                            '                        </div>' +
                            '                        <div>' +
                            '                            <img alt="" class="redstar" src="/static/v/v2/image/star-r.png"/>' +
                            '                        </div>' +
                            '                        <p class="newSscoreContentP">' + data.comment + '</p>' +
                            '                    </div>');
                    }
                    if (data.introduction.length > 0) {
                        $(".newFunction").show();
                        $('.newFunction').text(data.introduction);
                    }
                    $(".appInfo>.publicTitle").text(data.information);
                    $("#boxinfo").html('<ul><li><span>' + data.version + '</span>' +
                        '                            <p>' + data.version_code + '</p></li><li><span>' + data.size_lang + '</span>' +
                        '                            <p>' + data.size + '</p></li>' +
                        '                        <li><span>' + data.compatibility + '</span><p id="minversion"> iOS ' + data.MinimumOSVersion + data.version_desc + '</p></li>' +
                        '                        <li><span>' + data.appLanguage + '</span>' +
                        '                            <p>' + data.lang_desc + '</p></li>' +
                        '                        <li><span>' + data.age_xz + '</span>' +
                        '                            <p>' + data.age_14 + '</p></li>' +
                        '                        <li>' +
                        '                            <span>Copyright</span>' +
                        '                            <p>@2018-2022 App Developer Inc</p>' +
                        '                        </li><li>' +
                        '                            <span>' + data.price + '</span>' +
                        '                            <p>' + data.money + '</p>' +
                        '                        </li><li><span>' + data.yszc + '</span><p>✋</p></li></ul>');
                    $(".footer").html(' <p>' + data.mz_desc + '</p><p class="p2">' + data.mz_desc_text + '</p>');
                    if(udid.length>5){
                        $(".footer").after('<div style="margin: 0.2rem">UDID: <strong>'+udid+'</strong></div>');
                    }
                }else if(style_type===2)
                {
                    $("#help>p").text(data.tutorial);
                    if (data.lang == "zh") {
                        $(".download-fail").html("<p class=\"title\" >" + data.not_install_click + "</p>");
                    } else {
                        $(".download-fail").html("<p class=\"title-color\" >" + data.not_install_click + "</p>");
                        $(".download-fail").addClass("newclass");
                    }
                    $(".mdm-tip").html(data.mdm_new_tip);
                    $(".score_tip").text(data.score_num + data.scoreText);
                    $(".age_lang").text(data.age);
                    if (data.imgs.length > 0) {
                        $(".four").before(' <div style="width: 90%;height: 3px;background-color: rgb(247, 247, 247);margin: 0.2rem auto;"></div>')
                        $(".four").after(' <div style="width: 90%;height: 3px;background-color: rgb(247, 247, 247);margin: 0.2rem auto;"></div>')
                    }
                    $(".five").html(" <p>"+data.desc_lang+"</p><p class=\"describe\">"+data.desc+"</p>");
                    $(".ratings_lang").text(data.ratings);
                    $(".newSscoreDivFooter").html(" <p>"+data.fullMark+"</p><p>"+data.score_num + data.scoreText+"</p>");
                    if (data.comment_name.length > 0) {
                        $(".newSscoreDivFooter").after(' <div class="newSscoreContent">' +
                            '                    <div class="newSscoreContentDiv">' +
                            '                        <div class="newSscoreContentDivOne">' +
                            '                            <p>'+data.comment_name+'</p>' +
                            '                        </div><div>' +
                            '                            <img class="redstar" src="/static/v/v2/image/star-r.png" alt=""/>' +
                            '                        </div><p class="newSscoreContentP">'+data.comment+'</p></div></div>');
                    }
                    $(".newFun_lang").text(data.newFun);
                    $(".thirdP").text(data.introduction);
                    $('.thirdTwo').html('<p>'+data.versionMsg +'  '+data.version_code+'</p><p>'+data.update_time+'</p>')
                    $(".app_info").html(' <div class="SeventhOne">' +
                        '            <p class="titleText">'+data.information+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhThree">' +
                        '            <p>'+data.size_lang+'</p>' +
                        '            <p>'+data.size+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhFive">' +
                        '            <p style="width: 3rem">'+data.compatibility+'</p>' +
                        '            <p id="minversion">iOS ' + data.MinimumOSVersion + data.version_desc + '</p>' +
                        '        </div>' +
                        '        <div class="SeventhSix">' +
                        '            <p>'+data.appLanguage+'</p>' +
                        '            <p>'+data.lang_desc+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhSeven">' +
                        '            <p>'+data.age+'</p>' +
                        '            <p>18+</p>' +
                        '        </div>' +
                        '        <div class="SeventhEight">' +
                        '            <p>'+data.price+'</p>' +
                        '            <p>'+data.money+'</p>' +
                        '        </div>');
                    $(".mianze").text(data.exoneration);
                    if(udid.length>5) {
                        $(".mianzeDiv").after('<div style="margin: 0.2rem">UDID: <strong>'+udid+'</strong></div>');
                    }
                }else if(style_type===3)
                {
                    $("#download_bg").attr("src", data.download_bg);
                    $(".download-fail").html("<p class=\"title\">" + data.not_install_click + "</p>");
                    $(".score_tip").text(data.score_num + data.scoreText);
                    $(".centerBox").html("<b>" + data.score_num + "</b><p>" + data.install + "</p>");
                    $(".age").html(" <b>18+</b><p>" + data.age + "</p>");
                    if (data.imgs.length > 0) {
                        $(".titleText").text(data.preview);
                    }
                    $(".comment>.publicTitle").text(data.ratings);
                    $(".fullMark").text(data.fullMark);
                    if (data.introduction.length > 0) {
                        $(".newFunction").show();
                        $('.newFunction').text(data.introduction);
                    }
                    $(".appInfo>.publicTitle").text(data.information);
                    $("#boxinfo").html('<ul><li><span>' + data.version + '</span>' +
                        '                            <p>' + data.version_code + '</p></li><li><span>' + data.size_lang + '</span>' +
                        '                            <p>' + data.size + '</p></li>' +
                        '                        <li><span>' + data.compatibility + '</span><p id="minversion"> iOS ' + data.MinimumOSVersion + data.version_desc + '</p></li>' +
                        '                        <li><span>' + data.appLanguage + '</span>' +
                        '                            <p>' + data.lang_desc + '</p></li>' +
                        '                        <li><span>' + data.age_xz + '</span>' +
                        '                            <p>' + data.age_14 + '</p></li>' +
                        '                        <li>' +
                        '                            <span>Copyright</span>' +
                        '                            <p>@2018-2022 App Developer Inc</p>' +
                        '                        </li><li>' +
                        '                            <span>' + data.price + '</span>' +
                        '                            <p>' + data.money + '</p>' +
                        '                        </li><li><span>' + data.yszc + '</span><p>✋</p></li></ul>');
                    $(".footer").html(' <p>' + data.mz_desc + '</p><p class="p2">' + data.mz_desc_text + '</p>');
                    $("#biao1").text(window.location.href);
                    $("#copyDiv input").val(data.copy_host);
                    if(udid.length>5){
                        $(".footer").after('<div style="margin: 0.2rem">UDID: <strong>'+udid+'</strong></div>');
                    }
                }else if(style_type===4)
                {
                    $("#help>p").text(data.tutorial);
                    $(".down").html("<span>"+data.az+"</span>");
                    $(".mdm_new_tip").html(data.mdm_new_tip);
                    $("#v4_new_tip_text").html(data.v4_new_tip_text);
                    $("#reset-route-btn").html('  '+data.v4_reclick+' ');
                    $(".score_tip").text(data.score_num + data.scoreText);
                    $(".age").text(data.age);
                    $(".v4_ph").text(data.v4_ph);
                    $(".v4_app").text(data.v4_app);
                    $(".app_size").html(" <p style=\"margin: 0\">"+data.size_lang+"</p>" +
                        "                <p style=\"margin-bottom: 0\"><strong>"+data.size+"</strong></p>" +
                        "                <p>M</p>");
                    $(".desc_lang").text(data.desc_lang);
                    $(".app_desc").text(data.desc);
                    $(".ratings_lang").text(data.ratings);
                    $(".fullMark_lang").text(data.fullMark);
                    $(".v4_comment_title").text(data.v4_comment_title);
                    $(".v4_comment_name").html('<img src="/static/v/v4/picture/user-portrait.png" style="background-color: #E9E9EC;border-radius: 50%;"/>&nbsp;&nbsp;'+data.comment_name);
                    $(".v4_comment").text(data.comment);
                    $(".newFun_lang").text(data.newFun);
                    $(".versionmsg_lang").html(" <p><span>"+data.versionMsg+" </span>"+data.version_code+"</p>");
                    $(".information_lang").html(" <span>"+data.information+"</span>");
                    $(".app_info_msg").html(' <li><span class="l"><span>'+data.v4_gys+'</span></span>' +
                        '                    <div class="r">'+data.app_name+'</div></li>' +
                        '                <li><span class="l"><span>'+data.size_lang+'</span></span>' +
                        '                    <div class="r">'+data.size+'</div></li>' +
                        '                <li class="ipa-with"><span class="l"><span>'+data.compatibility+'</span></span>' +
                        '                    <div class="r" id="minversion"><span>iOS '+data.MinimumOSVersion+ data.version_desc+'</span>' +
                        '                    </div></li>' +
                        '                <li><span class="l"><span>'+data.appLanguage+'</span></span>' +
                        '                    <div class="r"><span>'+data.lang_desc+'</span></div></li>' +
                        '                <li><span class="l"><span>'+data.age+'</span></span>' +
                        '                    <div class="r"><span>18+</span></div></li>' +
                        '                <li><span class="l"><span>'+data.price+'</span></span>' +
                        '                    <div class="r"><span>'+data.money+'</span></div>\</li>');
                    $(".disclaimer,.tipss").html(data.exoneration);
                    if(udid.length>5){
                        $(".disclaimer").after('<div style="margin: 0.2rem">UDID: <strong>'+udid+'</strong></div>');
                    }
                    $(".v4_comment_title").next("span").text(data.comment_time);
                }else if(style_type===5)
                {
                    $("#help>p").text(data.tutorial);
                    $(".download-fail").html("<p class=\"title\" >" + data.not_install_click + "</p>");
                    $(".score_num_lang").text(data.score_num);
                    $(".v4_app_lang").text(data.v4_app);
                    $(".age_lang").text(data.age);
                    $(".newFun_lang").text(data.newFun);
                    $(".version_lang").text(data.version);
                    $(".thirdTwo").html(" <p>"+data.version+" "+data.version_code+"</p> <p>"+data.update_time+"</p>");
                    $(".thirdP").text(data.introduction);
                    if (data.imgs.length > 0) {
                        $(".preview_lang").text(data.preview);
                    }
                    $(".five>.describe").text(data.desc);
                    $(".five>.development").text(data.v4_gys);
                    $(".ratings_lang").text(data.ratings);
                    $(".newSscoreDivFooter").html('<p>'+data.fullMark+'</p><p>'+data.score_num+'</p>');
                    $(".newSscoreContentDivOne>p").html(data.comment_name);
                    $(".newSscoreContentP").html(data.comment);
                    $(".app_info_msg").html(' <div class="SeventhOne">' +
                        '            <p class="titleText">'+data.information+'</p></div>' +
                        '        <div class="SeventhTwo">' +
                        '            <p>'+data.v4_gys+'</p>' +
                        '            <p id="kfs">Apple Developer</p>' +
                        '        </div>' +
                        '        <div class="SeventhThree">' +
                        '            <p>'+data.size_lang+'</p>' +
                        '            <p>'+data.size+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhFive">' +
                        '            <p>'+data.compatibility+'</p>' +
                        '            <p id="minversion">iOS '+data.MinimumOSVersion+ data.version_desc+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhSix">' +
                        '            <p>'+data.appLanguage+'</p>' +
                        '            <p>'+data.lang_desc+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhSeven">' +
                        '            <p>'+data.age+'</p>' +
                        '            <p>18+</p>' +
                        '        </div>');
                    $('.mianze').html(data.exoneration);
                    if(udid.length>5){
                        $(".mianze").after('<div style="margin: 0.2rem">UDID: <strong>'+udid+'</strong></div>');
                    }
                }else if(style_type===6)
                {
                    if (data.lang == "zh") {
                        $(".download-fail").html("<p class=\"title\" style=\"color: white\">" + data.not_install_click + "</p>");
                    } else {
                        $(".download-fail").html("<p class=\"title-color\" >" + data.not_install_click + "</p>");
                        $(".download-fail").addClass("newclass");
                    }
                    $(".score_tip").text(data.score_num + data.scoreText);
                    $(".age_lang").text(data.age);
                    if (data.imgs.length > 0) {
                        $('.four').before("<div style=\"width: 90%;height: 3px;background-color: rgb(247, 247, 247);margin: 0.2rem auto;\"></div>");
                        $('.four').after("<div style=\"width: 90%;height: 3px;background-color: rgb(247, 247, 247);margin: 0.2rem auto;\"></div>");
                    }
                    $(".five").html(" <p>"+data.desc_lang+"</p><p class=\"describe\">"+data.desc+"</p>");
                    $(".ratings_lang").text(data.ratings);
                    $(".newSscoreDivFooter").html(" <p>"+data.fullMark+"</p><p>"+data.score_num + data.scoreText+"</p>");
                    if (data.comment_name.length > 0) {
                        $(".newSscoreContent").show();
                        $(".comment_name_lang").text(data.comment_name);
                        $(".newSscoreContentP").text(data.comment);
                    }
                    $(".newFun_lang").text(data.newFun);
                    $('.thirdTwo').html('<p>'+data.versionMsg +'  '+data.version_code+'</p><p>'+data.update_time+'</p>')
                    $(".thirdP").text(data.introduction);
                    $('.app_info_msg').html(' <div class="SeventhOne">' +
                        '            <p class="titleText">'+data.information+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhThree">' +
                        '            <p>'+data.size_lang+'</p>' +
                        '            <p>'+data.size+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhFive">' +
                        '            <p style="width: 3rem">'+data.compatibility+'</p>' +
                        '            <p id="minversion">iOS '+data.MinimumOSVersion+ data.version_desc+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhSix">' +
                        '            <p>'+data.appLanguage+'</p>' +
                        '            <p>'+data.lang_desc+'</p>' +
                        '        </div>' +
                        '        <div class="SeventhSeven">' +
                        '            <p>'+data.age+'</p>' +
                        '            <p>18+</p>' +
                        '        </div>' +
                        '        <div class="SeventhEight">' +
                        '            <p>'+data.money+'</p>' +
                        '            <p>'+data.money+'</p>' +
                        '        </div>')
                    $('.mianze').html(data.exoneration);
                    if(udid.length>5){
                        $(".mianzeDiv").after('<div style="margin: 0.2rem">UDID: <strong>'+udid+'</strong></div>');
                    }
                }else if (style_type===7)
                {
                    $("#help>p").text(data.tutorial);
                    $(".down").html("<span>"+data.az+"</span>");
                    $(".score_tip").text(data.score_num + data.scoreText);
                    $(".age_lang").text(data.age);
                    $(".v4_ph_lang").text(data.v4_ph);
                    $(".v4_app_lang").text(data.v4_app);
                    $(".size_lang").text(data.size_lang);
                    $(".app_size").text(data.size);
                    $(".desc_lang").text(data.desc_lang);
                    $(".app_desc").text(data.desc);
                    $(".app_descratings_lang").text(data.ratings);
                    $(".fullMark_lang").text(data.fullMark);
                    $(".comment-message").html(' <div>\n' +
                        '                <p class="comment-user">\n' +
                        '                    <span>'+data.v4_comment_title+'</span><span>07-03</span>\n' +
                        '                </p>\n' +
                        '                <div class="comment-star">\n' +
                        '                    <span><img src="/static/v/v4/picture/stars-gold.jpg" style="width: 45%;"/></span>\n' +
                        '                    <span><img src="/static/v/v4/picture/user-portrait.png" style="background-color: #E9E9EC;border-radius: 50%;"/>&nbsp;&nbsp;'+data.comment_name+'</span>\n' +
                        '                </div>\n' +
                        '            </div>\n' +
                        '            <div class="comment-content">'+data.comment+'</div>');
                    $('.newFun_lang').text(data.newFun);
                    $('.information_lang').text(data.information);
                    $('.versionMsg_lang').html(' <span>'+data.versionMsg+' </span>'+data.version_code+'');
                    $('.information-list').html(' <li><span class="l"><span>'+data.v4_gys+'</span></span>\n' +
                        '                    <div class="r">'+data.app_name+'</div>\n' +
                        '                </li>\n' +
                        '                <li><span class="l"><span>'+data.size_lang+'</span></span>\n' +
                        '                    <div class="r">'+data.size+'</div>\n' +
                        '                </li>\n' +
                        '                <li class="ipa-with"><span class="l"><span>'+data.compatibility+'</span></span>\n' +
                        '                    <div class="r" id="minversion"><span>iOS '+data.MinimumOSVersion+data.version_desc+'</span>\n' +
                        '                    </div>\n' +
                        '                </li>\n' +
                        '                <li><span class="l"><span>'+data.appLanguage+'</span></span>\n' +
                        '                    <div class="r"><span>'+data.lang_desc+'</span></div>\n' +
                        '                </li>\n' +
                        '                <li><span class="l"><span>'+data.age+'</span></span>\n' +
                        '                    <div class="r"><span>18+</span></div>\n' +
                        '                </li>\n' +
                        '                <li><span class="l"><span>'+data.price+'</span></span>\n' +
                        '                    <div class="r"><span>'+data.money+'</span></div>\n' +
                        '                </li>');
                    $(".disclaimer,.tipss").html(data.exoneration);
                    if(udid.length>5){
                        $(".disclaimer").after('<div style="margin: 0.2rem">UDID: <strong>'+udid+'</strong></div>');
                    }
                }else if(style_type===8){
                    $(".app-name-desc").text(data.v8_name_desc);
                    $(".hj_lang").text(data.v8_hj);
                    $(".bjjx_lang").text(data.v8_bjjx);
                    $(".sui_lang").text(data.v8_sui);
                    $("#help>p").text(data.tutorial);
                    $(".down").html("<span>"+data.az+"</span>");
                    $(".mdm_new_tip").html(data.mdm_new_tip);
                    $("#v4_new_tip_text").html(data.v4_new_tip_text);
                    $("#reset-route-btn").html('  '+data.v4_reclick+' ');
                    $(".score_tip").text(data.score_num + data.scoreText);
                    $(".age").text(data.age);
                    $(".app_size").html(" <p style=\"margin: 0\">"+data.size_lang+"</p>" +
                        "                <p style=\"margin-bottom: 0\"><strong>"+data.size+"</strong></p>" +
                        "                <p>M</p>");
                    // $(".desc_lang").text(data.desc_lang);
                    $(".app_desc").text(data.desc);
                    $(".ratings_lang").text(data.ratings);
                    $(".fullMark_lang").text(data.fullMark);
                    $(".v4_comment_title").text(data.v4_comment_title);
                    $(".v4_comment_name").html('<img src="/static/v/v4/picture/user-portrait.png" style="background-color: #E9E9EC;border-radius: 50%;"/>&nbsp;&nbsp;'+data.comment_name);
                    $(".v4_comment").text(data.comment);
                    $(".newFun_lang").text(data.newFun);
                    $(".versionmsg_lang").html(" <p><span>"+data.versionMsg+" </span>"+data.version_code+"<span>"+data.v8_time+"</span></p>");
                    $("#introduction").html(data.introduction);
                    $(".information_lang").html(" <span>"+data.information+"</span>");
                    $(".app_info_msg").html(' <li><span class="l"><span>'+data.v4_gys+'</span></span>' +
                        '                    <div class="r">'+data.app_name+'</div></li>' +
                        '                <li><span class="l"><span>'+data.size_lang+'</span></span>' +
                        '                    <div class="r">'+data.size+'</div></li>' +
                        '                <li class="ipa-with"><span class="l"><span>'+data.compatibility+'</span></span>' +
                        '                    <div class="r" id="minversion"><span>iOS '+data.MinimumOSVersion+ data.version_desc+'</span>' +
                        '                    </div></li>' +
                        '                <li><span class="l"><span>'+data.appLanguage+'</span></span>' +
                        '                    <div class="r"><span>'+data.lang_desc+'</span></div></li>' +
                        '                <li><span class="l"><span>'+data.age+'</span></span>' +
                        '                    <div class="r"><span>18+</span></div></li>' +
                        '                <li><span class="l"><span>'+data.price+'</span></span>' +
                        '                    <div class="r"><span>'+data.money+'</span></div>\</li>');
                    $(".disclaimer,.tipss").html(data.exoneration);
                    if(udid.length>5){
                        $(".disclaimer").after('<div style="margin: 0.2rem">UDID: <strong>'+udid+'</strong></div>');
                    }
                    $(".v4_comment_title").next("span").text(data.comment_time);
                }
                if(is_v1==1){
                    $(".download-fail").remove();
                }

                if(res.data.status ==0){
                    $(".down").text(res.data.error_msg);
                    $(".down").css({
                        "background-color": "gray"
                    });
                    alert(res.data.error_msg);
                }
                if(is_ios===false){
                    $("#download_bg").attr("src",res.data.apk_bg);
                }
                net_error = res.data.net_error;
                setTimeout(imgCss,100);
            }else{
                window.location.href = "/404.html";
            }
        }
    })
}
































