define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'admin_money_log/index' + location.search,
                    add_url: 'admin_money_log/add',
                    edit_url: 'admin_money_log/edit',
                    del_url: 'admin_money_log/del',
                    multi_url: 'admin_money_log/multi',
                    import_url: 'admin_money_log/import',
                    table: 'admin_money_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        // {field: 'id', title: __('Id')},
                        {field: 'user_id', title: __('User_id')},
                        {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE',formatter:Table.api.formatter.search},
                        {field: 'num', title: __('Num'), operate:'BETWEEN'},
                        {field: 'before', title: __('Before'), operate:'BETWEEN'},
                        {field: 'after', title: __('After'), operate:'BETWEEN'},
                        {field: 'memo', title: __('Memo'), operate: 'LIKE'},
                        {field: 'create_time', title: __('Createtime'), operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        // {field: 'money', title: __('Money'), operate:'BETWEEN'},
                        // {field: 'own', title: __('Own'), operate: 'LIKE'},
                        // {field: 'univalent', title: __('Univalent'), operate:'BETWEEN'},
                        // {field: 'remark', title: __('Remark'), operate: 'LIKE'},

                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
