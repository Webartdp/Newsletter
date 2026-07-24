DnepritNewsletter.combo.SubscriberStatus = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        name: 'status',
        hiddenName: 'status',
        fieldLabel: _('dnepritnewsletter_status'),
        store: new Ext.data.ArrayStore({
            idIndex: 0,
            fields: ['value', 'label'],
            data: [
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
        value: 'active',
        anchor: '100%'
    });

    DnepritNewsletter.combo.SubscriberStatus.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.combo.SubscriberStatus, MODx.combo.ComboBox);
Ext.reg('dnepritnewsletter-combo-subscriber-status', DnepritNewsletter.combo.SubscriberStatus);

DnepritNewsletter.window.Subscriber = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        width: 520,
        autoHeight: true,
        url: DnepritNewsletter.config.connectorUrl,
        action: 'subscribers/create',
        saveBtnText: _('save'),
        fields: [{
            xtype: 'hidden',
            name: 'id'
        }, {
            xtype: 'textfield',
            name: 'email',
            fieldLabel: _('dnepritnewsletter_email'),
            allowBlank: false,
            anchor: '100%'
        }, {
            xtype: 'textfield',
            name: 'name',
            fieldLabel: _('dnepritnewsletter_name'),
            anchor: '100%'
        }, {
            xtype: 'dnepritnewsletter-combo-subscriber-status'
        }, {
            xtype: 'textarea',
            name: 'comment',
            fieldLabel: _('dnepritnewsletter_comment'),
            height: 100,
            anchor: '100%'
        }]
    });

    DnepritNewsletter.window.Subscriber.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.window.Subscriber, MODx.Window);
Ext.reg('dnepritnewsletter-window-subscriber', DnepritNewsletter.window.Subscriber);
