define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'account/index' + location.search,
                    add_url: 'account/add',
                    edit_url: 'account/edit',
                    del_url: 'account/del',
                    import_url : 'account/import',
                    multi_url: 'account/multi',
                    table: 'account',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'sort',
                columns: [
                    [
                        {checkbox: true},
                        {title:'#',operate:false,formatter:function (value,row,index) {
                                return index+1;
                            }},
                        {field: 'id', title: __('Id'),sortable:true},
                        {field: 'account', title: __('Account'),sortable:true,operate:'like'},
                        {field: 'status', title: __('Status'),searchList:{0:'禁用',1:'正常'},sortable:true,formatter: Table.api.formatter.toggle},
                        {field: 'sort', title: __('Sort'),operate:false,sortable:true},
                        {field: 'mobile', title: __('手机号码'),sortable:true,formatter:Table.api.formatter.search},
                        {field: 'udid_num', title: __('Udid_num'),sortable:true},
                        {field: 'use_num', title: "实际使用数量",operate:false},
                        {field: 'is_delete', title: __('是否删除'),searchList:{0:'已删除',1:'可用'},custom:{0:'danger',1:'success'},formatter:Table.api.formatter.label},
                        {field: 'type', title: __('类型'),searchList:{1:'公有',2:'私有'},custom:{1:'success',2:'info'},formatter:Table.api.formatter.label},
                        {field: 'create_time', title: __('添加时间'), operate:'RANGE', addclass:'datetimerange'},
                        {field: 'source', title: __('来源'), operate:'LIKE'},
                        {field: 'operate', title: __('Operate'), table: table,
                            events: Table.api.events.operate,
                            buttons:[
                                {
                                    name: 'update_udid',
                                    title: __('UDID重置'),
                                    classname: 'btn btn-xs btn-info btn-ajax',
                                    icon: 'fa fa-bug',
                                    url: 'account/update_udid',
                                }
                            ],
                            formatter: Table.api.formatter.operate}
                    ]
                ]
            });
            table.on('load-success.bs.table', function (e, data) {
                $("#last_num").text(data.extend.last_num);
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            // 启动和暂停按钮
            $(document).on("click", ".btn-start,.btn-disable", function () {
                //在table外不可以使用添加.btn-change的方法
                //只能自己调用Table.api.multi实现
                //如果操作全部则ids可以置为空
                var ids = Table.api.selectedids(table);
                Table.api.multi("start", ids.join(","), table, this);
            });

        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        test:function(){
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