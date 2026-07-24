DnepritNewsletter.panel.Home = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        id: 'dnepritnewsletter-panel-home',
        cls: 'container',
        items: [{
            html: '<h2>' + _('dnepritnewsletter') + '</h2>',
            border: false,
            cls: 'modx-page-header'
        }, {
            xtype: 'modx-tabs',
            id: 'dnepritnewsletter-home-tabs',
            stateful: true,
            stateId: 'dnepritnewsletter-home-tabs-state',
            deferredRender: false,
            items: [{
                title: _('dnepritnewsletter_subscribers'),
                layout: 'anchor',
                items: [{xtype: 'dnepritnewsletter-grid-subscribers'}]
            }, {
                title: _('dnepritnewsletter_campaigns'),
                layout: 'anchor',
                items: [{xtype: 'dnepritnewsletter-grid-campaigns'}]
            }, {
                title: _('dnepritnewsletter_queue'),
                layout: 'anchor',
                items: [{xtype: 'dnepritnewsletter-grid-queue'}]
            }, {
                title: _('dnepritnewsletter_logs'),
                layout: 'anchor',
                items: [{xtype: 'dnepritnewsletter-grid-logs'}]
            }, {
                title: _('dnepritnewsletter_settings'),
                html: '<div class="dnepritnewsletter-placeholder">' + _('dnepritnewsletter_settings') + '</div>'
            }]
        }]
    });

    DnepritNewsletter.panel.Home.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.panel.Home, MODx.Panel);
Ext.reg('dnepritnewsletter-panel-home', DnepritNewsletter.panel.Home);
