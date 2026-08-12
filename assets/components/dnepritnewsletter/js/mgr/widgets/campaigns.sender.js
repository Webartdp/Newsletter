(function () {
    var proto = DnepritNewsletter.grid.Campaigns.prototype;
    var originalGetMenu = proto.getMenu;
    var originalOnRender = proto.onRender;

    Ext.override(DnepritNewsletter.grid.Campaigns, {
        getMenu: function () {
            var menu = originalGetMenu.call(this) || [];
            var record = this.menu && this.menu.record ? this.menu.record : null;

            if (record && ['queued', 'scheduled', 'sending'].indexOf(record.status) !== -1) {
                menu.push('-');
                menu.push({
                    text: _('dnepritnewsletter_queue_start'),
                    handler: function () {
                        this.startCampaignSending(this.menu.record);
                    }
                });
            }

            return menu;
        },

        onRender: function (ct, position) {
            originalOnRender.call(this, ct, position);

            var toolbar = this.getTopToolbar();
            if (!toolbar || Ext.getCmp('dnepritnewsletter-campaign-start-sending')) {
                return;
            }

            toolbar.add('-');
            toolbar.add({
                id: 'dnepritnewsletter-campaign-start-sending',
                text: _('dnepritnewsletter_queue_start'),
                cls: 'primary-button',
                handler: function () {
                    var selection = this.getSelectionModel().getSelected();
                    if (!selection) {
                        MODx.msg.alert(_('error'), _('dnepritnewsletter_queue_err_select_campaign'));
                        return;
                    }
                    this.startCampaignSending(selection.data);
                },
                scope: this
            });
            toolbar.doLayout();
        },

        startCampaignSending: function (record) {
            if (!record || !record.id) {
                MODx.msg.alert(_('error'), _('dnepritnewsletter_queue_err_select_campaign'));
                return;
            }

            if (['queued', 'scheduled', 'sending'].indexOf(record.status) === -1) {
                MODx.msg.alert(_('error'), _('dnepritnewsletter_queue_err_not_ready'));
                return;
            }

            if (this.senderRunning) {
                MODx.msg.alert(_('warning'), _('dnepritnewsletter_queue_sender_already_running'));
                return;
            }

            this.senderRunning = true;
            this.senderStopped = false;
            this.senderCampaignId = parseInt(record.id, 10);

            Ext.MessageBox.show({
                title: _('dnepritnewsletter_queue_sending_title'),
                msg: _('dnepritnewsletter_queue_sending_start'),
                progressText: '0%',
                width: 460,
                progress: true,
                closable: false,
                buttons: Ext.MessageBox.CANCEL,
                buttonText: {
                    cancel: _('cancel')
                },
                fn: function () {
                    this.senderStopped = true;
                    this.senderRunning = false;
                },
                scope: this
            });

            this.sendCampaignBatch(this.senderCampaignId);
        },

        sendCampaignBatch: function (campaignId) {
            if (this.senderStopped || !this.senderRunning || campaignId !== this.senderCampaignId) {
                return;
            }

            MODx.Ajax.request({
                url: DnepritNewsletter.config.connectorUrl,
                params: {
                    action: 'campaigns/sendbatch',
                    id: campaignId,
                    limit: 5
                },
                listeners: {
                    success: {
                        fn: function (response) {
                            var data = this.getSenderResponseObject(response);
                            var total = parseInt(data.total || 0, 10);
                            var sent = parseInt(data.sent || 0, 10);
                            var failed = parseInt(data.failed || 0, 10);
                            var skipped = parseInt(data.skipped || 0, 10);
                            var progress = parseFloat(data.progress || 0);
                            var processed = sent + failed + skipped;

                            Ext.MessageBox.updateProgress(
                                Math.max(0, Math.min(1, progress / 100)),
                                progress + '%',
                                _('dnepritnewsletter_queue_sending_progress') + ': ' + processed + ' / ' + total
                            );

                            this.refresh();

                            if (data.complete) {
                                this.senderRunning = false;
                                this.senderStopped = true;
                                Ext.MessageBox.hide();
                                MODx.msg.alert(
                                    _('dnepritnewsletter_queue_sending_complete_title'),
                                    _('dnepritnewsletter_queue_sending_complete') + '<br>' +
                                    _('dnepritnewsletter_campaign_sent') + ': ' + sent + '<br>' +
                                    _('dnepritnewsletter_campaign_failed') + ': ' + failed + '<br>' +
                                    _('dnepritnewsletter_campaign_skipped') + ': ' + skipped
                                );
                                return;
                            }

                            if (data.waiting || data.rate_limited) {
                                Ext.MessageBox.updateProgress(
                                    Math.max(0, Math.min(1, progress / 100)),
                                    progress + '%',
                                    _('dnepritnewsletter_queue_sending_waiting')
                                );
                                Ext.defer(function () {
                                    this.sendCampaignBatch(campaignId);
                                }, 60000, this);
                                return;
                            }

                            Ext.defer(function () {
                                this.sendCampaignBatch(campaignId);
                            }, 300, this);
                        },
                        scope: this
                    },
                    failure: {
                        fn: function (response) {
                            this.senderRunning = false;
                            this.senderStopped = true;
                            Ext.MessageBox.hide();
                            MODx.msg.alert(
                                _('error'),
                                response && response.message
                                    ? response.message
                                    : _('dnepritnewsletter_queue_sending_error')
                            );
                        },
                        scope: this
                    }
                }
            });
        },

        getSenderResponseObject: function (response) {
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
}());
