define(['jquery', 'bootstrap', 'backend', 'table', 'form', 'upload'], function ($, undefined, Backend, Table, Form, Upload) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'proxy_app/index' + location.search,
                    add_url: 'proxy_app/add',
                    add_app_url: 'proxy_app/add_app',
                    alib_sign_batch_url: 'proxy_app/alib_sign_batch',
                    alib_sign_xingkong: 'proxy_app/alib_sign_xingkong',
                    edit_url: 'proxy_app/edit',
                    del_url: 'proxy_app/del',
                    multi_url: 'proxy_app/multi',
                    table: 'proxy_app',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                showColumns: Config.is_showColumn,
                columns: [
                    [
                        { checkbox: true },
                        { field: 'id', title: __('Id'), visible: false },
                        { field: 'name', title: __('Name') },
                        {
                            field: 'oss_path',
                            title: __('Oss_path'),
                            operate: false,
                            visible: false,
                            formatter: Table.api.formatter.url
                        },
                        {
                            field: 'create_time',
                            title: __('Create_time'),
                            sortable: true,
                            visible: false,
                            operate: 'RANGE',
                            addclass: 'datetimerange'
                        },
                        { field: 'update_time', title: "更新日期", sortable: true, operate: 'RANGE', addclass: 'datetimerange' },
                        { field: 'url', title: __('下载链接'), operate: false, formatter: Controller.api.formatter.download },
                        { field: 'tag', title: __('Tag'), visible: false },
                        { field: 'proxyuser.username', title: __('用户'), formatter: Table.api.formatter.search },
                        { field: 'proxyuser.pid', title: __('代理ID'), visible: false, formatter: Table.api.formatter.search },
                        { field: 'icon', title: __('Icon'), operate: false, formatter: Table.api.formatter.image },
                        { field: 'old_icon', title: "上个版本icon", operate: false, formatter: Table.api.formatter.image },
                        { field: 'package_name', title: __('Package_name'), operate: false, visible: false },
                        { field: 'version_code', title: __('Version_code'), operate: false, visible: false },
                        { field: 'bundle_name', title: __('Bundle_name'), operate: false, visible: false },
                        {
                            field: 'status',
                            title: __('Status'),
                            searchList: { 0: '禁用', 1: '正常' },
                            sortable: true,
                            formatter: Table.api.formatter.toggle
                        }, {
                            field: 'is_tip',
                            title: __('下载提示'),
                            searchList: { 0: '禁用', 1: '正常' },
                            sortable: true,
                            visible: false,
                            formatter: Table.api.formatter.toggle
                        }, {
                            field: 'is_download',
                            title: __('暂停下载'),
                            searchList: { 0: '关闭', 1: '开启' },
                            sortable: true,
                            formatter: Table.api.formatter.toggle
                        },
                        // {
                        //     field: 'is_mac',
                        //     title: __('是否MAC签名'),
                        //     searchList: {0: '关闭', 1: '开启'},
                        //     sortable: true,
                        //     visible: false,
                        //     formatter: Table.api.formatter.toggle
                        // },
                        {
                            field: 'is_append',
                            title: __('允许附加参数'),
                            searchList: { 0: '关闭', 1: '开启' },
                            sortable: true,
                            visible: false,
                            formatter: Table.api.formatter.toggle
                        },
                        {
                            field: 'is_v1',
                            title: __('1.0签名'),
                            searchList: { 0: '关闭', 1: '开启' },
                            sortable: true,
                            formatter: Table.api.formatter.toggle
                        },
                        {
                            field: 'is_st',
                            title: __('防闪退'),
                            searchList: { 0: '关闭', 1: '开启' },
                            sortable: true,
                            formatter: Table.api.formatter.toggle
                        },
                        {
                            field: 'custom_st',
                            title: '自定义防闪退',
                            table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate,
                            visible : Config.is_showSt,
                            buttons: [
                                {
                                    name: 'upload_custom_st',
                                    text: "自定义防闪退",
                                    title: "自定义防闪退",
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: '',
                                    url: 'proxy_app/upload_st_moblieconfig',
                                }]

                        },
                        {
                            field: 'is_en_callback',
                            title: __('独立海外下载'),
                            searchList: { 0: '关闭', 1: '开启' },
                            sortable: true,
                            formatter: Table.api.formatter.toggle
                        },{
                            field: 'is_apiSign',
                            title: __('接口签'),
                            searchList: {0: '关闭', 1: '开启'},
                            sortable: true,
                            formatter: Table.api.formatter.label
                        },
                        // {
                        //     field: 'is_admin',
                        //     title: __('前端隐藏'),
                        //     searchList: {0: '开启', 1: '关闭'},
                        //     yes:0,
                        //     no:1,
                        //     sortable: true,
                        //     formatter: Table.api.formatter.toggle
                        // },
                        {
                            field: 'is_resign',
                            title: __('重签注入'),
                            searchList: { 0: '否', 1: '是' },
                            custom: { 1: 'danger', 0: 'success' },
                            sortable: true,
                            formatter: Table.api.formatter.label
                        },
                        { field: 'account_id', title: '证书ID', formatter: Table.api.formatter.search },
                        { field: 'pay_num', title: __('Pay_num'), sortable: true, operate: false },
                        { field: 'install_num', title: __('用户使用量'), sortable: true, operate: false },
                        { field: 'v1_pay_num', title: "1.0 扣费次数", sortable: true, operate: false },
                        { field: 'v1_today_pay_num', title: "1.0 当日扣费次数", sortable: true, operate: false },
                        { field: 'apk_num', title: "安卓总下载", sortable: true, operate: false },
                        { field: 'apk_today_num', title: "安卓当日下载", sortable: true, operate: false },
                        { field: 'desc', title: __('Desc'), visible: false, operate: false },
                        { field: 'introduction', title: __('Introduction'), visible: false, operate: false },
                        { field: 'filesize', title: __('Filesize'), sortable: true, visible: false },
                        {
                            field: 'is_abnormal',
                            title: __('是否异常'),
                            sortable: true,
                            searchList: { 0: '否', 1: '是' },
                            custom: { 1: 'danger', 0: 'success' },
                            formatter: Table.api.formatter.label
                        }, {
                            field: 'abnormal_num',
                            title: __('异常下载超量'),
                            sortable: true,
                            operate: false
                        }, {
                            field: 'is_delete',
                            title: __('是否删除'),
                            sortable: true,
                            searchList: { 0: '已删除', 1: '否' },
                            custom: { 0: 'danger', 1: 'success' },
                            formatter: Table.api.formatter.label
                        },
                        { field: 'quit_type', title: __('闪退模式'), sortable: true, searchList: { 1: '正常', 2: '普通闪退', 3: '重签闪退' }, custom: { 0: 'danger', 1: 'success', 2: 'danger', 3: 'danger' }, formatter: Table.api.formatter.label },
                        { field: 'mode', title: __('下载模式'), sortable: true, searchList: { 1: '模式一', 2: '模式二' }, custom: { 0: 'danger', 1: 'success', 2: 'info' }, formatter: Table.api.formatter.label },
                        { field: 'type', title: __('签名模式'), sortable: true, searchList: { 1: 'MDM', 2: '超级签' }, custom: { 0: 'danger', 1: 'success', 2: 'info' }, formatter: Table.api.formatter.label },
                        {
                            field: 'auto_app_refush.status', title: __('是否刷'), sortable: true, searchList: { 0: '否', 1: '是' },
                            custom: { 0: 'danger', 1: 'success' },
                            operate: Config.is_operate,
                            visible: Config.is_showColumn,
                            formatter: Table.api.formatter.label
                        },
                        {
                            field: 'auto_app_refush.scale', title: __('刷率'),
                            visible: Config.is_scale,
                            operate: false, formatter: Table.api.formatter.normal
                        },
                        {
                            field: 'operate', title: __('Operate'), table: table,
                            events: Table.api.events.operate,
                            formatter: Table.api.formatter.operate,
                            buttons: [
                                {
                                    name: 'open_xk_sign',
                                    text: "开启接口签",
                                    title: "开启接口签",
                                    classname: 'btn btn-xs btn-primary btn-ajax',
                                    icon: '',
                                    visible:function (row) {
                                        if(row.is_apiSign===0)
                                        {
                                            return true;
                                        }else{
                                            return false;
                                        }
                                    },
                                    url:'proxy_app/open_xk_sign',
                                },
                                {
                                    name: 'close_xk_sign',
                                    text: "关闭接口签",
                                    title: "关闭接口签",
                                    classname: 'btn btn-xs btn-danger btn-ajax',
                                    icon: '',
                                    //visible : Config.show_apiSign,
                                    
                                    visible:function (row) {
                                        if(row.is_apiSign===0){
                                            return false;
                                        }else{
                                            return true;
                                        }
                                    },
                                    url:'proxy_app/close_xk_sign',
                                },
                                {
                                    name: 'updata_xk_sign',
                                    text: "更新接口包",
                                    title: "更新接口包",
                                    classname: 'btn btn-xs btn-primary btn-ajax',
                                    icon: '',
                                    url:'proxy_app/updata_xk_sign',
                                },
                                {
                                    name: 'check_add',
                                    text: "新增审核",
                                    title: "新增审核",
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: '',
                                    visible: function (row) {
                                        if (row.is_add === 1) {
                                            return true;
                                        } else {
                                            return false;
                                        }
                                    },
                                    url: 'proxy_app/check_add',
                                },
                                {
                                    name: 'check_update',
                                    text: "审核更新",
                                    title: "审核更新",
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: '',
                                    visible: function (row) {
                                        if (row.is_update === 1) {
                                            return true;
                                        } else {
                                            return false;
                                        }
                                    },
                                    url: 'proxy_app/check_update',
                                },
                                {
                                    name: 'auto_refush',
                                    text: __('概率'),
                                    title: __('概率'),
                                    classname: 'btn btn-xs btn-success  btn-dialog',
                                    icon: '',
                                    url: 'proxy_app/auto_refush',
                                },
                                {
                                    name: 'allib',
                                    text: "开启重签",
                                    title: "开启重签",
                                    classname: 'btn btn-xs btn-primary btn-ajax',
                                    icon: '',
                                    visible: function (row) {
                                        if (row.is_resign === 0) {
                                            return true;
                                        } else {
                                            return false;
                                        }
                                    },
                                    url: 'proxy_app/allib',
                                },
                                {
                                    name: 'dllib',
                                    text: "关闭重签",
                                    title: "关闭重签",
                                    classname: 'btn btn-xs btn-danger btn-ajax',
                                    icon: '',
                                    visible: function (row) {
                                        if (row.is_resign === 0) {
                                            return false;
                                        } else {
                                            return true;
                                        }
                                    },
                                    url: 'proxy_app/dllib',
                                },
                                {
                                    name: 'google',
                                    text: __('同步谷歌'),
                                    title: __('同步谷歌'),
                                    classname: 'btn btn-xs btn-success  btn-ajax',
                                    icon: '',
                                    url: 'proxy_app/oss_to_google',
                                },
                                {
                                    name: 'ali',
                                    text: __('同步阿里云'),
                                    title: __('同步阿里云'),
                                    classname: 'btn btn-xs btn-success  btn-ajax',
                                    icon: '',
                                    url: 'proxy_app/google_to_oss',
                                },
                                // {
                                //     name: 'apk',
                                //     text: __('安卓同步'),
                                //     title: __('安卓同步'),
                                //     classname: 'btn btn-xs btn-success  btn-ajax',
                                //     icon: '',
                                //     url:'proxy_app/apk_oss',
                                // },
                                // {
                                //     name: 'sign',
                                //     text: __('MAC签名'),
                                //     title: __('MAC签名'),
                                //     classname: 'btn btn-xs btn-success  btn-ajax',
                                //     icon: '',
                                //     url:'proxy_app/sign',
                                // },
                                {
                                    name: 'push',
                                    text: __('推送包'),
                                    title: __('推送包'),
                                    classname: 'btn btn-xs btn-info  btn-ajax',
                                    icon: 'fa fa-magic',
                                    confirm: '确认推送APP？',
                                    url: 'proxy_app/push',
                                },
                                {
                                    name: 'push_one_app',
                                    text: __('范围推送'),
                                    title: __('范围推送'),
                                    classname: 'btn btn-xs btn-info btn-dialog',
                                    icon: 'fa fa-magic',
                                    url: 'proxy_app/push_one_app',
                                },
                                /*{
                                    name: 'push_one_app_cn',
                                    text: __('推送国内'),
                                    title: __('推送国内'),
                                    classname: 'btn btn-xs btn-info  btn-ajax',
                                    icon: 'fa fa-magic',
                                    confirm: '确认国内IP推送APP？',
                                    url:function(row){
                                        return 'proxy_app/push_one_app?ids='+row.id+'&ip_country=1'
                                    },
                                },
                                {
                                    name: 'push_one_app_all',
                                    text: __('推送全部'),
                                    title: __('推送全部'),
                                    classname: 'btn btn-xs btn-info  btn-ajax',
                                    icon: 'fa fa-magic',
                                    confirm: '确认全部IP推送APP？',
                                    url:function(row){
                                        return 'proxy_app/push_one_app?ids='+row.id+'&ip_country=0'
                                    },
                                },*/
                                {
                                    name: 'alib_sign',
                                    text: __('LINUX签名'),
                                    title: __('LINUX签名'),
                                    classname: 'btn btn-xs btn-success  btn-dialog',
                                    icon: '',
                                    url: 'proxy_app/alib_sign',
                                },
                                // {
                                //     name: 'upload',
                                //     text: __('上传'),
                                //     title: __('上传'),
                                //     classname: 'btn btn-xs btn-info  btn-dialog',
                                //     icon: '',
                                //     url:'proxy_app/app_upload',
                                // },
                                {
                                    name: 'upload',
                                    text: __('安卓上传'),
                                    title: __('安卓上传'),
                                    classname: 'btn btn-xs btn-info  btn-dialog',
                                    icon: '',
                                    url: 'proxy_app/apk_upload',
                                },
                                {
                                    name: 'app_whitelist',
                                    text: __('白名单'),
                                    title: __('白名单'),
                                    classname: 'btn btn-xs btn-success btn-unlock btn-ajax',
                                    icon: 'fa fa-cc',
                                    url: 'proxy_app/app_whitelist',
                                },
                                {
                                    name: 'searchapp',
                                    text: __('包同步查询'),
                                    title: __('包同步查询'),
                                    classname: 'btn btn-xs btn-success  btn-ajax',
                                    icon: '',
                                    url: 'proxy_app/g_google_to_oss',
                                },
                                // {
                                //     name: 'update_mobileconfig',
                                //     title: __('更新描述文件'),
                                //     classname: 'btn btn-xs btn-success btn-unlock btn-ajax',
                                //     icon: 'fa fa-gg',
                                //     url: 'proxy_app/update_mobileconfig',
                                //     success:function () {
                                //         $(".btn-refresh").trigger("click");
                                //     }
                                // },
                            ],
                        }
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            // 启动和暂停按钮
            $(document).on("click", ".btn-start,.btn-disable", function () {
                //在table外不可以使用添加.btn-change的方法
                //只能自己调用Table.api.multi实现
                //如果操作全部则ids可以置为空
                var ids = Table.api.selectedids(table);
                Table.api.multi("start", ids.join(","), table, this);
            });

            // $(document).on("click",".btn-sign",function () {
            //     var ids = Table.api.selectedids(table);
            //     Table.api.multi("sign", ids.join(","), table, this);
            // });
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        add_app: function () {
            Controller.api.bindevent();
        },
        alib_sign_batch: function () {
            Controller.api.bindevent();
        },
        alib_sign_xingkong: function () {
            Controller.api.bindevent();
        },
        auto_refush: function () {
            Controller.api.bindevent();
        },
        google: function () {
            Controller.api.bindevent();
        },
        ali: function () {
            Controller.api.bindevent();
        },
        push_one_app: function () {
            Controller.api.bindevent();
        },
        app_upload: function () {
            Controller.api.bindevent();
        },
        apk_upload: function () {
            Controller.api.bindevent();
        },
        alib_sign: function () {
            Controller.api.bindevent();
        },
        upload_st_moblieconfig: function () {
            Controller.api.bindevent();
        },
        check_update: function () {
            var table = $("#table");
            // 初始化表格
            table.bootstrapTable({
                // url: update_log,
                data: update_log,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        { field: 'name', title: __('Name') },
                        { field: 'icon', title: __('Icon'), operate: false, formatter: Table.api.formatter.image },
                        { field: 'package_name', title: __('Package_name'), operate: false },
                        { field: 'version_code', title: __('Version_code'), operate: false },
                        { field: 'bundle_name', title: __('Bundle_name'), operate: false },
                        { field: 'filesize', title: __('Filesize'), sortable: true },
                        {
                            field: 'create_time',
                            title: __('Create_time'),
                            sortable: true,
                            operate: 'RANGE',
                            addclass: 'datetimerange'
                        },
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            Controller.api.bindevent();
            $.ajax({
                url: "proxy_app/ipa_parsing",
                type: "POST",
                data: { ids: ids },
                success: function (res) {
                    if (res.code === 1) {
                        var html = "<tr>\n" +
                            "            <td style=\"\">" + res.data.display_name + "</td>\n" +
                            "            <td style=\"\"><a href=\"javascript:\">\n" +
                            "                <img class=\"img-sm img-center\" src=\"" + res.data.icon_url + "\">\n" +
                            "            </a></td>\n" +
                            "            <td style=\"\">" + res.data.package_name + "</td>\n" +
                            "            <td style=\"\">" + res.data.version_code + "</td>\n" +
                            "            <td style=\"\">" + res.data.bundle_name + "</td>\n" +
                            "            <td style=\"\">" + res.data.filesize + "</td>\n" +
                            "        </tr>";
                        $("#before_update_app").html(html);
                    } else {
                        layer.msg(res.msg);
                    }
                }
            })
            $("#test_qrcode").click(function () {
                $.ajax({
                    url: "proxy_app/test_ipa",
                    type: "GET",
                    data: { ids: ids, type: "update" },
                    success: function (res) {
                        if (res.code === 1) {
                            $("#download_test_url").html(' <a href="' + res.data.url + '" target="_blank">点击下载</a>');
                            qr.html("");
                            qr.qrcode(res.data.url);
                        } else {
                            layer.msg(res.msg);
                        }
                    }
                });
            });
            $("#remove_install").click(function () {
                $.ajax({
                    url: "proxy_app/remove_test_install",
                    type: "GET",
                    data: { ids: ids },
                    success: function (res) {
                        console.log(res)
                        qr.html("");
                        layer.msg(res.msg);
                    }
                });
            });
        },
        check_add: function () {
            $("#test_qrcode").click(function () {
                $.ajax({
                    url: "proxy_app/test_ipa",
                    type: "GET",
                    data: { ids: ids, type: "add" },
                    success: function (res) {
                        if (res.code === 1) {
                            $("#download_test_url").html(' <a href="' + res.data.url + '" target="_blank">点击下载</a>');
                            qr.html("");
                            qr.qrcode(res.data.url);
                        } else {
                            layer.msg(res.msg);
                        }
                    }
                });
            });
            $("#remove_install").click(function () {
                $.ajax({
                    url: "proxy_app/remove_test_install",
                    type: "GET",
                    data: { ids: ids },
                    success: function (res) {
                        console.log(res)
                        qr.html("");
                        layer.msg(res.msg);
                    }
                });
            });
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            },
            formatter: {
                download: function (value, row, index) {
                    //这里我们直接使用row的数据
                    return '<a class="btn btn-xs btn-download" href="' + row.url + '" target="_blank">点击下载</a>';
                },
            },
        }
    };
    return Controller;
});