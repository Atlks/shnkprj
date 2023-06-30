define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'app_install_callback/index' + location.search,
                    add_url: 'app_install_callback/add',
                    edit_url: 'app_install_callback/edit',
                    del_url: 'app_install_callback/del',
                    multi_url: 'app_install_callback/multi',
                    import_url: 'app_install_callback/import',
                    table: 'app_install_callback',
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
                        {field: 'app_id', title: __('APPID'),formatter:Table.api.formatter.search},
                        {field: 'user_id', title: __('用户ID'),formatter:Table.api.formatter.search},
                        {field: 'proxyapp.name', title: __('APP名称'), operate: 'LIKE',formatter:Table.api.formatter.search},
                        {field: 'proxyuser.username', title: __('用户名'), operate: 'LIKE',formatter:Table.api.formatter.search},
                        {field: 'install_num', title: __('用户使用量')},
                        // {field: 'idfv', title: __('Idfv'), operate: 'LIKE'},
                        // {field: 'device', title: __('Device'), operate: 'LIKE'},
                        // {field: 'osversion', title: __('Osversion'), operate: 'LIKE'},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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