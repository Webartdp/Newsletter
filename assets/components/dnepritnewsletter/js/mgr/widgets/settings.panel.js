DnepritNewsletter.panel.Settings = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        id: 'dnepritnewsletter-panel-settings',
        url: DnepritNewsletter.config.connectorUrl,
        baseParams: {
            action: 'settings/update'
        },
        border: false,
        bodyStyle: 'padding: 18px;',
        labelWidth: 245,
        items: [{
            xtype: 'fieldset',
            title: _('dnepritnewsletter_settings_sender'),
            anchor: '100%',
            defaults: {
                anchor: '100%'
            },
            items: [{
                xtype: 'textfield',
                name: 'sender_name',
                fieldLabel: _('dnepritnewsletter_campaign_sender_name')
            }, {
                xtype: 'textfield',
                name: 'sender_email',
                fieldLabel: _('dnepritnewsletter_campaign_sender_email'),
                vtype: 'email'
            }, {
                xtype: 'textfield',
                name: 'reply_to',
                fieldLabel: _('dnepritnewsletter_campaign_reply_to'),
                vtype: 'email'
            }]
        }, {
            xtype: 'fieldset',
            title: _('dnepritnewsletter_settings_delivery'),
            anchor: '100%',
            defaults: {
                xtype: 'numberfield',
                anchor: '100%',
                allowDecimals: false,
                allowNegative: false
            },
            items: [{
                name: 'batch_size',
                fieldLabel: _('dnepritnewsletter_settings_batch_size'),
                minValue: 1,
                maxValue: 1000
            }, {
                name: 'limit_per_minute',
                fieldLabel: _('dnepritnewsletter_settings_limit_per_minute'),
                minValue: 0,
                maxValue: 10000
            }, {
                name: 'limit_per_hour',
                fieldLabel: _('dnepritnewsletter_settings_limit_per_hour'),
                minValue: 0,
                maxValue: 1000000
            }, {
                name: 'max_attempts',
                fieldLabel: _('dnepritnewsletter_settings_max_attempts'),
                minValue: 1,
                maxValue: 20
            }, {
                name: 'retry_delay',
                fieldLabel: _('dnepritnewsletter_settings_retry_delay'),
                minValue: 60,
                maxValue: 86400
            }, {
                name: 'lock_ttl',
                fieldLabel: _('dnepritnewsletter_settings_lock_ttl'),
                minValue: 300,
                maxValue: 86400
            }, {
                name: 'failure_limit',
                fieldLabel: _('dnepritnewsletter_settings_failure_limit'),
                minValue: 1,
                maxValue: 100
            }]
        }, {
            xtype: 'fieldset',
            title: _('dnepritnewsletter_settings_subscription'),
            anchor: '100%',
            defaults: {
                anchor: '100%'
            },
            items: [{
                xtype: 'numberfield',
                name: 'unsubscribe_resource_id',
                fieldLabel: _('dnepritnewsletter_settings_unsubscribe_resource_id'),
                allowDecimals: false,
                allowNegative: false,
                minValue: 0
            }, {
                xtype: 'numberfield',
                name: 'import_max_size_mb',
                fieldLabel: _('dnepritnewsletter_settings_import_max_size_mb'),
                allowDecimals: false,
                allowNegative: false,
                minValue: 1,
                maxValue: 100
            }, {
                xtype: 'xcheckbox',
                name: 'require_consent',
                inputValue: 1,
                uncheckedValue: 0,
                boxLabel: _('dnepritnewsletter_settings_require_consent')
            }, {
                xtype: 'xcheckbox',
                name: 'reactivate_unsubscribed',
                inputValue: 1,
                uncheckedValue: 0,
                boxLabel: _('dnepritnewsletter_settings_reactivate_unsubscribed')
            }, {
                xtype: 'numberfield',
                name: 'subscribe_min_seconds',
                fieldLabel: _('dnepritnewsletter_settings_subscribe_min_seconds'),
                allowDecimals: false,
                allowNegative: false,
                minValue: 0,
                maxValue: 3600
            }, {
                xtype: 'numberfield',
                name: 'subscribe_token_ttl',
                fieldLabel: _('dnepritnewsletter_settings_subscribe_token_ttl'),
                allowDecimals: false,
                allowNegative: false,
                minValue: 300,
                maxValue: 604800
            }, {
                xtype: 'numberfield',
                name: 'subscribe_ip_limit',
                fieldLabel: _('dnepritnewsletter_settings_subscribe_ip_limit'),
                allowDecimals: false,
                allowNegative: false,
                minValue: 0,
                maxValue: 10000
            }, {
                xtype: 'numberfield',
                name: 'subscribe_ip_window',
                fieldLabel: _('dnepritnewsletter_settings_subscribe_ip_window'),
                allowDecimals: false,
                allowNegative: false,
                minValue: 60,
                maxValue: 86400
            }, {
                xtype: 'numberfield',
                name: 'subscribe_email_limit',
                fieldLabel: _('dnepritnewsletter_settings_subscribe_email_limit'),
                allowDecimals: false,
                allowNegative: false,
                minValue: 0,
                maxValue: 10000
            }, {
                xtype: 'numberfield',
                name: 'subscribe_email_window',
                fieldLabel: _('dnepritnewsletter_settings_subscribe_email_window'),
                allowDecimals: false,
                allowNegative: false,
                minValue: 60,
                maxValue: 86400
            }]
        }, {
            xtype: 'fieldset',
            title: _('dnepritnewsletter_settings_mail_status'),
            anchor: '100%',
            defaults: {
                anchor: '100%',
                disabled: true
            },
            items: [{
                xtype: 'xcheckbox',
                name: 'mail_use_smtp',
                inputValue: 1,
                boxLabel: _('dnepritnewsletter_settings_mail_use_smtp')
            }, {
                xtype: 'textfield',
                name: 'mail_smtp_hosts',
                fieldLabel: _('dnepritnewsletter_settings_mail_smtp_hosts')
            }, {
                xtype: 'numberfield',
                name: 'mail_smtp_port',
                fieldLabel: _('dnepritnewsletter_settings_mail_smtp_port')
            }, {
                xtype: 'textfield',
                name: 'mail_smtp_user',
                fieldLabel: _('dnepritnewsletter_settings_mail_smtp_user')
            }, {
                xtype: 'displayfield',
                value: _('dnepritnewsletter_settings_mail_help')
            }],
            buttons: [{
                text: _('dnepritnewsletter_settings_open_mail'),
                handler: function () {
                    MODx.loadPage('system/settings', 'namespace=core');
                }
            }]
        }],
        buttons: [{
            text: _('save'),
            cls: 'primary-button',
            handler: function () {
                this.submit({});
            },
            scope: this
        }, {
            text: _('dnepritnewsletter_settings_reload'),
            handler: function () {
                this.loadSettings();
            },
            scope: this
        }],
        listeners: {
            afterrender: {
                fn: function () {
                    this.loadSettings();
                },
                scope: this,
                single: true
            },
            success: {
                fn: function () {
                    MODx.msg.status({
                        title: _('success'),
                        message: _('dnepritnewsletter_settings_saved')
                    });
                    this.loadSettings();
                },
                scope: this
            }
        }
    });

    DnepritNewsletter.panel.Settings.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.panel.Settings, MODx.FormPanel, {
    loadSettings: function () {
        MODx.Ajax.request({
            url: DnepritNewsletter.config.connectorUrl,
            params: {
                action: 'settings/get'
            },
            listeners: {
                success: {
                    fn: function (response) {
                        var data = this.getResponseObject(response);
                        this.getForm().setValues(data);
                    },
                    scope: this
                }
            }
        });
    },

    getResponseObject: function (response) {
        if (!response) {
            return {};
        }
        if (response.object) {
            return response.object;
        }
        if (response.result && response.result.object) {
            return response.result.object;
        }
        if (response.responseText) {
            try {
                var decoded = Ext.decode(response.responseText);
                return decoded.object || (decoded.result && decoded.result.object) || decoded;
            } catch (e) {
                return {};
            }
        }
        return {};
    }
});

Ext.reg('dnepritnewsletter-panel-settings', DnepritNewsletter.panel.Settings);
