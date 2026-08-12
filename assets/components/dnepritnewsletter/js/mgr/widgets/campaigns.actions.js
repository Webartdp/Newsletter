(function () {
    var proto = DnepritNewsletter.grid.Campaigns.prototype;
    var originalOnRender = proto.onRender;

    Ext.override(DnepritNewsletter.grid.Campaigns, {
        onRender: function (ct, position) {
            originalOnRender.call(this, ct, position);

            var toolbar = this.getTopToolbar();
            if (!toolbar || Ext.getCmp('dnepritnewsletter-campaign-edit-selected')) {
                return;
            }

            toolbar.insert(1, {
                id: 'dnepritnewsletter-campaign-edit-selected',
                text: _('dnepritnewsletter_campaign_update'),
                handler: this.editSelectedCampaign,
                scope: this
            });
            toolbar.insert(2, {
                id: 'dnepritnewsletter-campaign-remove-selected',
                text: _('dnepritnewsletter_campaign_remove'),
                handler: this.removeSelectedCampaign,
                scope: this
            });
            toolbar.doLayout();
        },

        getSelectedCampaignRecord: function () {
            var selection = this.getSelectionModel().getSelected();
            if (!selection) {
                MODx.msg.alert(_('error'), _('dnepritnewsletter_campaign_err_no_selection'));
                return null;
            }
            return selection.data;
        },

        editSelectedCampaign: function (button, event) {
            var record = this.getSelectedCampaignRecord();
            if (!record) {
                return;
            }
            this.openUpdateWindow(record, event);
        },

        removeSelectedCampaign: function () {
            var record = this.getSelectedCampaignRecord();
            if (!record) {
                return;
            }
            this.removeCampaign(record);
        }
    });
}());
