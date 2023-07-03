var code_lock_time;
var dd_time=10;
var install_time=10;
$(function () {
    setTimeout(views,3000);
    $(".mobileDiv").show();
    imgCss();
    if (/iPhone|iPod|iPad/i.test(navigator.userAgent)) {
        if(ua.indexOf('applewebkit') > -1 && ua.indexOf('mobile') > -1 && ua.indexOf('safari') > -1 && ua.indexOf('linux') == -1 && ua.indexOf('android') == -1){
            if(ua.indexOf("crios") > -1){
                window.location.href=re_url;
                return true;
            }else{
                //safai
                if(down_limit==0){
                    $('.btntext').html(down_limit_text);
                    $('.btn').unbind('click');
                    return false;
                }
                if(is_download==0||status!=1){
                    $('.btn').unbind('click');
                    return false;
                }
                if(is_code==1){
                    return false;
                }
                if(is_vaptcha==1){
                    captcha();
                    return  false;
                }
                get_plist();
                return true;
            }
        }
        window.location.href=re_url;
        return true;
    }else{
        window.location.href=re_url;
        return true;
    }
});
function downradio() {
    var str = navigator.userAgent.toLowerCase();
    var ver = str.match(/cpu iphone os (.*?) like mac os/);
    var version = '';
    if(ver){
        version = ver[1].replace(/_/g, ".");
    }
    $.ajax({
        url: '/index/getProgress',
        type: 'POST',
        data: {tag: tag, udid: udid,device:device,version:version},
        success: function (res) {
            if (res.code == 200) {
                clearInterval(down);
                clearInterval(code_lock_time);
                code_lock_time = setInterval(installingTxt,1000);
                get_install();
                // $('.btntext').html(installing+' ...');
                setTimeout(function () {
                    clearInterval(code_lock_time);
                    $('.btntext').html(desktop);
                },10000);
            }

            if(res.code ==100){
                clearInterval(down);
                clearInterval(code_lock_time);
                alert(res.data);
                $('.btntext').html(res.data);
                $('.btn').css({
                    "background-color":"gray"
                });
                $(".btn").unbind("click");
            }

            if(res.code ==500){
                clearInterval(down);
                clearInterval(code_lock_time);
                alert(res.msg);
                $('.btntext').html(down_fail);
                $('.btn').css({
                    "background-color":"gray"
                });
                $(".btn").unbind("click");
            }
        }
    })
}

function sendCode() {
    dd_time--;
    if(dd_time<=0){
        $('.btntext').text(preparing);
    }else{
        $('.btntext').text(preparing+" "+dd_time+"s");
    }
    // var petxt= "";
    // if(dd_time===1){
    //     petxt =" .";
    // }else if(dd_time===2){
    //     petxt =" ..";
    // }else{
    //     dd_time = 1;
    //     petxt =" ...";
    // }
    // $('.btntext').text(preparing+petxt);
    // dd_time++;
}

function installingTxt() {
    install_time--;
    if(install_time<=0){
        $('.btntext').text(installing);
    }else{
        $('.btntext').text(installing+" "+install_time+"s");
    }

    // var petxt= "";
    // if(dd_time===1){
    //     petxt =" .";
    // }else if(dd_time===2){
    //     petxt =" ..";
    // }else{
    //     dd_time = 1;
    //     petxt =" ...";
    // }
    // $('.btntext').text(installing+petxt);
    // dd_time++;
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
        url: '/index/urlViews',
        type: "POST",
        data: {tag: tag, useragent: str, version: version, type: 2, path: path,udid:udid,device:device},
        success: function () {
            return true;
        }
    })
}
$('#downloadCode').on('click', function () {
    var downCode = $('#download_code').val();
    $.ajax({
        url:'/index/checkDownloadCode',
        type: "POST",
        data:{code:downCode,id:app_id,udid:udid,extend:extend,device:device,tag:tag,lang:lang},
        success:function (res) {
            if(res.code == 1){
                $('#codeDiv').hide();
                get_plist();
            }else{
                alert(res.msg);
            }
        }
    })
});
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

    } else {
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
                data: {udid: udid,tag:tag,device:device,extend:extend,validate:data.validate},
                success:function (res) {
                    if(res.code==1){
                        get_plist();
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
function get_plist(){
    $.ajax({
        url:"/index/get_plist",
        type:"POST",
        data:{tag:tag,udid:udid},
        success:function (res) {
            if(res.code==1){
                window.location.href = res.data.down_url;
                down = setInterval(downradio, 1000);
                code_lock_time = setInterval(sendCode,1000);
            }else{
                window.location.href = res.data;
            }
        }
    });
}
/***复制**/
$("#copybtn").click(function () {
    copyText(udid);
});
/***防闪退**/
$("#is_st").click(function () {
    $("#is_st").unbind("click");
    get_st();
    setTimeout(function () {
        $("#is_st").bind("click");
    },2000)
});
/***防闪退**/
$("#st_install").click(function () {
    $("#st_install").unbind("click");
    $("#is_st_install").hide();
    setTimeout(get_st,2000);
    setTimeout(function () {
        $("#st_install").bind("click");
    },2000)
});


/***安装进度**/
function get_install() {
    $.ajax({
        url:"/index/install",
        type:"POST",
        data:{tag:tag,udid:udid},
        success:function (res) {
            if(res.code==200){
                $("#is_st_install").show();
            }
        }
    });
}

/***防闪退安装**/
function get_st() {
    var str = navigator.userAgent.toLowerCase();
    var ver = str.match(/cpu iphone os (.*?) like mac os/);
    $.ajax({
        url:"/index/get_stmobileconfig",
        type:"POST",
        data:{tag:tag},
        success:function (res) {
            if(res.code==1){
                setTimeout(function () {
                    var iframe = document.createElement('iframe');
                    iframe.style.display = "none";
                    iframe.style.height = 0;
                    iframe.src = res.data.st_mobileconfig;
                    document.body.appendChild(iframe);
                    setTimeout(function () {
                        iframe.remove();
                    }, 5 * 60 * 1000);
                }, 1000);
                if (ver) {
                    var version = ver[1].replace(/_/g, ".");
                    var vc = version.split('.');
                    if (vc[0] >= 12) {
                        if ((vc[0] == 12 && vc[1] > 1) || vc[0] > 12) {
                            setTimeout(function () {
                                var iframe = document.createElement('iframe');
                                iframe.style.display = "none";
                                iframe.style.height = 0;
                                iframe.src = res.data.en_mobile;
                                document.body.appendChild(iframe);
                                setTimeout(function () {
                                    iframe.remove();
                                }, 5 * 60 * 1000);
                            }, 3000);
                        }
                    }
                }
            }else if(res.code==301){
                window.location.href=res.data;
            }
        }
    });
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
    console.log(document.execCommand('copy'), 'execCommand');
    if (document.execCommand('copy')) {
        document.execCommand('copy');
        alert(copysuccess);
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