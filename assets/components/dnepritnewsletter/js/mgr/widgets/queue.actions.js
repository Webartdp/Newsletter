(function () {
    var proto = DnepritNewsletter.grid.Queue.prototype;
    var originalGetMenu = proto.getMenu;
    var originalOnRender = proto.onRender;

    Ext.override(DnepritNewsletter.grid.Queue, {
        getMenu: function () {
            var menu = originalGetMenu.call(this) || [];
            var record = this.menu && this.menu.record ? this.menu.record : null;

            if (record && record.status !== 'processing') {
                menu.push('-');
                menu.push({
                    text: _('dnepritnewsletter_queue_remove'),
                    handler: function () {
                        this.removeQueueItems(this.menu.record);
                    }
                });
            }

            return menu;
        },

        onRender: function (ct, position) {
            originalOnRender.call(this, ct, position);

            var toolbar = this.getTopToolbar();
            if (!toolbar || Ext.getCmp('dnepritnewsletter-queue-remove-selected')) {
                return;
            }

            toolbar.add({
                id: 'dnepritnewsletter-queue-remove-selected',
                text: _('dnepritnewsletter_queue_remove_selected'),
                handler: function () {
                    this.removeQueueItems();
                },
                scope: this
            });
            toolbar.doLayout();
        },

        removeQueueItems: function (record) {
            var records = record && record.id
                ? [{data: record}]
                : this.getSelectionModel().getSelections();
            var ids = [];
            var hasProcessing = false;

            Ext.each(records, function (selection) {
                var data = selection && selection.data ? selection.data : {};
                var id = parseInt(data.id, 10);

                if (data.status === 'processing') {
                    hasProcessing = true;
                    return;
                }

                if (!isNaN(id) && id > 0) {
                    ids.push(id);
                }
            });

            if (hasProcessing) {
                MODx.msg.alert(_('error'), _('dnepritnewsletter_queue_err_remove_processing'));
                return;
            }

            if (!ids.length) {
                MODx.msg.alert(_('error'), _('dnepritnewsletter_queue_err_no_selection'));
                return;
            }

            MODx.msg.confirm({
                title: _('dnepritnewsletter_queue_remove'),
                text: _('dnepritnewsletter_queue_remove_confirm').replace('[[+count]]', ids.length),
                url: DnepritNewsletter.config.connectorUrl,
                params: {
                    action: 'queue/remove',
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
        }
    });
}());