define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'wx_host/index' + location.search,
                    add_url: 'wx_host/add',
                    edit_url: 'wx_host/edit',
                    del_url: 'wx_host/del',
                    multi_url: 'wx_host/multi',
                    import_url: 'wx_host/import',
                    table: 'wx_host',
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
                        {field: 'host_url', title: __('Host_url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'status',
                            title: __('Status'),
                            searchList: {0: '禁用', 1: '正常'},
                            sortable: true,
                            formatter: Table.api.formatter.toggle},
                        {field: 'admin.username', title: __('Admin.username'), operate: 'LIKE'},
                        // {field: 'user_id', title: __('User_id')},
                        {field: 'create_time', title: "创建时间", operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'use_time', title: __('Use_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
