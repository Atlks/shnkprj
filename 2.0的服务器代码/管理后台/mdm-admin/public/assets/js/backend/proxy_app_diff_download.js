define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'proxy_app_diff_download/index' + location.search,
                    // add_url: 'proxy_app_diff_download/add',
                    // edit_url: 'proxy_app_diff_download/edit',
                    del_url: 'proxy_app_diff_download/del',
                    // multi_url: 'proxy_app_diff_download/multi',
                    import_url: 'proxy_app_diff_download/import',
                    table: 'proxy_app_diff_download',
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
                        {field: 'app_id', title: __('App Id')},
                        {field: 'proxyapp.name', title: __('Proxyapp.name'), operate: 'LIKE',formatter: Table.api.formatter.search},
                        {field: 'proxyapp.user_id', title: __('Proxyapp.user_id'),formatter: Table.api.formatter.search},
                        {field: 'proxyapp.icon', title: __('LOGO'), operate: false, formatter: Table.api.formatter.image},
                        {field: 'one_num', title: Config.time_diff_text.one_num ,sortable: true,},
                        {field: 'two_num', title: Config.time_diff_text.two_num,sortable: true},
                        {field: 'three_num', title: Config.time_diff_text.three_num,sortable: true},
                        {field: 'four_num', title: Config.time_diff_text.four_num,sortable: true},
                        {field: 'five_num', title: Config.time_diff_text.five_num,sortable: true},
                        {field: 'diff_num', title: __('Diff_num'),sortable: true},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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