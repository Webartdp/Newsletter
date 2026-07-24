DnepritNewsletter.grid.Queue = function (config) {
    config = config || {};
    this.sm = new Ext.grid.CheckboxSelectionModel();

    Ext.applyIf(config, {
        id: 'dnepritnewsletter-grid-queue',
        url: DnepritNewsletter.config.connectorUrl,
        baseParams: {
            action: 'queue/getlist'
        },
        fields: [
            'id', 'campaign_id', 'campaign_title', 'subscriber_id', 'email', 'name', 'subject',
            'status', 'status_label', 'attempts', 'last_error', 'last_error_short', 'queued_at',
            'next_attempt_at', 'processing_at', 'sent_at', 'locked_at', 'locked_by', 'can_retry'
        ],
        paging: true,
        remoteSort: true,
        autoHeight: true,
        sm: this.sm,
        columns: [this.sm, {
            header: _('dnepritnewsletter_id'),
            dataIndex: 'id',
            width: 55,
            sortable: true
        }, {
            header: _('dnepritnewsletter_queue_campaign'),
            dataIndex: 'campaign_title',
            width: 170,
            sortable: false,
            renderer: function (value, meta, record) {
                var title = value || ('#' + record.data.campaign_id);
                return Ext.util.Format.htmlEncode(title);
            }
        }, {
            header: _('dnepritnewsletter_email'),
            dataIndex: 'email',
            width: 205,
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
            header: _('dnepritnewsletter_queue_attempts'),
            dataIndex: 'attempts',
            width: 65,
            sortable: true
        }, {
            header: _('dnepritnewsletter_queue_next_attempt'),
            dataIndex: 'next_attempt_at',
            width: 130,
            sortable: true
        }, {
            header: _('dnepritnewsletter_queue_sent_at'),
            dataIndex: 'sent_at',
            width: 130,
            sortable: true
        }, {
            header: _('dnepritnewsletter_queue_last_error'),
            dataIndex: 'last_error_short',
            width: 250,
            sortable: false,
            renderer: function (value, meta, record) {
                var full = record.data.last_error || '';
                if (full) {
                    meta.attr = 'ext:qtip="' + Ext.util.Format.htmlEncode(full) + '"';
                }
                return Ext.util.Format.htmlEncode(value || '—');
            }
        }],
        tbar: [{
            xtype: 'textfield',
            id: 'dnepritnewsletter-queue-search',
            emptyText: _('dnepritnewsletter_queue_search'),
            width: 220,
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
            id: 'dnepritnewsletter-queue-status-filter',
            emptyText: _('dnepritnewsletter_filter_status'),
            width: 145,
            store: new Ext.data.ArrayStore({
                idIndex: 0,
                fields: ['value', 'label'],
                data: [
                    ['', _('dnepritnewsletter_all_statuses')],
                    ['pending', _('dnepritnewsletter_queue_status_pending')],
                    ['processing', _('dnepritnewsletter_queue_status_processing')],
                    ['sent', _('dnepritnewsletter_queue_status_sent')],
                    ['failed', _('dnepritnewsletter_queue_status_failed')],
                    ['skipped', _('dnepritnewsletter_queue_status_skipped')]
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
            xtype: 'numberfield',
            id: 'dnepritnewsletter-queue-campaign-filter',
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
        }, '->', {
            text: _('dnepritnewsletter_queue_retry_selected'),
            handler: this.retrySelected,
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

    DnepritNewsletter.grid.Queue.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.grid.Queue, MODx.grid.Grid, {
    getMenu: function () {
        var menu = [{
            text: _('dnepritnewsletter_queue_details'),
            handler: function () {
                this.showDetails(this.menu.record);
            }
        }];

        if (this.menu.record.can_retry) {
            menu.push('-');
            menu.push({
                text: _('dnepritnewsletter_queue_retry'),
                handler: function () {
                    this.retrySelected(this.menu.record);
                }
            });
        }

        return menu;
    },

    showDetails: function (record) {
        if (!record) {
            return;
        }

        var encode = Ext.util.Format.htmlEncode;
        var rows = [
            [_('dnepritnewsletter_id'), record.id],
            [_('dnepritnewsletter_queue_campaign'), record.campaign_title || ('#' + record.campaign_id)],
            [_('dnepritnewsletter_email'), record.email || '—'],
            [_('dnepritnewsletter_name'), record.name || '—'],
            [_('dnepritnewsletter_campaign_subject'), record.subject || '—'],
            [_('dnepritnewsletter_status'), record.status_label || record.status],
            [_('dnepritnewsletter_queue_attempts'), record.attempts || 0],
            [_('dnepritnewsletter_queue_queued_at'), record.queued_at || '—'],
            [_('dnepritnewsletter_queue_next_attempt'), record.next_attempt_at || '—'],
            [_('dnepritnewsletter_queue_processing_at'), record.processing_at || '—'],
            [_('dnepritnewsletter_queue_sent_at'), record.sent_at || '—'],
            [_('dnepritnewsletter_queue_worker'), record.locked_by || '—']
        ];
        var html = '<div class="dnepritnewsletter-details"><table>';

        Ext.each(rows, function (row) {
            html += '<tr><th>' + encode(String(row[0])) + '</th><td>' + encode(String(row[1])) + '</td></tr>';
        });
        html += '</table>';

        if (record.last_error) {
            html += '<div class="dnepritnewsletter-error-box"><strong>' +
                encode(_('dnepritnewsletter_queue_last_error')) + '</strong><pre>' +
                encode(String(record.last_error)) + '</pre></div>';
        }

        html += '</div>';
        MODx.msg.alert(_('dnepritnewsletter_queue_details'), html);
    },

    retrySelected: function (record) {
        var records = record ? [{data: record}] : this.getSelectionModel().getSelections();
        var ids = [];

        Ext.each(records, function (selection) {
            if (selection.data.can_retry) {
                ids.push(parseInt(selection.data.id, 10));
            }
        });

        if (!ids.length) {
            MODx.msg.alert(_('error'), _('dnepritnewsletter_queue_err_no_failed_selection'));
            return;
        }

        MODx.msg.confirm({
            title: _('dnepritnewsletter_queue_retry'),
            text: _('dnepritnewsletter_queue_retry_confirm').replace('[[+count]]', ids.length),
            url: DnepritNewsletter.config.connectorUrl,
            params: {
                action: 'queue/retry',
                ids: Ext.encode(ids)
            },
            listeners: {
                success: {
                    fn: function () {
                        this.refresh();
                        var campaigns = Ext.getCmp('dnepritnewsletter-grid-campaigns');
                        var logs = Ext.getCmp('dnepritnewsletter-grid-logs');
                        if (campaigns) {
                            campaigns.refresh();
                        }
                        if (logs) {
                            logs.refresh();
                        }
                    },
                    scope: this
                }
            }
        });
    },

    resetFilters: function () {
        var search = Ext.getCmp('dnepritnewsletter-queue-search');
        var status = Ext.getCmp('dnepritnewsletter-queue-status-filter');
        var campaign = Ext.getCmp('dnepritnewsletter-queue-campaign-filter');

        if (search) {
            search.reset();
        }
        if (status) {
            status.reset();
        }
        if (campaign) {
            campaign.reset();
        }

        this.getStore().baseParams.query = '';
        this.getStore().baseParams.status = '';
        this.getStore().baseParams.campaign_id = 0;
        this.getBottomToolbar().changePage(1);
    }
});

Ext.reg('dnepritnewsletter-grid-queue', DnepritNewsletter.grid.Queue);
