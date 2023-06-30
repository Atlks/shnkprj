define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'proxy/recharge_log/index' + location.search,
                    add_url: 'proxy/recharge_log/add',
                    edit_url: 'proxy/recharge_log/edit',
                    del_url: 'proxy/recharge_log/del',
                    multi_url: 'proxy/recharge_log/multi',
                    import_url: 'proxy/recharge_log/import',
                    table: 'proxy_recharge_log',
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
                        {field:'id',title:'ID',visible:false},
                        // {field: 'user_id', title: __('User_id'),formatter:Table.api.formatter.search},
                        {field: 'pid', title: __('代理ID'),formatter:Table.api.formatter.search},
                        {field: 'p_user', title: __('代理'),operate:false},
                        {field: 'proxyuser.username', title: __('用户'),formatter:Table.api.formatter.search},
                        {field: 'num', title: __('Num'),operate:false},
                        {field: 'type', title: __('类型'),searchList:{1:'平台充值',2:'平台扣除',5:'代理充值',6:'代理扣除'},custom:{1:"info",2:'danger',6:'danger',5:'success'},formatter:Table.api.formatter.label},
                        {field: 'remark', title: __('Remark'),operate: false},
                        {field: 'create_time', title: __('Create_time'),sortable:true, operate:'RANGE', addclass:'datetimerange'},
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