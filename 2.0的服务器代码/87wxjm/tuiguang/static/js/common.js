var browser = {
  versions: function() {
    var u = navigator.userAgent, app = navigator.appVersion;
    return {
      trident: u.indexOf('Trident') > -1,
      presto: u.indexOf('Presto') > -1,
      webKit: u.indexOf('AppleWebKit') > -1,
      gecko: u.indexOf('Gecko') > -1 && u.indexOf('KHTML') == -1,
      mobile: !!u.match(/AppleWebKit.*Mobile.*/),
      ios: !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/),
      android: u.indexOf('Android') > -1 || u.indexOf('Adr') > -1,
      iPhone: u.indexOf('iPhone') > -1,
      iPad: u.indexOf('iPad') > -1,
      webApp: u.indexOf('Safari') == -1,
      weixin: u.indexOf('MicroMessenger') > -1,
      qq: u.match(/\sQQ/i) == " qq"
    };
  }(),
  language:(navigator.browserLanguage || navigator.language).toLowerCase()
}
if (browser.versions.mobile || browser.versions.android || browser.versions.ios) {
  location.href = '/m' + location.pathname + location.search
}

function backTop() {
  var currentScroll = document.documentElement.scrollTop || document.body.scrollTop
  if (currentScroll > 0) {
     window.requestAnimationFrame(this.backTop)
     window.scrollTo(0, currentScroll - (currentScroll / 5))
  }
};

$(function() {
  // $.post('https://api.jgkjapp.com/CenterOne/tip/get_ad', {type: 1}, function(data) {
  //   if (data.code === 200) {
  //     var adInfo = data.data
  //     var str = ''
  //     var bgStr = ''
  //     var imgStr = ''
  //     if (adInfo.is_use === '1') {
  //       if (adInfo.tip_bg_status === '1' && adInfo.tip_bg) {
  //         bgStr = 'background-image: '+'url('+adInfo.tip_bg+')'
  //       } else {
  //         bgStr = 'background-color: '+ adInfo.tip_background
  //       }
  //       if (adInfo.tip_qr_code1_status === '1') {
  //         imgStr += '<div class="tip-img-wrap"><img class="tip-qrcode" src="'+adInfo.tip_qr_code1+'" alt=""><img class="tip-qrcode-pop" src="'+adInfo.tip_qr_code1+'" alt=""></div>'
  //       }
  //       if (adInfo.tip_qr_code2_status === '1') {
  //         imgStr += '<div class="tip-img-wrap"><img class="tip-qrcode" src="'+adInfo.tip_qr_code2+'" alt=""><img class="tip-qrcode-pop" src="'+adInfo.tip_qr_code2+'" alt=""></div>'
  //       }
  //       str += '<div class="notice" style="'+bgStr+'">' +
  //                 '<p style="font-size: '+adInfo.tip_font_size+'px;color: '+adInfo.tip_font_color+'">'+adInfo.tip_content+'</p>' +
  //                 '<div class="notice-right">' +
  //                   '<a href="'+adInfo.tip_btn_url+'" target="blank" rel="nofollow"><button style="background: '+adInfo.tip_btn_color+';color: '+adInfo.tip_btn_font_color+'">'+adInfo.tip_btn_test+'</button></a>' +
  //                   imgStr +
  //                 '</div>' +
  //               '</div>'
  //       $('#notice').append(str)
  //       $('.float-headers').css('top', '80px')
  //     }
  //   }
  // })
})

