DnepritNewsletter.grid.Logs = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        id: 'dnepritnewsletter-grid-logs',
        url: DnepritNewsletter.config.connectorUrl,
        baseParams: {
            action: 'logs/getlist'
        },
        fields: [
            'id', 'campaign_id', 'campaign_title', 'subscriber_id', 'queue_id', 'email',
            'event', 'event_label', 'level', 'level_label', 'attempt', 'message', 'message_short', 'created_at'
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
            header: _('dnepritnewsletter_queue_campaign'),
            dataIndex: 'campaign_title',
            width: 165,
            sortable: false,
            renderer: function (value, meta, record) {
                var title = value || (record.data.campaign_id ? '#' + record.data.campaign_id : '—');
                return Ext.util.Format.htmlEncode(title);
            }
        }, {
            header: _('dnepritnewsletter_email'),
            dataIndex: 'email',
            width: 190,
            sortable: true
        }, {
            header: _('dnepritnewsletter_log_event'),
            dataIndex: 'event_label',
            width: 145,
            sortable: false
        }, {
            header: _('dnepritnewsletter_log_level'),
            dataIndex: 'level_label',
            width: 90,
            sortable: false,
            renderer: function (value, meta, record) {
                return '<span class="dnepritnewsletter-log-level dnepritnewsletter-log-level-' +
                    Ext.util.Format.htmlEncode(record.data.level) + '">' +
                    Ext.util.Format.htmlEncode(value) + '</span>';
            }
        }, {
            header: _('dnepritnewsletter_log_attempt'),
            dataIndex: 'attempt',
            width: 65,
            sortable: true
        }, {
            header: _('dnepritnewsletter_log_message'),
            dataIndex: 'message_short',
            width: 300,
            sortable: false,
            renderer: function (value, meta, record) {
                var full = record.data.message || '';
                if (full) {
                    meta.attr = 'ext:qtip="' + Ext.util.Format.htmlEncode(full) + '"';
                }
                return Ext.util.Format.htmlEncode(value || '—');
            }
        }, {
            header: _('dnepritnewsletter_log_created_at'),
            dataIndex: 'created_at',
            width: 135,
            sortable: true
        }],
        tbar: [{
            xtype: 'textfield',
            id: 'dnepritnewsletter-logs-search',
            emptyText: _('dnepritnewsletter_log_search'),
            width: 210,
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
            id: 'dnepritnewsletter-logs-event-filter',
            emptyText: _('dnepritnewsletter_log_event'),
            width: 155,
            store: new Ext.data.ArrayStore({
                idIndex: 0,
                fields: ['value', 'label'],
                data: [
                    ['', _('dnepritnewsletter_all_events')],
                    ['queue_prepared', _('dnepritnewsletter_log_event_queue_prepared')],
                    ['sent', _('dnepritnewsletter_log_event_sent')],
                    ['retry_scheduled', _('dnepritnewsletter_log_event_retry_scheduled')],
                    ['manual_retry', _('dnepritnewsletter_log_event_manual_retry')],
                    ['failed', _('dnepritnewsletter_log_event_failed')],
                    ['skipped_inactive', _('dnepritnewsletter_log_event_skipped_inactive')],
                    ['public_subscribe_created', _('dnepritnewsletter_log_event_public_subscribe_created')],
                    ['public_subscribe_existing', _('dnepritnewsletter_log_event_public_subscribe_existing')],
                    ['public_subscribe_reactivated', _('dnepritnewsletter_log_event_public_subscribe_reactivated')],
                    ['public_unsubscribe', _('dnepritnewsletter_log_event_public_unsubscribe')]
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
                        this.getStore().baseParams.event = combo.getValue();
                        this.getBottomToolbar().changePage(1);
                    },
                    scope: this
                }
            }
        }, {
            xtype: 'combo',
            id: 'dnepritnewsletter-logs-level-filter',
            emptyText: _('dnepritnewsletter_log_level'),
            width: 115,
            store: new Ext.data.ArrayStore({
                idIndex: 0,
                fields: ['value', 'label'],
                data: [
                    ['', _('dnepritnewsletter_all_levels')],
                    ['info', _('dnepritnewsletter_log_level_info')],
                    ['warning', _('dnepritnewsletter_log_level_warning')],
                    ['error', _('dnepritnewsletter_log_level_error')]
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
                        this.getStore().baseParams.level = combo.getValue();
                        this.getBottomToolbar().changePage(1);
                    },
                    scope: this
                }
            }
        }, {
            xtype: 'numberfield',
            id: 'dnepritnewsletter-logs-campaign-filter',
            emptyText: _('dnepritnewsletter_queue_campaign_id'),
            width: 105,
            allowDecimals: false,
            allowNegative: false,
            enableKeyEvents: true,
            listeners: {
                keyup: {
                    fn: function (field) {
                        this.getStore().baseParams.campaign_id = field.getValue() || 0;
                        this.getBottomToolbar().changePage(1);
                    },
                    scope: this,
                    buffer: 400
                }
            }
        }, {
            text: _('dnepritnewsletter_reset'),
            handler: this.resetFilters,
            scope: this
        }],
        listeners: {
            rowDblClick: {
                fn: function (grid, rowIndex) {
                    this.showDetails(grid.getStore().getAt(rowIndex).data);
                },
                scope: this
            }
        }
    });

    DnepritNewsletter.grid.Logs.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.grid.Logs, MODx.grid.Grid, {
    getMenu: function () {
        return [{
            text: _('dnepritnewsletter_log_details'),
            handler: function () {
                this.showDetails(this.menu.record);
            }
        }];
    },

    showDetails: function (record) {
        if (!record) {
            return;
        }

        var encode = Ext.util.Format.htmlEncode;
        var rows = [
            [_('dnepritnewsletter_id'), record.id],
            [_('dnepritnewsletter_queue_campaign'), record.campaign_title || (record.campaign_id ? '#' + record.campaign_id : '—')],
            [_('dnepritnewsletter_log_queue_id'), record.queue_id || '—'],
            [_('dnepritnewsletter_email'), record.email || '—'],
            [_('dnepritnewsletter_log_event'), record.event_label || record.event],
            [_('dnepritnewsletter_log_level'), record.level_label || record.level],
            [_('dnepritnewsletter_log_attempt'), record.attempt || 0],
            [_('dnepritnewsletter_log_created_at'), record.created_at || '—']
        ];
        var html = '<div class="dnepritnewsletter-details"><table>';

        Ext.each(rows, function (row) {
            html += '<tr><th>' + encode(String(row[0])) + '</th><td>' + encode(String(row[1])) + '</td></tr>';
        });
        html += '</table><div class="dnepritnewsletter-log-message"><strong>' +
            encode(_('dnepritnewsletter_log_message')) + '</strong><pre>' +
            encode(String(record.message || '—')) + '</pre></div></div>';

        MODx.msg.alert(_('dnepritnewsletter_log_details'), html);
    },

    resetFilters: function () {
        var search = Ext.getCmp('dnepritnewsletter-logs-search');
        var event = Ext.getCmp('dnepritnewsletter-logs-event-filter');
        var level = Ext.getCmp('dnepritnewsletter-logs-level-filter');
        var campaign = Ext.getCmp('dnepritnewsletter-logs-campaign-filter');

        if (search) {
            search.reset();
        }
        if (event) {
            event.reset();
        }
        if (level) {
            level.reset();
        }
        if (campaign) {
            campaign.reset();
        }

        this.getStore().baseParams.query = '';
        this.getStore().baseParams.event = '';
        this.getStore().baseParams.level = '';
        this.getStore().baseParams.campaign_id = 0;
        this.getBottomToolbar().changePage(1);
    }
});

Ext.reg('dnepritnewsletter-grid-logs', DnepritNewsletter.grid.Logs);
