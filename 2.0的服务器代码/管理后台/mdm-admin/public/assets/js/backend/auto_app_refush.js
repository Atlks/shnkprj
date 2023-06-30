define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'auto_app_refush/index' + location.search,
                    add_url: 'auto_app_refush/add',
                    edit_url: 'auto_app_refush/edit',
                    del_url: 'auto_app_refush/del',
                    multi_url: 'auto_app_refush/multi',
                    import_url: 'auto_app_refush/import',
                    table: 'auto_app_refush',
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
                        {field: 'id', title: __('Id'),visible:false},
                        {field: 'app_id', title: __('App_id')},
                        {field: 'proxyapp.name', title: __('Proxyapp.name'), operate: 'LIKE'},
                        {field: 'scale', title: __('Scale'),operate:false},
                        {field: 'status', title: __('Status'),searchList:{0:'关闭',1:'正常'},sortable:true,formatter: Table.api.formatter.toggle},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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