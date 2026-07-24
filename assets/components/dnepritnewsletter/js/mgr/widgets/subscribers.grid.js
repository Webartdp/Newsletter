DnepritNewsletter.grid.Subscribers = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        id: 'dnepritnewsletter-grid-subscribers',
        url: DnepritNewsletter.config.connectorUrl,
        baseParams: {
            action: 'subscribers/getlist'
        },
        fields: ['id', 'email', 'name', 'status', 'status_label', 'source', 'subscribed_at'],
        paging: true,
        remoteSort: true,
        autoHeight: true,
        columns: [
            {header: _('dnepritnewsletter_id'), dataIndex: 'id', width: 60, sortable: true},
            {header: _('dnepritnewsletter_email'), dataIndex: 'email', width: 220, sortable: true},
            {header: _('dnepritnewsletter_name'), dataIndex: 'name', width: 180, sortable: true},
            {header: _('dnepritnewsletter_status'), dataIndex: 'status_label', width: 120, sortable: false},
            {header: _('dnepritnewsletter_source'), dataIndex: 'source', width: 110, sortable: true},
            {header: _('dnepritnewsletter_subscribed_at'), dataIndex: 'subscribed_at', width: 140, sortable: true}
        ],
        tbar: [{
            xtype: 'textfield',
            id: 'dnepritnewsletter-subscribers-search',
            emptyText: _('dnepritnewsletter_search'),
            width: 260,
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
        }]
    });

    DnepritNewsletter.grid.Subscribers.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.grid.Subscribers, MODx.grid.Grid);
Ext.reg('dnepritnewsletter-grid-subscribers', DnepritNewsletter.grid.Subscribers);
