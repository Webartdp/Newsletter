var DnepritNewsletter = function (config) {
    config = config || {};
    DnepritNewsletter.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter, Ext.Component, {
    page: {},
    window: {},
    grid: {},
    tree: {},
    panel: {},
    combo: {},
    util: {},
    config: {},
    connectorUrl: ''
});

Ext.reg('dnepritnewsletter', DnepritNewsletter);
DnepritNewsletter = new DnepritNewsletter();
