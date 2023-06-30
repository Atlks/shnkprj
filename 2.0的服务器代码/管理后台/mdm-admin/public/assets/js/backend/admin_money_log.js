define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'admin_money_log/index' + location.search,
                    // add_url: 'admin_money_log/add',
                    edit_url: 'admin_money_log/edit',
                    // del_url: 'admin_money_log/del',
                    // multi_url: 'admin_money_log/multi',
                    // import_url: 'admin_money_log/import',
                    table: 'admin_money_log',
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
                        // {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'user_id', title: "变更用户ID"},
                        {field: 'user_name', title: "用户名",operate: "LIKE",formatter: Table.api.formatter.search},
                        {field: 'admin.username', title: "操作人员", operate: 'LIKE',formatter: Table.api.formatter.search},
                        {field: 'sign_num', title: __('Sign_num'),operate: false},
                        {field: 'before', title: __('Before'),operate: false},
                        {field: 'after', title: __('After'),operate: false},
                        {field: 'type', title: "类型",
                            searchList:{1:"管理员充值",2:"管理员扣除",3:"商务充值",4:"商务扣除",5:"管理员余额补差"},
                            custom:{1:"success",2:"danger",3:"info",4:"danger",5:"success"},
                            formatter: Table.api.formatter.label
                        },
                        {field: 'memo', title: __('Memo'), formatter: Table.api.formatter.search},
                        {field: 'create_time', title: __('Create_time'),sortable:true, operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'create_admin_id', title: __('Create_admin_id')},
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