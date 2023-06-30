define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'proxy_app_apk_download_log/index' + location.search,
                    add_url: 'proxy_app_apk_download_log/add',
                    edit_url: 'proxy_app_apk_download_log/edit',
                    del_url: 'proxy_app_apk_download_log/del',
                    multi_url: 'proxy_app_apk_download_log/multi',
                    import_url: 'proxy_app_apk_download_log/import',
                    table: 'proxy_app_apk_download_log',
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
                        {field: 'app_id', title: __('App_id'),formatter:Table.api.formatter.search},
                        {field: 'user_id', title: '用户ID',formatter:Table.api.formatter.search},
                        {field: 'app_name', title: "APP",operate: false},
                        // {field: 'ip', title: __('Ip'), operate: 'LIKE'},
                        {field: 'num', title: "今日下载", operate: false,sortable: true},
                        // {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},

                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            table.on('load-success.bs.table', function (e, data) {
                $("#today_num").text(data.extend.today_num);
                $("#yesterday_num").text(data.extend.yesterday_num);
                $("#week_num").text(data.extend.week_num);
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