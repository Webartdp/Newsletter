(function () {
    var proto = DnepritNewsletter.grid.Campaigns.prototype;
    var originalOpenPrepareQueueWindow = proto.openPrepareQueueWindow;

    Ext.override(DnepritNewsletter.grid.Campaigns, {
        openPrepareQueueWindow: function (record, event) {
            originalOpenPrepareQueueWindow.call(this, record, event);

            if (!this.queueWindow) {
                return;
            }

            this.queueWindow.browserSenderRecord = record;

            if (this.queueWindow.browserSenderListenerBound) {
                return;
            }

            this.queueWindow.browserSenderListenerBound = true;
            this.queueWindow.on('success', function (response) {
                var data = this.getSenderResponseObject(response);
                var campaign = this.queueWindow.browserSenderRecord;

                if (!campaign || data.status !== 'queued') {
                    return;
                }

                campaign.status = 'queued';
                Ext.defer(function () {
                    this.startCampaignSending(campaign);
                }, 250, this);
            }, this);
        }
    });
}());
