DnepritNewsletter.window.Campaign = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        width: 900,
        autoHeight: true,
        modal: true,
        url: DnepritNewsletter.config.connectorUrl,
        closeAction: 'hide',
        fields: [{
            xtype: 'hidden',
            name: 'id'
        }, {
            xtype: 'textfield',
            name: 'title',
            fieldLabel: _('dnepritnewsletter_campaign_title'),
            anchor: '100%',
            allowBlank: false
        }, {
            xtype: 'textfield',
            name: 'subject',
            fieldLabel: _('dnepritnewsletter_campaign_subject'),
            anchor: '100%',
            allowBlank: false
        }, {
            xtype: 'fieldset',
            title: _('dnepritnewsletter_campaign_sender'),
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
                allowBlank: false,
                vtype: 'email'
            }, {
                xtype: 'textfield',
                name: 'reply_to',
                fieldLabel: _('dnepritnewsletter_campaign_reply_to'),
                vtype: 'email'
            }]
        }, {
            xtype: 'modx-tabs',
            deferredRender: false,
            height: 455,
            anchor: '100%',
            items: [{
                title: _('dnepritnewsletter_campaign_html'),
                layout: 'fit',
                bodyStyle: 'padding: 8px;',
                items: [{
                    xtype: 'htmleditor',
                    name: 'body_html',
                    allowBlank: false,
                    enableSourceEdit: true,
                    anchor: '100%',
                    height: 390
                }]
            }, {
                title: _('dnepritnewsletter_campaign_text'),
                layout: 'fit',
                bodyStyle: 'padding: 8px;',
                items: [{
                    xtype: 'textarea',
                    name: 'body_text',
                    emptyText: _('dnepritnewsletter_campaign_text_auto'),
                    anchor: '100%',
                    height: 390
                }]
            }, {
                title: _('dnepritnewsletter_campaign_placeholders'),
                autoScroll: true,
                html: '<div class="dnepritnewsletter-placeholders">' +
                    '<h3>' + _('dnepritnewsletter_campaign_placeholders') + '</h3>' +
                    '<p><code>[[+name]]</code> — ' + _('dnepritnewsletter_placeholder_name') + '</p>' +
                    '<p><code>[[+email]]</code> — ' + _('dnepritnewsletter_placeholder_email') + '</p>' +
                    '<p><code>[[+unsubscribe_url]]</code> — ' + _('dnepritnewsletter_placeholder_unsubscribe') + '</p>' +
                    '<p><code>[[+site_name]]</code> — ' + _('dnepritnewsletter_placeholder_site') + '</p>' +
                    '<p class="dnepritnewsletter-help">' + _('dnepritnewsletter_campaign_placeholder_help') + '</p>' +
                    '</div>'
            }]
        }]
    });

    DnepritNewsletter.window.Campaign.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.window.Campaign, MODx.Window);
Ext.reg('dnepritnewsletter-window-campaign', DnepritNewsletter.window.Campaign);
