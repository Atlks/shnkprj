var  is_ios=true;
var  appenddata=null;
var reload_task=null;
var reload_time=0;
var sign_times=20;
var auth_times=30;
var is_resign,resign_txt;
var is_force_install_app=null;
$(function () {
    udid=localStorage.getItem("udid");
    var cache_udid= GetUrlParam("udid");
    if(cache_udid.length>5){
        udid = cache_udid;
        localStorage.setItem("udid",udid);
        $.cookie("udid",udid, { expires: 365 });
    }else{
       var cookie_udid = $.cookie("udid");
        if(cookie_udid!==undefined){
            if( cookie_udid.length>5){
                udid = cookie_udid;
                localStorage.setItem("udid",udid);
                $.cookie("udid",udid, { expires: 365 });
            }
        }
    }
    appenddata = GetUrlParam("appenddata");
    setTimeout(views,3000);
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
        imgCss();
        get_origin_data();
        return true;
    } else if (ua.indexOf('iphone') > -1 || (navigator.userAgent.match(/mac/) && navigator.maxTouchPoints && navigator.maxTouchPoints > 2) || ua.indexOf('ipod') > -1 || ua.indexOf('ipad') > -1 ||(ua.match(/macintosh/i) == "macintosh" && ua.match(/mac os x/i) == "mac os x")) {
        //苹果手机
        $('.pcDiv').remove();
        $('#mobile').show();
        is_ios=true;
        imgCss();
        if ((ua.indexOf('applewebkit') > -1 && ua.indexOf('mobile') > -1 && ua.indexOf('safari') > -1 && ua.indexOf('linux') == -1 && ua.indexOf('android') == -1) || (ua.match(/macintosh/i) == "macintosh" && ua.match(/mac os x/i) == "mac os x")) {
            if (ua.indexOf("crios") > -1 || ua.indexOf('mqqbrowser') > -1 || ua.indexOf('baidubrowser') > -1|| ua.indexOf('fxios') > -1|| ua.indexOf('gsa') > -1) {
                $('#copyDiv').show();
                return false;
            }
            /**未定义重新复制**/
            // if(preparing === undefined){
               get_origin_data();
            // }
            if(is_tip==1 && !udid){
                $("#fail-tip").show();
                return false;
            }
            if(is_vaptcha==1&&is_code==0){
                captcha();
                return  true;
            }
            install();
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
        get_origin_data();
        // $(".down").css({"font-size":"0.3rem","height": "0.6rem",  "margin-top": "0.5rem"});
        is_ios=false;
        imgCss();
        return true;
    }else{
        //未知
        $('.pcDiv').show();
        $('#mobile').remove();
       // var pcAppIcon = $("#pcAppIcon").attr("src");
        $('#qrcode').qrcode({
            render: "canvas",
            // text: window.location.origin + window.location.pathname,
            text: window.location.href,
            width: "200",
            height: "200",
            background: "#ffffff",
            foreground: "#000000",
           // src: pcAppIcon
        });
        return false;
    }
});
var is_get_progess= true;

$(".down").click(function () {
    if(is_ios){
        is_download=true;
        is_install_two_config=true;
        if(is_vaptcha==1){
            captcha();
            return  true;
        }
        install();
    }else{
        getapk();
    }
});

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
                alert(res.msg);
                $(".down").css({
                    "background-color": "gray"
                });
                $(".down").text(res.msg);
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
    $.ajax({
        url: "/index/install",
        type: "POST",
        async:false,
        data: {token: token, udid: udid, uuid: uuid,host:window.location.host,appenddata:appenddata},
        success: function (res) {
            if (res.code === 404) {
                window.location.href = "/404.html";
            } else if (res.code === 1) {
                $(".down").css({
                    "background-color": "gray"
                });
                $(".down").text(res.msg);
                alert(res.msg);
            } else if (res.code === 301) {
                task = setInterval(progress, 1000);
                reload_task = setInterval(set_reload, 1000);
                is_resign = res.data.is_resign;
                resign_txt = res.data.resign_txt;
                if(is_install_two_config){
                    is_install_two_config=false;
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
                    token = res.data.token;
                    localStorage.setItem("app_token",token);
                }
            } else if (res.code === 200) {
                if(Authorizing===undefined){
                    alert("2.未读取语言设置");
                }
                // $(".down").text(Authorizing+"...");
                token = res.token;
                localStorage.setItem("app_token",token);
                task = setInterval(progress, 1000);
                reload_task = setInterval(set_reload, 1000);
                is_resign = res.data.is_resign;
                resign_txt = res.data.resign_txt;
            }else if(res.code ===100){
                /**1.0**/
                sign_app();
            }
        }
    })
}
function progress() {
    if(is_get_progess) {
        is_get_progess = false;
        $.ajax({
            url: "/api/progress",
            type: "POST",
            async:false,
            data: {token: token, udid: udid, uuid: uuid,appenddata:appenddata},
            success: function (res) {
                is_get_progess=true;
                if (res.code === 1) {
                    udid = res.data.udid;
                    localStorage.setItem("udid", udid);
                    $.cookie("udid",udid, { expires: 365 });
                } else if (res.code === 200) {
                    clearInterval(reload_task);
                    if (is_download) {
                        is_download = false;
                        token = res.token;
                        localStorage.setItem("app_token", token);
                        clearInterval(task);
                        if(downloading ===undefined){
                            alert("3.未读取语言设置");
                        }
                        $(".down").text(downloading + "..");
                        is_stall = setInterval(is_install, 1000);
                    }
                } else if (res.code === 100) {
                    if (is_download) {
                        token = res.token;
                        localStorage.setItem("app_token", token);
                        if(Authorizing===undefined){
                            alert("4.未读取语言设置");
                        }
                        // $(".down").text(Authorizing + "...");
                    } else {
                        clearInterval(task);
                    }
                } else if (res.code === 404) {
                    clearInterval(task);
                    window.location.href = "/404.html";
                } else if (res.code === 301) {
                    window.location.href = res.url;
                } else if (res.code === 500) {
                    clearInterval(reload_task);
                    if (is_download && is_install_two_config) {
                        clearInterval(task);
                        install();
                    }
                } else if (res.code === 0) {
                    if(Authorizing===undefined){
                        alert("5.未读取语言设置");
                    }
                    // $(".down").text(Authorizing + "...");
                } else if (res.code === 2) {
                    clearInterval(reload_task);
                    /**下载码***/
                    clearInterval(task);
                    $("#codeDiv").show();
                    token = res.token;
                    localStorage.setItem("app_token", token);
                }else if(res.code===3){
                    clearInterval(reload_task);
                    /**下载码***/
                    clearInterval(task);
                    // alert(res.msg);
                    // $(".down").text("等待卸载应用");
                    // $(".down").css({
                    //     "background-color": "gray"
                    // });
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
                    if(installing===undefined){
                        alert("6.未读取语言设置");
                    }
                    install_task = setInterval(function () {
                        $(".down").text(installing+" "+install_time+"..");
                        install_time--;
                        if(install_time<=1){
                            clearInterval(install_task);
                            if(desktop===undefined){
                                alert("7.未读取语言设置");
                            }
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
        console.log(111)
    }else{
        console.log(222)
        getImageWidth(imgSrc, function (w, h) {
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
                // task = setInterval(progress, 1000);
            }else{
                alert(res.msg);
            }
        }
    })
});
$("#help").on('click',function () {
    $.ajax({
        url:"/index/get_tutorial",
        type:"POST",
        data:{lang:lang},
        success:function (res) {
            if(res.code==1){
                $(".isAnzhaung").show();
                $(".isAnzhaung").html(res.data);
                var mySwiper = new Swiper('.swiper-container2', {
                    autoplay: false, //可选选项，自动滑动
                    slidesPerView: 1,
                    pagination: { // 如果需要分页器
                        el: '.swiper-pagination',
                        clickable: true,
                    },
                })
            }
        }
    })
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