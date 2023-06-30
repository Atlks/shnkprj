define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'proxy/proxy_user_money_notice/index' + location.search,
                    add_url: 'proxy/proxy_user_money_notice/add',
                    edit_url: 'proxy/proxy_user_money_notice/edit',
                    del_url: 'proxy/proxy_user_money_notice/del',
                    multi_url: 'proxy/proxy_user_money_notice/multi',
                    import_url: 'proxy/proxy_user_money_notice/import',
                    table: 'proxy_user_money_notice',
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
                        {field: 'proxyuser.username', title: __('Proxyuser.username'),formatter:Table.api.formatter.search},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'sign_num', title: __('Sign_num')},
                        {field: 'status', title: __('Status'),searchList:{0:'关闭提醒',1:'正常',},sortable:true,formatter: Table.api.formatter.toggle},
                        {field: 'chat_id', title: __('飞机ID'),operate: false},
                        {field: 'times', title: __('Times')},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange'},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate},
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
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});