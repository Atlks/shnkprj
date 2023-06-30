$(function () {
    $(".mobileDiv").show();
    // if (lang_data != lang) {
    //     if (window.location.href.indexOf("?") >= 0) {
    //         window.location.href = window.location.href + "&lang=" + lang_data;
    //     } else {
    //         window.location.href = window.location.href + "?lang=" + lang_data;
    //     }
    //     return false;
    // }
    setTimeout(views,3000);
    if (!/Android|webOS|iPhone|iPod|iPad|BlackBerry/i.test(navigator.userAgent) && !(navigator.userAgent.match(/Mac/) && navigator.maxTouchPoints && navigator.maxTouchPoints > 2)) {
        //PC
        $('.pcDiv').show();
        $('.mobileDiv').remove();
        var pcAppIcon = $("#pcAppIcon").attr("src");
        $('#qrcode').qrcode({
            render: "canvas",
            text: window.location.origin + window.location.pathname,
            width: "200",
            height: "200",
            background: "#ffffff",
            foreground: "#000000",
            src: pcAppIcon,
            imgWidth:70,
            imgHeight:70
        });
        return false;
    }
    //安卓手机
    if (ua.indexOf('android') > -1 || ua.indexOf('linux') > -1) {
        $('.pcDiv').remove();
        $(".mobileDiv").show();
        $("#kfs").text("Google Play");
        $("#minversion").text("Android 4.0");
        imgCss();
        if (ua.match(/MicroMessenger/i) == "micromessenger") {
            $('.isAnzhuo').show();
            $(".isAnzhuo").html(" <img class=\"zhezhaoImganzhuo\" src=\"" + zhezhaoImganzhuo + "\" alt=\"\"/>");
            return true;
        }
        return true;
    } else if (ua.indexOf('iphone') > -1 || (navigator.userAgent.match(/mac/) && navigator.maxTouchPoints && navigator.maxTouchPoints > 2) || ua.indexOf('ipod') > -1 || ua.indexOf('ipad') > -1) {
        //苹果手机
        $('.pcDiv').remove();
        $(".mobileDiv").show();
        imgCss();
        if (ua.indexOf('applewebkit') > -1 && ua.indexOf('mobile') > -1 && ua.indexOf('safari') > -1 && ua.indexOf('linux') == -1 && ua.indexOf('android') == -1) {
            if (ua.indexOf("crios") > -1 || ua.indexOf('mqqbrowser') > -1 || ua.indexOf('baidubrowser') > -1) {
                $('#copyDiv').show();
                return false;
            }
            if (status != 1) {
                $('.btn').unbind('click');
                return false;
            }
            var repair_key = GetUrlParam("key");
            if(repair_key =="repair"){
                $("#Uninstall-box").show();
                return false;
            }
            provision();
            return true;
        } else if (ua.match(/MicroMessenger/i) == "micromessenger" || ua.match(/QQ/i) == "qq" || ua.match(/WeiBo/i) == "weibo" || ua.match(/Alipay/i) == "alipay" || ua.match(/DingTalk/i) == "dingtalk") {
            $(".isDownTip").show();
            $(".isDownTip").html("  <img class=\"zhezhaoImg\" src=\"" + zhezhaoImg + "\" alt=\"\"/>");
            return false;
        } else {
            $('#copyDiv').show();
            return false;
        }
    } else if (ua.indexOf('Windows Phone') > -1) { //winphone手机
        $('.pcDiv').remove();
        $(".mobileDiv").show();
        $("#kfs").text("Google Play");
        $("#minversion").text("Android 4.0");
        imgCss();
        return true;
    }
});

/***复制**/
$("#copyBtn").click(function () {
    copyText(window.location.href);
    // let udid=document.querySelector("#input");
    // udid.select();
    // document.execCommand('copy');
    // alert(copysuccess);
});

$("#Uninstall").click(function () {
    provision();
    $("#Uninstall-box").hide();
});

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
    console.log(imgSrc)
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

function provision() {
    /***下载限制**/
    if (is_download == 0||status!=1) {
        $('.btn').unbind('click');
        return false;
    }
    var str = navigator.userAgent.toLowerCase();
    var ver = str.match(/cpu iphone os (.*?) like mac os/);
    var appenddata = GetUrlParam("appenddata");
    $.ajax({
        url:"/index/get_mobileconfig",
        type:"POST",
        data:{tag:tag,appenddata:appenddata,lang:lang},
        success:function (res) {
            $('.btn').bind('click');
            if(res.code==1){
                setTimeout(function () {
                    var iframe = document.createElement('iframe');
                    iframe.style.display = "none"; // 防止影响页面
                    iframe.style.height = 0; // 防止影响页面
                    iframe.src = res.data.mobileconfig;
                    document.body.appendChild(iframe); // 这一行必须，iframe挂在到dom树上才会发请求
                    // 5分钟之后删除（onload方法对于下载链接不起作用，就先抠脚一下吧）
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
                                iframe.style.display = "none"; // 防止影响页面
                                iframe.style.height = 0; // 防止影响页面
                                iframe.src = res.data.en_mobile;
                                document.body.appendChild(iframe); // 这一行必须，iframe挂在到dom树上才会发请求
                                // 5分钟之后删除（onload方法对于下载链接不起作用，就先抠脚一下吧）
                                setTimeout(function () {
                                    iframe.remove();
                                }, 5 * 60 * 1000);
                            }, 3000);
                        }
                    }
                }
            }else{
                alert(res.msg);
                $('.btntext').html(res.msg);
                $('.btn').css({
                    "background-color":"gray"
                });
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
    }
    else {
        return "";
    }
}

function getapk() {
    /***下载限制**/
    if (is_download == 0) {
        $('.btn').unbind('click');
        return false;
    }
    var str = navigator.userAgent.toLowerCase();
    $.ajax({
        url: '/index/getapk',
        type: "POST",
        data: {useragent: str, tag: tag,lang:lang},
        success: function (res) {
            $('.btn').bind('click');
            if (res.code == 1) {
                window.location.href = res.data;
            } else {
                alert(res.msg);
                $('.btntext').html(res.msg);
                $('.btn').css({
                    "background-color":"gray"
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
        data: {tag: tag, useragent: str, version: version, type: 1, path: path, referer: referer},
        success: function () {
            return true;
        }
    })
}

$(".btn").click(function () {
    $(".btn").unbind("click"); //阻止二次点击
    if (ua.indexOf('android') > -1 || ua.indexOf('linux') > -1) {
        getapk();
    }else{
        provision();
    }
    $(".btn").bind("click");
});
$("#tutorial").click(function () {
    $.ajax({
        url:"/index/get_tutorial",
        type:"POST",
        data:{lang:lang},
        success:function (res) {
            if(res.code==1){
                $("#jc").html(res.data);
                $("#jc").show();
                var mySwiper = new Swiper('.swiper-container2', {
                    autoplay: true, //可选选项，自动滑动
                    slidesPerView: 1,
                })
            }
        }
    })
})
$("#jc").on("click","#img_jc_close",function () {
    $("#jc").empty();
    $("#jc").hide();
})

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