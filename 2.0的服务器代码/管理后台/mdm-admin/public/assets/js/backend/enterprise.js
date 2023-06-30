define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'enterprise/index' + location.search,
                    add_url: 'enterprise/add',
                    edit_url: 'enterprise/edit',
                    del_url: 'enterprise/del',
                    multi_url: 'enterprise/multi',
                    import_url: 'enterprise/import',
                    table: 'enterprise',
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
                        {field: 'name', title: __('证书名称')},
                        {field: 'path', title: __('Path'),visible:false,operate: false,formatter: Table.api.formatter.url},
                        {field: 'oss_path', title: __('Oss_path'),operate: false,visible:false,formatter: Table.api.formatter.url},
                        {field: 'password', title: __('Password'),visible:false,},
                        {field: 'provisioning_path', title: __('Provisioning_path'),operate: false,visible:false,formatter: Table.api.formatter.url},
                        {field: 'oss_provisioning', title: __('Oss_provisioning'),operate: false,visible:false,formatter: Table.api.formatter.url},
                        // {field: 'status', title: __('Status'),searchList:{0:'停用',1:'正常'},formatter:Table.api.formatter.status},
                        {field: 'status', title: __('Status'),searchList:{0:'停用',1:'正常'},sortable:true,formatter: Table.api.formatter.toggle},
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