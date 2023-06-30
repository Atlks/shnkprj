define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'proxy/app_download_ratio/index' + location.search,
                    // add_url: 'proxy/app_download_ratio/add',
                    // edit_url: 'proxy/app_download_ratio/edit',
                    // del_url: 'proxy/app_download_ratio/del',
                    // multi_url: 'proxy/app_download_ratio/multi',
                    import_url: 'proxy/app_download_ratio/import',
                    table: 'proxy_app',
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
                        {field: 'user_id', title: __('User_id'),formatter: Table.api.formatter.search},
                        {field: 'proxyuser.username', title: __('Proxyuser.username'), operate: 'LIKE',formatter:Table.api.formatter.search},
                        {field: 'app_num', title: __("App数量"), operate: false,sortable:true},
                        {field: 'download_num', title: __('下载数量'), operate: false},
                        // {field: 'name', title: __('Name'), operate: 'LIKE'},
                        // {field: 'icon', title: __('Icon'), operate: 'LIKE', formatter: Table.api.formatter.icon},
                        // {field: 'status', title: __('Status')},
                        // {field: 'is_delete', title: __('Is_delete')},
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