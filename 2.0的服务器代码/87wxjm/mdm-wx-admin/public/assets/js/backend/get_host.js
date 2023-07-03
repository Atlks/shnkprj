define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'get_host/index' + location.search,
                    add_url: 'get_host/add',
                    edit_url: 'get_host/edit',
                    del_url: 'get_host/del',
                    multi_url: 'get_host/multi',
                    import_url: 'get_host/import',
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
                        // {field: 'id', title: __('Id')},
                        {field: 'host_url', title: __('Host_url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'remark', title: '备注', formatter: Table.api.formatter.search},
                        // {field: 'status', title: __('Status')},
                        // {field: 'user_id', title: __('User_id')},
                        // {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'use_time', title: __('Use_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            table.on('load-success.bs.table', function (e, data) {
                $("#num").text(data.extend.num);
            });
            $(document).on("click",".btn-get-url",function () {
                Table.api.multi("get_url", null, table, this);
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
            }
        }
    };
    return Controller;
});
