DnepritNewsletter.grid.Subscribers = function (config) {
    config = config || {};
    this.sm = new Ext.grid.CheckboxSelectionModel();

    Ext.applyIf(config, {
        id: 'dnepritnewsletter-grid-subscribers',
        url: DnepritNewsletter.config.connectorUrl,
        baseParams: {
            action: 'subscribers/getlist'
        },
        fields: [
            'id', 'email', 'name', 'status', 'status_label', 'source', 'comment',
            'failure_count', 'subscribed_at', 'updated_at', 'unsubscribed_at'
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
            header: _('dnepritnewsletter_email'),
            dataIndex: 'email',
            width: 220,
            sortable: true
        }, {
            header: _('dnepritnewsletter_name'),
            dataIndex: 'name',
            width: 170,
            sortable: true
        }, {
            header: _('dnepritnewsletter_status'),
            dataIndex: 'status_label',
            width: 120,
            sortable: false,
            renderer: function (value, meta, record) {
                return '<span class="dnepritnewsletter-status dnepritnewsletter-status-' +
                    Ext.util.Format.htmlEncode(record.data.status) + '">' +
                    Ext.util.Format.htmlEncode(value) + '</span>';
            }
        }, {
            header: _('dnepritnewsletter_source'),
            dataIndex: 'source',
            width: 100,
            sortable: true
        }, {
            header: _('dnepritnewsletter_failure_count'),
            dataIndex: 'failure_count',
            width: 75,
            sortable: true
        }, {
            header: _('dnepritnewsletter_subscribed_at'),
            dataIndex: 'subscribed_at',
            width: 135,
            sortable: true
        }],
        tbar: [{
            text: _('dnepritnewsletter_subscriber_create'),
            cls: 'primary-button',
            handler: this.createSubscriber,
            scope: this
        }, '-', {
            xtype: 'textfield',
            id: 'dnepritnewsletter-subscribers-search',
            emptyText: _('dnepritnewsletter_search'),
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
            id: 'dnepritnewsletter-subscribers-status-filter',
            emptyText: _('dnepritnewsletter_filter_status'),
            width: 155,
            store: new Ext.data.ArrayStore({
                idIndex: 0,
                fields: ['value', 'label'],
                data: [
                    ['', _('dnepritnewsletter_all_statuses')],
                    ['active', _('dnepritnewsletter_subscriber_status_active')],
                    ['unsubscribed', _('dnepritnewsletter_subscriber_status_unsubscribed')],
                    ['blocked', _('dnepritnewsletter_subscriber_status_blocked')]
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
        }, '->', {
            text: _('dnepritnewsletter_activate_selected'),
            handler: function () {
                this.changeStatus('active');
            },
            scope: this
        }, {
            text: _('dnepritnewsletter_block_selected'),
            handler: function () {
                this.changeStatus('blocked');
            },
            scope: this
        }, {
            text: _('dnepritnewsletter_remove_selected'),
            handler: this.removeSelected,
            scope: this
        }],
        listeners: {
            rowDblClick: {
                fn: function (grid, rowIndex, event) {
                    this.openUpdateWindow(grid.getStore().getAt(rowIndex).data, event);
                },
                scope: this
            }
        }
    });

    DnepritNewsletter.grid.Subscribers.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.grid.Subscribers, MODx.grid.Grid, {
    getMenu: function () {
        return [{
            text: _('dnepritnewsletter_subscriber_update'),
            handler: function () {
                this.openUpdateWindow(this.menu.record);
            }
        }, '-', {
            text: _('dnepritnewsletter_activate'),
            handler: function () {
                this.changeStatus('active', this.menu.record);
            }
        }, {
            text: _('dnepritnewsletter_unsubscribe'),
            handler: function () {
                this.changeStatus('unsubscribed', this.menu.record);
            }
        }, {
            text: _('dnepritnewsletter_block'),
            handler: function () {
                this.changeStatus('blocked', this.menu.record);
            }
        }, '-', {
            text: _('dnepritnewsletter_remove'),
            handler: function () {
                this.removeSelected(this.menu.record);
            }
        }];
    },

    createSubscriber: function (button, event) {
        if (!this.createWindow) {
            this.createWindow = MODx.load({
                xtype: 'dnepritnewsletter-window-subscriber',
                title: _('dnepritnewsletter_subscriber_create'),
                action: 'subscribers/create',
                listeners: {
                    success: {
                        fn: this.refresh,
                        scope: this
                    }
                }
            });
        }

        this.createWindow.reset();
        this.createWindow.setValues({status: 'active'});
        this.createWindow.show(event ? event.target : null);
    },

    openUpdateWindow: function (record, event) {
        if (!record) {
            return;
        }

        if (!this.updateWindow) {
            this.updateWindow = MODx.load({
                xtype: 'dnepritnewsletter-window-subscriber',
                title: _('dnepritnewsletter_subscriber_update'),
                action: 'subscribers/update',
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

    changeStatus: function (status, record) {
        var ids = this.getSelectedIds(record);
        if (!ids.length) {
            MODx.msg.alert(_('error'), _('dnepritnewsletter_err_no_selection'));
            return;
        }

        MODx.Ajax.request({
            url: DnepritNewsletter.config.connectorUrl,
            params: {
                action: 'subscribers/changestatus',
                ids: Ext.encode(ids),
                status: status
            },
            listeners: {
                success: {
                    fn: this.refresh,
                    scope: this
                }
            }
        });
    },

    removeSelected: function (record) {
        var ids = this.getSelectedIds(record);
        if (!ids.length) {
            MODx.msg.alert(_('error'), _('dnepritnewsletter_err_no_selection'));
            return;
        }

        MODx.msg.confirm({
            title: _('dnepritnewsletter_remove'),
            text: _('dnepritnewsletter_remove_confirm'),
            url: DnepritNewsletter.config.connectorUrl,
            params: {
                action: 'subscribers/removebulk',
                ids: Ext.encode(ids)
            },
            listeners: {
                success: {
                    fn: this.refresh,
                    scope: this
                }
            }
        });
    },

    getSelectedIds: function (record) {
        if (record && record.id) {
            return [parseInt(record.id, 10)];
        }

        var selections = this.getSelectionModel().getSelections();
        var ids = [];
        Ext.each(selections, function (selection) {
            ids.push(parseInt(selection.data.id, 10));
        });
        return ids;
    },

    resetFilters: function () {
        var search = Ext.getCmp('dnepritnewsletter-subscribers-search');
        var status = Ext.getCmp('dnepritnewsletter-subscribers-status-filter');

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

Ext.reg('dnepritnewsletter-grid-subscribers', DnepritNewsletter.grid.Subscribers);
