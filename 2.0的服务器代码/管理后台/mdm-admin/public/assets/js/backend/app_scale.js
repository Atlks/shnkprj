define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'app_scale/index' + location.search,
                    table: 'proxy_bale_rate',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'num',
                search:false,
                showToggle:false,
                showColumns:false,
                showExport:false,
                showSearch:false,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'app_id', title: __('AppId')},
                        {field: 'user_id', title: __('用户ID')},
                        {field: 'name', title: __('名称'),operate: false},
                        {field: 'fee_num', title: __('扣费量'),operate:false},
                        {field: 'install_num', title: __('安装量'),operate:false},
                        {field: 'diff', title: __('差值(扣费量-安装量)'),operate:false},
                        // {field: 'create_time', title: __('时间'), operate:"RANGE",addclass:'datetimerange'},
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