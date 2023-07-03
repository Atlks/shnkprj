define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'get_re_host/index' + location.search,
                    add_url: 'get_re_host/add',
                    edit_url: 'get_re_host/edit',
                    del_url: 'get_re_host/del',
                    multi_url: 'get_re_host/multi',
                    import_url: 'get_re_host/import',
                    table: 're_host',
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
                        {field: 'url', title: __('Url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 're_url', title: __('Re_url'), operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'remark', title: __('Remark'), operate: 'LIKE'},
                        // {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        // {field: 'user_id', title: __('User_id')},
                        {field: 'use_time', title: '使用时间', operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
            table.on('load-success.bs.table', function (e, data) {
                $("#num").text(data.extend.num);
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
