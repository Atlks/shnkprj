define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user_idfv/index' + location.search,
                    add_url: 'user_idfv/add',
                    edit_url: 'user_idfv/edit',
                    del_url: 'user_idfv/del',
                    multi_url: 'user_idfv/multi',
                    import_url: 'user_idfv/import',
                    table: 'user_idfv',
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
                        {field: 'id', title: __('Id'),visible:false},
                        {field: 'app_id', title: __('App_id'),formatter: Table.api.formatter.search},
                        {field: 'proxyapp.user_id', title: __('用户ID'),formatter: Table.api.formatter.search},
                        {field: 'proxyapp.name', title: __('用户名'), operate: 'LIKE',formatter: Table.api.formatter.search},
                        {field: 'idfv', title: __('Idfv'), operate: 'LIKE'},
                        {field: 'device', title: __('机型'), operate: 'LIKE'},
                        {field: 'osversion', title: __('系统版本'), operate: 'LIKE'},
                        {
                            field: 'create_time',
                            title: __('Create_time'),
                            sortable: true,
                            operate: 'RANGE',
                            addclass: 'datetimerange'
                        },
                        {field: 'ip', title: __('IP'), operate: false},
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