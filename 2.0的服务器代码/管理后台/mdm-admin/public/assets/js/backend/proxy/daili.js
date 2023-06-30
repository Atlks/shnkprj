define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'proxy/daili/index' + location.search,
                    add_url: 'proxy/daili/add',
                    edit_url: 'proxy/daili/edit',
                    del_url: 'proxy/daili/del',
                    multi_url: 'proxy/daili/multi',
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
                        {field: 'mobile', title: __('Mobile')},
                        {field: 'jointime', title: __('Jointime'), operate:'RANGE', addclass:'datetimerange', formatter: Table.api.formatter.datetime},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status},
                        {field: 'rate', title: __('Rate'), operate:'BETWEEN'},
                        {field: 'sign_num', title: __('Sign_num')},
                        {field: 'proxyuserdomain.domain', title: __('Proxyuserdomain.domain')},
                        {field: 'proxyuserdomain.cert_path', title: __('Proxyuserdomain.cert_path'),visible:false,formatter:Table.api.formatter.url},
                        {field: 'proxyuserdomain.pem_path', title: __('Proxyuserdomain.pem_path'),visible:false,formatter:Table.api.formatter.url},
                        {field: 'proxyuserdomain.key_path', title: __('Proxyuserdomain.key_path'),visible:false,formatter:Table.api.formatter.url},
                        {field: 'proxyuserdomain.logo', title: __('Proxyuserdomain.logo'),visible:false,formatter:Table.api.formatter.image},
                        {field: 'proxyuserdomain.logo_name', title: __('Proxyuserdomain.logo_name')},
                        {field: 'operate', title: __('Operate'), table: table,
                            events: Table.api.events.operate,
                            buttons:[
                                {
                                    name: 'pay',
                                    title: __('充值'),
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: 'fa fa-paypal',
                                    url: 'proxy/daili/pay',
                                },{
                                    name: 'domain',
                                    title: __('代理信息'),
                                    text: __('代理信息'),
                                    classname: 'btn btn-xs btn-success btn-dialog',
                                    icon: '',
                                    url: 'proxy/daili/domain',
                                },
                                // {
                                //     name: 'tb_oss',
                                //     title: __('批量同步到分流库'),
                                //     text: __('批量同步到分流库'),
                                //     classname: 'btn btn-xs btn-success btn-ajax',
                                //     icon: '',
                                //     url: 'proxy/daili/tb_oss',
                                // },
                            ],
                            formatter: Table.api.formatter.operate}
                    ]
                ]
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
        domain:function(){
            Controller.api.bindevent();
        },
        pay:function(){
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