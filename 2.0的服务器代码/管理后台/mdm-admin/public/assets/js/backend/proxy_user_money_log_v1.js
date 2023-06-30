define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'proxy_user_money_log_v1/index' + location.search,
                    // add_url: 'proxy_user_money_log_v1/add',
                    edit_url: 'proxy_user_money_log_v1/edit',
                    // del_url: 'proxy_user_money_log_v1/del',
                    // multi_url: 'proxy_user_money_log_v1/multi',
                    // import_url: 'proxy_user_money_log_v1/import',
                    table: 'proxy_user_money_log_v1',
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
                        {field: 'id', title: __('Id'),operate:false,visible:false},
                        {field: 'user_id', title: __('User_id'),visible:false,formatter:Table.api.formatter.search},
                        {field: 'proxyuser.pid', title: "代理ID", formatter:Table.api.formatter.search},
                        {field: 'proxyuser.username', title: __('Proxyuser.username'), operate: 'LIKE',formatter:Table.api.formatter.search},
                        {field: 'num', title: __('Num'), operate:false},
                        {field: 'before', title: __('Before'), operate:false},
                        {field: 'after', title: __('After'), operate:false},
                        {field: 'type_text', title: '操作方式', operate:false},
                        {field: 'memo', title: __('操作类型'), formatter:Table.api.formatter.search},
                        {field: 'createtime', title: __('Createtime'),sortable:true, operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'money', title: __('Money'), operate:false},
                        {field: 'own', title: __('Own'), operate: 'LIKE',formatter:Table.api.formatter.search},
                        {field: 'univalent', title: __('Univalent'), operate:false},
                        {field: 'remark', title: __('Remark'), operate: false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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