DnepritNewsletter.window.SubscriberImportUpload = function (config) {
    config = config || {};

    Ext.applyIf(config, {
        title: _('dnepritnewsletter_import_title'),
        width: 520,
        autoHeight: true,
        url: DnepritNewsletter.config.connectorUrl,
        action: 'subscribers/importpreview',
        fileUpload: true,
        saveBtnText: _('dnepritnewsletter_import_preview'),
        fields: [{
            xtype: 'displayfield',
            hideLabel: true,
            value: _('dnepritnewsletter_import_help')
        }, {
            xtype: 'fileuploadfield',
            fieldLabel: _('dnepritnewsletter_import_file'),
            name: 'file',
            anchor: '100%',
            allowBlank: false,
            buttonText: _('dnepritnewsletter_import_choose_file')
        }]
    });

    DnepritNewsletter.window.SubscriberImportUpload.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.window.SubscriberImportUpload, MODx.Window);
Ext.reg('dnepritnewsletter-window-subscriber-import-upload', DnepritNewsletter.window.SubscriberImportUpload);

DnepritNewsletter.window.SubscriberImportMapping = function (config) {
    config = config || {};
    var data = config.importData || {};
    var columnData = [];
    var noNameData = [[-1, _('dnepritnewsletter_import_no_name_column')]];
    var rows = data.rows || [];
    var firstRow = rows.length ? rows[0] : [];

    for (var index = 0; index < (data.columns || 0); index++) {
        var sample = typeof firstRow[index] !== 'undefined' ? String(firstRow[index]) : '';
        var label = _('dnepritnewsletter_import_column') + ' ' + (index + 1);
        if (sample) {
            label += ' — ' + Ext.util.Format.ellipsis(sample, 40);
        }
        columnData.push([index, label]);
    }

    var emailStore = new Ext.data.ArrayStore({
        idIndex: 0,
        fields: ['value', 'label'],
        data: columnData
    });

    var nameStore = new Ext.data.ArrayStore({
        idIndex: 0,
        fields: ['value', 'label'],
        data: noNameData.concat(columnData)
    });

    Ext.applyIf(config, {
        title: _('dnepritnewsletter_import_mapping_title'),
        width: 760,
        autoHeight: true,
        url: DnepritNewsletter.config.connectorUrl,
        action: 'subscribers/import',
        saveBtnText: _('dnepritnewsletter_import_start'),
        fields: [{
            xtype: 'hidden',
            name: 'token',
            value: data.token || ''
        }, {
            xtype: 'hidden',
            name: 'extension',
            value: data.extension || ''
        }, {
            xtype: 'hidden',
            name: 'delimiter',
            value: data.delimiter || 'single'
        }, {
            xtype: 'displayfield',
            fieldLabel: _('dnepritnewsletter_import_filename'),
            value: Ext.util.Format.htmlEncode(data.filename || '')
        }, {
            xtype: 'checkbox',
            name: 'has_header',
            inputValue: 1,
            fieldLabel: _('dnepritnewsletter_import_has_header'),
            checked: data.has_header === true || data.has_header === 1
        }, {
            xtype: 'combo',
            name: 'email_column_display',
            hiddenName: 'email_column',
            fieldLabel: _('dnepritnewsletter_import_email_column'),
            store: emailStore,
            mode: 'local',
            triggerAction: 'all',
            displayField: 'label',
            valueField: 'value',
            editable: false,
            allowBlank: false,
            value: typeof data.email_column !== 'undefined' ? data.email_column : 0,
            anchor: '100%'
        }, {
            xtype: 'combo',
            name: 'name_column_display',
            hiddenName: 'name_column',
            fieldLabel: _('dnepritnewsletter_import_name_column'),
            store: nameStore,
            mode: 'local',
            triggerAction: 'all',
            displayField: 'label',
            valueField: 'value',
            editable: false,
            value: typeof data.name_column !== 'undefined' ? data.name_column : -1,
            anchor: '100%'
        }, {
            xtype: 'combo',
            name: 'duplicate_mode_display',
            hiddenName: 'duplicate_mode',
            fieldLabel: _('dnepritnewsletter_import_duplicates'),
            store: new Ext.data.ArrayStore({
                idIndex: 0,
                fields: ['value', 'label'],
                data: [
                    ['skip', _('dnepritnewsletter_import_duplicates_skip')],
                    ['update', _('dnepritnewsletter_import_duplicates_update')]
                ]
            }),
            mode: 'local',
            triggerAction: 'all',
            displayField: 'label',
            valueField: 'value',
            editable: false,
            value: 'skip',
            anchor: '100%'
        }, {
            xtype: 'checkbox',
            name: 'reactivate_unsubscribed',
            inputValue: 1,
            fieldLabel: _('dnepritnewsletter_import_reactivate'),
            checked: false
        }, {
            xtype: 'panel',
            border: false,
            cls: 'dnepritnewsletter-import-preview',
            html: DnepritNewsletter.util.buildImportPreview(rows)
        }]
    });

    DnepritNewsletter.window.SubscriberImportMapping.superclass.constructor.call(this, config);
};

Ext.extend(DnepritNewsletter.window.SubscriberImportMapping, MODx.Window);
Ext.reg('dnepritnewsletter-window-subscriber-import-mapping', DnepritNewsletter.window.SubscriberImportMapping);

DnepritNewsletter.util.buildImportPreview = function (rows) {
    var html = '<div class="dnepritnewsletter-import-preview-title">' +
        Ext.util.Format.htmlEncode(_('dnepritnewsletter_import_preview_title')) + '</div>';
    html += '<div class="dnepritnewsletter-import-preview-scroll"><table><tbody>';

    Ext.each(rows || [], function (row, rowIndex) {
        html += '<tr><th>' + (rowIndex + 1) + '</th>';
        Ext.each(row, function (cell) {
            html += '<td>' + Ext.util.Format.htmlEncode(String(cell || '')) + '</td>';
        });
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    return html;
};

DnepritNewsletter.util.getResponseObject = function (response) {
    if (response && response.a && response.a.result && response.a.result.object) {
        return response.a.result.object;
    }
    if (response && response.object) {
        return response.object;
    }
    return {};
};
