define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'bale_rate_v1/index' + location.search,
                    table: 'proxy_v1_bale_rate',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                showColumns: Config.is_showColumn,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'),visible:false},
                        {field: 'app_id', title: __('App_id'),formatter:Table.api.formatter.search},
                        {field: 'account_id', title: "账号ID",formatter:Table.api.formatter.search},
                        {field: 'app_name', title: __('APP'),formatter:Table.api.formatter.search},
                        {field: 'pid', title: __('Pid'),formatter:Table.api.formatter.search},
                        {field: 'username', title: __('用户'),formatter:Table.api.formatter.search},
                        {field: 'is_auto', title: __('自动刷'),sortable:true,searchList:{0:'否',1:'是'},
                            custom:{0:'info',1:'success'},
                            operate:Config.is_operate,
                            visible:Config.is_showColumn,
                            formatter:Table.api.formatter.label},
                        {field: 'udid', title: __('Udid')},
                        {field: 'resign_udid', title: __('重置UDID')},
                        {field: 'ip', title: __('IP')},
                        {field: 'device', title: __('Device'),operate:false},
                        {field: 'sign_num', title: '扣除次数',operate:false},
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange'},
                        {field: 'update_time', title: "更新时间", operate:'RANGE', addclass:'datetimerange'},
                    ]
                ]
            });
            table.on('load-success.bs.table', function (e, data) {
                $("#all-num").text(data.extend.all_num);
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