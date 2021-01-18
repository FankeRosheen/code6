@extends('base')
@section('content')
    <link rel="stylesheet" href="{{ URL::asset('css/configToken.css?v=') . VERSION }}">
    <script>
        Ext.onReady(function () {
            Ext.QuickTips.init(true, {dismissDelay: 0});

            Ext.create('Ext.data.Store', {
                storeId: 'store',
                pageSize: 99999, // 不分页
                autoLoad: true,
                proxy: {
                    type: 'ajax',
                    url: '/api/configToken',
                }
            });

            var status = [
                {
                    color: 'blue',
                    text: '未知',
                    tooltip: '没有读取到此令牌状态（可能是当前请求 GitHub 网络不通畅）',
                },
                {
                    color: 'green',
                    text: '正常',
                    tooltip: '此令牌可正常使用',
                },
                {
                    color: 'red',
                    text: '异常',
                    tooltip: '此令牌无法使用（请检查 GitHub 账号及令牌是否异常）',
                },
            ]

            var content = '';
            content += '<p class="tip-title">1. 令牌是什么？<span></p>';
            content += '<p>用来请求 GitHub API 的 Token（即 GitHub personal access token）</p><br/>';
            content += '<p class="tip-title">2. 如何申请令牌？</p>';
            content += '<p>GitHub - Settings - Developer settings - Personal access tokens - Generate new token';
            content += '（<a target="_blank" href="https://github.com/settings/tokens/new">直达</a>）</p><br/>';
            content += '<p class="tip-title">3. 为何需要配置多个令牌？</p>';
            content += '<p>监控需要大量请求 GitHub API，而 GitHub 限制了 API 的请求速率';
            content += '（<a target="_blank" href="https://developer.github.com/v3/#rate-limiting">GitHub API v3 - Rate limiting</a>）</p>';
            content += '<p>因此需要多个 GitHub 账号创建令牌用于轮询请求（建议至少配置 3 个令牌）</p>';

            var grid = Ext.create('plugin.grid', {
                store: Ext.data.StoreManager.lookup('store'),
                tbar: {
                    margin: '5 12 15 18',
                    items: [
                        {
                            id: 'help',
                            text: '帮助信息',
                            iconCls: 'icon-page-star',
                            handler: function () {
                                Ext.Msg.show({
                                    title: '帮助信息',
                                    iconCls: 'icon-page-star',
                                    modal: false,
                                    maxWidth: 800,
                                    message: content,
                                });
                            }
                        },
                        '->',
                        {
                            text: '配置引导',
                            iconCls: 'icon-page-magnify',
                            handler: function () {
                                guide();
                            }
                        },
                        {
                            id: 'addToken',
                            text: '新增令牌',
                            iconCls: 'icon-add',
                            margin: '0 13 0 0',
                            handler: function () {
                                winForm([]);
                            }
                        }
                    ]
                },
                columns: [
                    {
                        text: 'ID',
                        dataIndex: 'id',
                        width: 75,
                        align: 'center',
                    },
                    {
                        text: '令牌',
                        dataIndex: 'token',
                        width: 380,
                        align: 'center',
                    },
                    {
                        text: '状态',
                        dataIndex: 'status',
                        width: 150,
                        align: 'center',
                        renderer: function (value, cellmeta) {
                            var data = status[value];
                            var tpl = new Ext.XTemplate('<div class="tag tag-{color}">{text}</div>');
                            cellmeta.tdAttr = 'data-qtip="' + data.tooltip + '"'
                            return tpl.apply(data);
                        }
                    },
                    {
                        text: '创建时间',
                        dataIndex: 'created_at',
                        align: 'center',
                        width: 180,
                        hidden: true,
                    },
                    {
                        text: 'GitHub接口请求配额',
                        columns: [
                            {
                                text: '接口用量',
                                tooltip: '已用次数 / 最大允许请求次数',
                                width: 180,
                                renderer: function (value, cellmeta, record) {
                                    var item = [], data = record.data;
                                    item.limit = data.api_limit;
                                    item.used = Math.max(0, item.limit - data.api_remaining);
                                    item.percent = parseFloat(item.used / item.limit * 100);
                                    return new Ext.XTemplate(
                                        '<div class="progress">',
                                        '    <div style="width:{percent}%">',
                                        '        <span>{used} / {limit}</span>',
                                        '    </div>',
                                        '</div>',
                                    ).apply(item);
                                }
                            },
                            {
                                text: '重置时间',
                                dataIndex: 'api_reset_at',
                                align: 'center',
                                width: 180,
                                renderer: function (value) {
                                    return value ? value : '-';
                                }
                            }
                        ]
                    },
                    {
                        text: '说明',
                        dataIndex: 'description',
                        align: 'center',
                        flex: 1,
                        renderer: function (value) {
                            return value ? value : '-';
                        }
                    },
                    {
                        text: '操作',
                        sortable: false,
                        width: 250,
                        align: 'center',
                        xtype: 'widgetcolumn',
                        widget: {
                            xtype: 'buttongroup',
                            baseCls: 'border:0',
                            layout: {
                                type: 'hbox',
                                pack: 'center',
                            },
                            items: [
                                {
                                    text: '编辑',
                                    iconCls: 'icon-bullet-green',
                                    margin: '0 20 0 0',
                                    handler: function (obj) {
                                        var record = obj.up().getWidgetRecord();
                                        winForm(record.data);
                                    }
                                },
                                {
                                    text: '删除',
                                    iconCls: 'icon-bullet-red',
                                    handler: function (obj) {
                                        Ext.Msg.show({
                                            title: '警告',
                                            iconCls: 'icon-warning',
                                            message: '确定删除此项？',
                                            buttons: Ext.Msg.YESNO,
                                            fn: function (btn) {
                                                if (btn !== 'yes') {
                                                    return;
                                                }
                                                var record = obj.up().getWidgetRecord();
                                                var url = '/api/configToken/' + record.id;
                                                tool.ajax('DELETE', url, {}, function (rsp) {
                                                    if (rsp.success) {
                                                        tool.toast(rsp.message, 'success');
                                                        grid.store.remove(record);
                                                    } else {
                                                        tool.toast(rsp.message, 'error');
                                                    }
                                                });
                                            }
                                        });
                                    }
                                }
                            ]
                        }
                    }
                ]
            });

            function winForm(data) {
                if (!Ext.getCmp('tokenWin')) {
                    var win = Ext.create('Ext.window.Window', {
                        id: 'tokenWin',
                        title: '令牌信息',
                        width: 500,
                        iconCls: 'icon-page-wrench',
                        layout: 'fit',
                        items: [
                            {
                                xtype: 'form',
                                layout: 'form',
                                bodyPadding: 15,
                                items: [
                                    {
                                        id: 'token',
                                        name: 'token',
                                        xtype: 'textfield',
                                        fieldLabel: '令牌',
                                        allowBlank: false,
                                        value: data.token,
                                    },
                                    {
                                        id: 'description',
                                        name: 'description',
                                        xtype: 'textfield',
                                        fieldLabel: '说明',
                                        value: data.description,
                                    }
                                ],
                                buttons: [
                                    {
                                        text: '重置',
                                        handler: function () {
                                            this.up('form').getForm().reset();
                                        }
                                    },
                                    {
                                        id: 'submit',
                                        text: '提交',
                                        formBind: true,
                                        handler: function () {
                                            var params = this.up('form').getValues();
                                            var method = data.id ? 'PUT' : 'POST';
                                            var url = data.id ? '/api/configToken/' + data.id : '/api/configToken';
                                            tool.ajax(method, url, params, function (rsp) {
                                                if (rsp.success) {
                                                    win.close();
                                                    tool.toast('操作成功', 'success');
                                                    var index = data.id ? grid.store.indexOfId(data.id) : 0;
                                                    grid.store.insert(Math.max(0, index), rsp.data);
                                                } else {
                                                    tool.toast(rsp.message, 'warning');
                                                }
                                            });
                                        }
                                    }
                                ]
                            }
                        ]
                    }).show();
                    steps();
                }
            }

            Ext.create('Ext.container.Container', {
                renderTo: Ext.getBody(),
                height: '100%',
                layout: 'fit',
                items: [grid],
            });

            function guide() {
                var driver = new Driver({
                    opacity: 0,
                    doneBtnText: '完成',
                    closeBtnText: '关闭',
                    nextBtnText: '下一步',
                    prevBtnText: '上一步',
                    allowClose: true
                });
                driver.defineSteps([
                    {
                        element: '#addToken',
                        popover: {
                            className: 'addToken',
                            title: '新增令牌',
                            description: '点击 <span style="color:#1890FF"> 新增令牌 </span> 按钮',
                            position: 'left'
                        }
                    }
                ]);
                driver.start();
            }

            function steps() {
                var driver = new Driver({
                    opacity: 0,
                    doneBtnText: '完成',
                    closeBtnText: '关闭',
                    nextBtnText: '下一步',
                    prevBtnText: '上一步',
                    allowClose: false,
                });
                var tokenDescription = '';
                tokenDescription += '例：<span style="color:#157FCC">7832e5a6b69bc6bc0aa4</span></br>详情点击';
                tokenDescription += `<span style="color:#157FCC;cursor: pointer;" onclick="document.getElementById('help').click()"> 帮助信息 </span>`
                driver.defineSteps([
                    {
                        element: '#token-inputEl',
                        popover: {
                            className: 'token-steps',
                            title: '令牌配置',
                            description: tokenDescription,
                            position: 'right'
                        }
                    },
                    {
                        element: '#description-inputEl',
                        popover: {
                            className: 'description-steps',
                            title: '说明',
                            description: '说明或备注（选填）',
                            position: 'right'
                        }
                    }, {
                        element: '#submit',
                        popover: {
                            className: 'submit-steps',
                            title: '提交',
                            description: `提交后请前往（配置中心 - <a style='color:#157FCC' href="/configJob" target='_blank'>任务配置</a>）进行任务配置`,
                            position: 'right'
                        }
                    }
                ]);
                driver.start();
            }
        });
    </script>
@endsection
