define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'oss_config/index' + location.search,
                    add_url: 'oss_config/add',
                    edit_url: 'oss_config/edit',
                    del_url: 'oss_config/del',
                    multi_url: 'oss_config/multi',
                    table: 'oss_config',
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
                        {field: 'name', title: __('Name'),sortable:true},
                        {field: 'nickname', title: __('OSS简称'),sortable:true},
                        {field: 'bucket', title: __('Bucket')},
                        {field: 'endpoint', title: __('Endpoint')},
                        {field: 'own_endpoint', title: __('Own_endpoint')},
                        {field: 'url', title: __('Url'), formatter: Table.api.formatter.url},
                        {field: 'status', title: __('Status'), searchList: {"0":__('关闭'),"1":__('正常')}, formatter: Table.api.formatter.status},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange'},
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