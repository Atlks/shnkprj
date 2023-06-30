define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'proxy/user/index' + location.search,
                    add_url: 'proxy/user/add',
                    edit_url: 'proxy/user/edit',
                    del_url: 'proxy/user/del',
                    multi_url: 'proxy/user/multi',
                    table: 'proxy_user',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id')},
                        {field: 'username', title: __('Username')},
                        {field: 'nickname', title: __('Nickname')},
                        {field: 'pid', title: __('代理ID'),formatter:Table.api.formatter.search},
                        {field: 'mobile', title: __('Mobile')},
                        {field: 'salesman', title: '商务',operate: 'LIKE',formatter: Table.api.formatter.search},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status},
                        {field: 'is_white', title: "白名单", searchList: {0: '关闭', 1: '开启'},
                            operate:Config.is_operate,
                            visible:Config.is_showColumn,
                            formatter: Table.api.formatter.toggle},
                        {field: 'is_check', title: "关闭审核", searchList: {0: '否', 1: '是'},
                            operate:Config.is_operate,
                            visible:Config.is_showColumn,
                            formatter: Table.api.formatter.toggle},
                        {field: 'is_check_update', title: "应用更新检测", searchList: {0: '否', 1: '是'},
                            operate:Config.is_operate,
                            visible:Config.is_showColumn,
                            formatter: Table.api.formatter.toggle},
                        {field: 'is_change_url_notice', title: "域名更新提醒", searchList: {0: '否', 1: '是'},
                            operate:Config.is_operate,
                            visible:Config.is_showColumn,
                            formatter: Table.api.formatter.toggle},
                        {field: 'is_second_pay', title: "二次扣费", searchList: {0: '否', 1: '是'},
                            operate:Config.is_operate,
                            visible:Config.is_showColumn,
                            formatter: Table.api.formatter.toggle},
                        // {field: 'download_num', title: __('Download_num'),operate: false},
                        {field: 'rate', title: __('Rate'), operate:false},
                        {field: 'sign_num', title: __('Sign_num'),operate: false},
                        {field: 'v1_num', title: '1.0 次数',operate: false},
                        {field: 'account_id', title: '签名证书ID',operate: false, visible:Config.is_showColumn},
                        // {field: 'private_num', title: __('私有池设备数量'),operate: false},
                        // {field: 'authentication', title: __('实名认证'),sortable:true,searchList:{1: __('Yes'), 0: __('No')},custom:{0:'danger',1:'success'},formatter:Table.api.formatter.label},
                        // {field: 'is_auth', title: __('待审核')},
                        {field: 'operate', title: __('Operate'), table: table,
                            events: Table.api.events.operate, formatter: Table.api.formatter.operate,
                            buttons:[
                                // {
                                //     name: 'toaccess',
                                //     title: __('一键实名'),
                                //     classname: 'btn btn-xs btn-info btn-magic btn-ajax',
                                //     icon: 'fa fa-magic',
                                //     url: 'proxy/user/smCheck',
                                //     success:function () {
                                //         $(".btn-refresh").trigger("click");
                                //     }
                                // },
                                {
                                    name: 'pay',
                                    text: '2.0充值',
                                    title: '2.0充值',
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: 'fa fa-paypal',
                                    url: 'proxy/user/pay',
                                },{
                                    name: 'v1_pay',
                                    text: '1.0充值',
                                    title: '1.0充值',
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: '',
                                    url: 'proxy/user/v1_pay',
                                },{
                                    name: 'account',
                                    text: '证书选择',
                                    title: '证书选择',
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: '',
                                    url: 'proxy/user/select_account',
                                },
                                {
                                    name: 'money_notice',
                                    title: __('余额飞机提醒'),
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: 'fa fa-telegram',
                                    url: 'proxy/user/money_notice',
                                },
                                // {
                                //     name: 'addtabs',
                                //     title: __('审核'),
                                //     classname: 'btn btn-xs btn-warning btn-addtabs',
                                //     icon: 'fa fa-check',
                                //     url: 'proxy/verified/index'
                                // }
                            ],
                        }
                    ]
                ]
            });

            table.on('load-success.bs.table', function (e, data) {
                $("#all-num").text(data.extend.all_num);
            });
            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        money_notice: function () {
            Controller.api.bindevent();
        },
        pay:function(){
            Controller.api.bindevent();
        },
        v1_pay:function(){
            Controller.api.bindevent();
        },
        account:function(){
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});