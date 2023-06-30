define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'account_auto_obtained_log/index' + location.search,
                    add_url: 'account_auto_obtained_log/add',
                    multi_url: 'account_auto_obtained_log/multi',
                    table: 'account_auto_obtained_log',
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
                        {field: 'account_id', title: __('Account_id')},
                        {field: 'account.account', title: __('Account')},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange'},
                        {field: 'msg', title: __('msg'),operate: false,formatter:Controller.api.formatter.msgText},
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            // 撤回
            $(document).on("click", ".btn-revoke", function () {
                //在table外不可以使用添加.btn-change的方法
                //只能自己调用Table.api.multi实现
                //如果操作全部则ids可以置为空
                var ids = Table.api.selectedids(table);
                Table.api.multi("revoke", ids.join(","), table, this);
            });

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
            },
            formatter: {
                msgText:function (value, row, index) {
                    return '<p style="overflow: hidden;height: auto;white-space: pre-wrap">'+value+'</p>';
                }
            }
        }
    };
    return Controller;
});