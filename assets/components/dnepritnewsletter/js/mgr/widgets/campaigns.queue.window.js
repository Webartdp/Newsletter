DnepritNewsletter.window.PrepareQueue = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        width: 520,
        autoHeight: true,
        modal: true,
        url: DnepritNewsletter.config.connectorUrl,
        closeAction: 'hide',
        fields: [{
            xtype: 'hidden',
            name: 'id'
        }, {
            xtype: 'checkbox',
            name: 'send_now',
            inputValue: 1,
            uncheckedValue: 0,
            checked: true,
            boxLabel: _('dnepritnewsletter_queue_send_now'),
            listeners: {
                check: function (field, checked) {
                    var dateField = Ext.getCmp('dnepritnewsletter-queue-scheduled-date');
                    var timeField = Ext.getCmp('dnepritnewsletter-queue-scheduled-time');

                    if (dateField) {
                        dateField.setDisabled(checked);
                    }
                    if (timeField) {
                        timeField.setDisabled(checked);
                    }
                }
            }
        }, {
            xtype: 'compositefield',
            fieldLabel: _('dnepritnewsletter_queue_schedule'),
            anchor: '100%',
            items: [{
                xtype: 'datefield',
                id: 'dnepritnewsletter-queue-scheduled-date',
                name: 'scheduled_date',
                format: 'Y-m-d',
                altFormats: 'd.m.Y|d-m-Y|Y-m-d',
                width: 150,
                disabled: true,
                minValue: new Date()
            }, {
                xtype: 'timefield',
                id: 'dnepritnewsletter-queue-scheduled-time',
                name: 'scheduled_time',
                format: 'H:i',
                increment: 15,
                width: 110,
                disabled: true
            }]
        }, {
            xtype: 'displayfield',
            value: '<div class="dnepritnewsletter-queue-warning">' +
                _('dnepritnewsletter_queue_warning') +
                '</div>'
        }]
    });

    DnepritNewsletter.window.PrepareQueue.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.window.PrepareQueue, MODx.Window);
Ext.reg('dnepritnewsletter-window-prepare-queue', DnepritNewsletter.window.PrepareQueue);
