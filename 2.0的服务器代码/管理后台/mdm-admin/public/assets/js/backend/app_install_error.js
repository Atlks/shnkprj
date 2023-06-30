define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'app_install_error/index' + location.search,
                    // add_url: 'app_install_error/add',
                    // edit_url: 'app_install_error/edit',
                    del_url: 'app_install_error/del',
                    // multi_url: 'app_install_error/multi',
                    import_url: 'app_install_error/import',
                    table: 'app_install_error',
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
                        {field: 'proxyapp.id', title: 'APP_ID',operate:false},
                        {field: 'proxyapp.name', title: '名称', operate: false},
                        {field: 'tag', title: '唯一标识',formatter: Table.api.formatter.search},
                        {field: 'ip', title: __('IP'),formatter: Table.api.formatter.search},
                        {field: 'error_info', title: __('Error_info'), operate: 'LIKE',formatter: Table.api.formatter.search},
                        {field: 'create_time', title: __('时间'), operate:'RANGE', sortable:true,addclass:'datetimerange', autocomplete:false},
                        {field: 'post_data', title: __('请求内容'), operate:false,formatter:Controller.api.formatter.msgText},
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
            },
            formatter: {
                msgText:function (value, row, index) {
                    return '<p style="overflow: hidden;height: auto;white-space: pre-wrap">'+value+'</p>';
                }
            }
        }
    };
    return Controller;
});