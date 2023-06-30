define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'download_url/index' + location.search,
                    add_url: 'download_url/add',
                    edit_url: 'download_url/edit',
                    del_url: 'download_url/del',
                    multi_url: 'download_url/multi',
                    import_url: 'download_url/import',
                    table: 'download_url',
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
                        {field: 'name', title: __('Name'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'), searchList: {"1":__('Yes'),"0":__('No')}, formatter: Table.api.formatter.toggle},
                        {field: 'is_default', title: __('Is_default'),searchList: {"1":__('默认'),"0":__('否')},formatter:Table.api.formatter.label},
                        {field: 'cert_path', title: __('Cert_path'), operate: false,visible:false,formatter: Table.api.formatter.url},
                        {field: 'pem_path', title: __('Pem_path'), operate: false,visible:false,formatter: Table.api.formatter.url},
                        {field: 'key_path', title: __('Key_path'), operate: false,visible:false,formatter: Table.api.formatter.url},
                        {field: 'wx_port', title: __('Wx_port'), operate: false},
                        {field: 'web_port', title: __('Web_port'), operate: false},
                        {field: 'admin_port', title: __('Admin_port'), operate: false},
                        {field: 'udid_port', title: __('Udid_port'), operate: false},
                        {field: 'plist_port', title: __('Plist_port'), operate: false},
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