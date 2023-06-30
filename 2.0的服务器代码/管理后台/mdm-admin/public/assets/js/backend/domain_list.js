define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'domain_list/index' + location.search,
                    add_url: 'domain_list/add',
                    edit_url: 'domain_list/edit',
                    del_url: 'domain_list/del',
                    multi_url: 'domain_list/multi',
                    import_url: 'domain_list/import',
                    table: 'domain_list',
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
                        {field: 'domain', title: __('Domain'), operate: 'LIKE'},
                        {field: 'status', title: __('Status'),searchList: {"1":"正常","0":"关闭"}, formatter: Table.api.formatter.toggle},
                        {field: 'is_use', title: __('Is_use'),searchList: {"1":"是","0":'否'},  custom: {1: 'success', 0: 'success'},formatter:Table.api.formatter.label},
                        {field: 'is_error_use', title: __('Is_error_use'),
                            searchList: {"1":"是","0":'否'},
                            custom: {1: 'danger', 0: 'success'},
                            operate:Config.is_operate,
                            visible:Config.is_showColumn,
                            formatter:Table.api.formatter.label},
                        {field: 'admin.username', title: '使用商务', operate: 'LIKE'},
                        // {field: 'proxyuserdomain.domain', title: __('Proxyuserdomain.domain'), operate: 'LIKE'},
                        // {field: 'admin_id', title: __('Admin_id')},
                        // {field: 'daili_id', title: __('Daili_id')},
                        // {field: 'is_error_use', title: __('Is_error_use')},
                        {field: 'remark', title: __('Remark'),
                            operate: false,
                            visible:Config.is_showColumn,
                        },
                        {field: 'create_time', title: __('Create_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'update_time', title: __('Update_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
                        {field: 'use_time', title: __('Use_time'), operate:'RANGE', addclass:'datetimerange', autocomplete:false},
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
        get_domain: function () {
            $(document).on('change', ".is_fan", function () {
                var that = this;
                var is_fan = $(that).val();
                Fast.api.ajax({
                    url: "domain_list/get_url_list",
                    data: {is_fan: is_fan},
                }, function (data, ret) {
                    if(is_fan==1) {
                        $("#d_url").hide();
                        $("#fan").show();
                        if (ret.code == 1) {
                            $("#domain_url_txt").text("."+data.domain);
                            $("#domain_url").val(data.domain);
                        } else {
                            layer.msg(ret.msg);
                        }
                    }else{
                        $("#d_url").show();
                        $("#fan").hide();
                        if (ret.code == 1) {
                            $("#domain_id").val(data.id);
                            $("#c-domain").val(data.domain);
                        } else {
                            layer.msg(ret.msg);
                        }
                    }
                    return false;
                });
                return false;
            });

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