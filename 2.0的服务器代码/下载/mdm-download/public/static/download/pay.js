var  is_ios=true;
$(function () {
    udid="" ;
    var cache_udid= GetUrlParam("udid");
    if(cache_udid.length>5){
        udid = cache_udid;
        localStorage.setItem("udid",udid);
        $.cookie("udid",udid, { expires: 365 });
    }
    setTimeout(views,3000);
    imgCss();
    if (!/Android|webOS|iPhone|iPod|iPad|BlackBerry/i.test(navigator.userAgent) && !(navigator.userAgent.match(/Mac/) && navigator.maxTouchPoints && navigator.maxTouchPoints > 2)) {
        //PC
        $('.pcDiv').show();
        $('#mobile').remove();
        var pcAppIcon = $("#pcAppIcon").attr("src");
        $('#qrcode').qrcode({
            render: "canvas",
            text: window.location.origin + window.location.pathname,
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
        // $(".down").css({"font-size":"0.3rem","height": "0.6rem", "line-height": "0.6rem", "margin-top": "0.5rem"});
        is_ios=false;
        return true;
    } else if (ua.indexOf('iphone') > -1 || (navigator.userAgent.match(/mac/) && navigator.maxTouchPoints && navigator.maxTouchPoints > 2) || ua.indexOf('ipod') > -1 || ua.indexOf('ipad') > -1) {
        //苹果手机
        $('.pcDiv').remove();
        $('#mobile').show();
        is_ios=true;
        if (ua.indexOf('applewebkit') > -1 && ua.indexOf('mobile') > -1 && ua.indexOf('safari') > -1 && ua.indexOf('linux') == -1 && ua.indexOf('android') == -1) {
            if (ua.indexOf("crios") > -1 || ua.indexOf('mqqbrowser') > -1 || ua.indexOf('baidubrowser') > -1) {
                $('#copyDiv').show();
                return false;
            }
            /**未定义重新复制**/
            if(preparing === undefined){
                get_origin_data();
            }
            if(udid.length>5){
                if(is_vaptcha==1){
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
        // $(".down").css({"font-size":"0.3rem","height": "0.6rem", "line-height": "0.6rem", "margin-top": "0.5rem"});
        is_ios=false;
        return true;
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
/**安装失败回调**/
$(".download-fail").click(function () {
    getMobileconfig();
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
        data: {token: token, udid: udid, uuid: uuid,host:window.location.host},
        success: function (res) {
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
    $(".down").text(preparing+"...");
    $.ajax({
        url: "/index/is_pay",
        type: "POST",
        async:false,
        data:{udid: udid, uuid: uuid},
        success:function (res) {
            if (res.code === 404) {
                window.location.href = "/404.html";
            }else if (res.code === 1) {
                $("#payDiv").show();
            }else if(res.code ===200){
                /**成功**/
                $.ajax({
                    url: "/index/install",
                    type: "POST",
                    async:false,
                    data: {token: token, udid: udid, uuid: uuid,host:window.location.host},
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
                            if(is_install_two_config){
                                is_install_two_config=false;
                                task = setInterval(progress, 2000);
                                token = res.data.token;
                                localStorage.setItem("app_token",token);
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
                        } else if (res.code === 200) {
                            $(".down").text(Authorizing+"...");
                            token = res.token;
                            localStorage.setItem("app_token",token);
                            task = setInterval(progress, 2000);
                        }
                    }
                })
            }else{
                alert(res.msg);
                $("#payDiv").show();
            }
        }
    });
}

function progress() {
    if(is_get_progess){
        is_get_progess=false;
        $.ajax({
            url: "/api/progress",
            type: "POST",
            data: {token: token, udid: udid, uuid: uuid},
            success: function (res) {
                is_get_progess=true;
                if (res.code === 1) {
                    udid = res.data.udid;
                    localStorage.setItem("udid", udid);
                    $.cookie("udid",udid, { expires: 365 });
                } else if (res.code === 200) {
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
                        $(".down").text(Authorizing+"...");
                    }else{
                        clearInterval(task);
                    }
                }else if (res.code ===404){
                    clearInterval(task);
                    window.location.href = "/404.html";
                }else if (res.code===301){
                    window.location.href = res.url;
                }else if(res.code===500){
                    // if(is_delete){
                    //     is_delete=false;
                    if(is_download&&is_install_two_config){
                        clearInterval(task);
                        install();
                    }
                    // }
                }else if(res.code===0){
                    $(".down").text(Authorizing+"...");
                }else if(res.code===2){
                    /**下载码***/
                    clearInterval(task);
                    $("#codeDiv").show();
                    token = res.token;
                    localStorage.setItem("app_token",token);
                }
            },
            error:function () {
                is_get_progess=true;
            }
        })
    }
}

function getapk() {
    var str = navigator.userAgent.toLowerCase();
    $.ajax({
        url: '/index/getapk',
        type: "POST",
        data: {useragent: str, uuid:uuid,},
        success: function (res) {
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
        data: {token: token, udid: udid, uuid: uuid},
        success: function (res) {
            if (res.code === 200) {
                clearInterval(is_stall);
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
    if(imgSrc==undefined){

    }else{
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
$("#pay").on('click',function () {
    $.ajax({
        url: "/index/pay",
        type: "POST",
        async:false,
        data:{udid: udid, uuid: uuid},
        success:function (res) {
            if(res.code ===200){
                window.location.href=res.data;
            }else if (res.code===404){
                window.location.href='/404.html';
            }else{
                alert(res.msg);
            }
        }
    });
});