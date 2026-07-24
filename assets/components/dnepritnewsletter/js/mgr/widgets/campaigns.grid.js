DnepritNewsletter.grid.Campaigns = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        id: 'dnepritnewsletter-grid-campaigns',
        url: DnepritNewsletter.config.connectorUrl,
        baseParams: {
            action: 'campaigns/getlist'
        },
        fields: [
            'id', 'title', 'subject', 'body_html', 'body_text', 'sender_email', 'sender_name',
            'reply_to', 'status', 'status_label', 'recipients_total', 'sent_count', 'failed_count',
            'created_at', 'updated_at', 'scheduled_at', 'started_at', 'finished_at',
            'can_edit', 'can_remove', 'can_prepare'
        ],
        paging: true,
        remoteSort: true,
        autoHeight: true,
        columns: [{
            header: _('dnepritnewsletter_id'),
            dataIndex: 'id',
            width: 55,
            sortable: true
        }, {
            header: _('dnepritnewsletter_campaign_title'),
            dataIndex: 'title',
            width: 180,
            sortable: true
        }, {
            header: _('dnepritnewsletter_campaign_subject'),
            dataIndex: 'subject',
            width: 225,
            sortable: true
        }, {
            header: _('dnepritnewsletter_status'),
            dataIndex: 'status_label',
            width: 105,
            sortable: false,
            renderer: function (value, meta, record) {
                return '<span class="dnepritnewsletter-status dnepritnewsletter-status-' +
                    Ext.util.Format.htmlEncode(record.data.status) + '">' +
                    Ext.util.Format.htmlEncode(value) + '</span>';
            }
        }, {
            header: _('dnepritnewsletter_campaign_recipients'),
            dataIndex: 'recipients_total',
            width: 85,
            sortable: true
        }, {
            header: _('dnepritnewsletter_campaign_sent'),
            dataIndex: 'sent_count',
            width: 70,
            sortable: true
        }, {
            header: _('dnepritnewsletter_campaign_failed'),
            dataIndex: 'failed_count',
            width: 70,
            sortable: true
        }, {
            header: _('dnepritnewsletter_queue_scheduled_at'),
            dataIndex: 'scheduled_at',
            width: 135,
            sortable: true
        }, {
            header: _('dnepritnewsletter_campaign_created_at'),
            dataIndex: 'created_at',
            width: 135,
            sortable: true
        }],
        tbar: [{
            text: _('dnepritnewsletter_campaign_create'),
            cls: 'primary-button',
            handler: this.createCampaign,
            scope: this
        }, '-', {
            xtype: 'textfield',
            id: 'dnepritnewsletter-campaigns-search',
            emptyText: _('dnepritnewsletter_campaign_search'),
            width: 240,
            enableKeyEvents: true,
            listeners: {
                keyup: {
                    fn: function (field) {
                        this.getStore().baseParams.query = field.getValue();
                        this.getBottomToolbar().changePage(1);
                    },
                    scope: this,
                    buffer: 400
                }
            }
        }, {
            xtype: 'combo',
            id: 'dnepritnewsletter-campaigns-status-filter',
            emptyText: _('dnepritnewsletter_filter_status'),
            width: 155,
            store: new Ext.data.ArrayStore({
                idIndex: 0,
                fields: ['value', 'label'],
                data: [
                    ['', _('dnepritnewsletter_all_statuses')],
                    ['draft', _('dnepritnewsletter_campaign_status_draft')],
                    ['scheduled', _('dnepritnewsletter_campaign_status_scheduled')],
                    ['queued', _('dnepritnewsletter_campaign_status_queued')],
                    ['sending', _('dnepritnewsletter_campaign_status_sending')],
                    ['sent', _('dnepritnewsletter_campaign_status_sent')],
                    ['paused', _('dnepritnewsletter_campaign_status_paused')],
                    ['failed', _('dnepritnewsletter_campaign_status_failed')]
                ]
            }),
            mode: 'local',
            triggerAction: 'all',
            displayField: 'label',
            valueField: 'value',
            editable: false,
            listeners: {
                select: {
                    fn: function (combo) {
                        this.getStore().baseParams.status = combo.getValue();
                        this.getBottomToolbar().changePage(1);
                    },
                    scope: this
                }
            }
        }, {
            text: _('dnepritnewsletter_reset'),
            handler: this.resetFilters,
            scope: this
        }],
        listeners: {
            rowDblClick: {
                fn: function (grid, rowIndex, event) {
                    var record = grid.getStore().getAt(rowIndex).data;
                    if (record.can_edit) {
                        this.openUpdateWindow(record, event);
                    }
                },
                scope: this
            }
        }
    });

    DnepritNewsletter.grid.Campaigns.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.grid.Campaigns, MODx.grid.Grid, {
    getMenu: function () {
        var menu = [];

        if (this.menu.record.can_prepare) {
            menu.push({
                text: _('dnepritnewsletter_queue_prepare'),
                handler: function () {
                    this.openPrepareQueueWindow(this.menu.record);
                }
            });
            menu.push('-');
        }

        if (this.menu.record.can_edit) {
            menu.push({
                text: _('dnepritnewsletter_campaign_update'),
                handler: function () {
                    this.openUpdateWindow(this.menu.record);
                }
            });
        }

        menu.push({
            text: _('dnepritnewsletter_campaign_duplicate'),
            handler: function () {
                this.duplicateCampaign(this.menu.record);
            }
        });

        if (this.menu.record.can_remove) {
            menu.push('-');
            menu.push({
                text: _('dnepritnewsletter_remove'),
                handler: function () {
                    this.removeCampaign(this.menu.record);
                }
            });
        }

        return menu;
    },

    createCampaign: function (button, event) {
        if (!this.createWindow) {
            this.createWindow = MODx.load({
                xtype: 'dnepritnewsletter-window-campaign',
                title: _('dnepritnewsletter_campaign_create'),
                action: 'campaigns/create',
                listeners: {
                    success: {
                        fn: this.refresh,
                        scope: this
                    }
                }
            });
        }

        this.createWindow.reset();
        this.createWindow.setValues({
            sender_email: DnepritNewsletter.config.senderEmail || '',
            sender_name: DnepritNewsletter.config.senderName || '',
            reply_to: DnepritNewsletter.config.replyTo || ''
        });
        this.createWindow.show(event ? event.target : null);
    },

    openUpdateWindow: function (record, event) {
        if (!record || !record.can_edit) {
            MODx.msg.alert(_('error'), _('dnepritnewsletter_campaign_err_locked'));
            return;
        }

        if (!this.updateWindow) {
            this.updateWindow = MODx.load({
                xtype: 'dnepritnewsletter-window-campaign',
                title: _('dnepritnewsletter_campaign_update'),
                action: 'campaigns/update',
                listeners: {
                    success: {
                        fn: this.refresh,
                        scope: this
                    }
                }
            });
        }

        this.updateWindow.reset();
        this.updateWindow.setValues(record);
        this.updateWindow.show(event ? event.target : null);
    },

    openPrepareQueueWindow: function (record, event) {
        if (!record || !record.can_prepare) {
            MODx.msg.alert(_('error'), _('dnepritnewsletter_queue_err_already_prepared'));
            return;
        }

        if (!this.queueWindow) {
            this.queueWindow = MODx.load({
                xtype: 'dnepritnewsletter-window-prepare-queue',
                title: _('dnepritnewsletter_queue_prepare_title'),
                action: 'campaigns/preparequeue',
                listeners: {
                    success: {
                        fn: this.refresh,
                        scope: this
                    }
                }
            });
        }

        this.queueWindow.reset();
        this.queueWindow.setValues({
            id: record.id,
            send_now: 1
        });
        this.queueWindow.show(event ? event.target : null);
    },

    duplicateCampaign: function (record) {
        if (!record || !record.id) {
            return;
        }

        MODx.Ajax.request({
            url: DnepritNewsletter.config.connectorUrl,
            params: {
                action: 'campaigns/duplicate',
                id: record.id
            },
            listeners: {
                success: {
                    fn: this.refresh,
                    scope: this
                }
            }
        });
    },

    removeCampaign: function (record) {
        if (!record || !record.id || !record.can_remove) {
            MODx.msg.alert(_('error'), _('dnepritnewsletter_campaign_err_locked'));
            return;
        }

        MODx.msg.confirm({
            title: _('dnepritnewsletter_remove'),
            text: _('dnepritnewsletter_campaign_remove_confirm'),
            url: DnepritNewsletter.config.connectorUrl,
            params: {
                action: 'campaigns/remove',
                id: record.id
            },
            listeners: {
                success: {
                    fn: this.refresh,
                    scope: this
                }
            }
        });
    },

    resetFilters: function () {
        var search = Ext.getCmp('dnepritnewsletter-campaigns-search');
        var status = Ext.getCmp('dnepritnewsletter-campaigns-status-filter');

        if (search) {
            search.reset();
        }
        if (status) {
            status.reset();
        }

        this.getStore().baseParams.query = '';
        this.getStore().baseParams.status = '';
        this.getBottomToolbar().changePage(1);
    }
});

Ext.reg('dnepritnewsletter-grid-campaigns', DnepritNewsletter.grid.Campaigns);
