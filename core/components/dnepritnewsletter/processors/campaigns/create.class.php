<?php

class DnepritNewsletterCampaignCreateProcessor extends modObjectCreateProcessor
{
    public $classKey = 'DnepritNewsletterCampaign';
    public $languageTopics = ['dnepritnewsletter:default'];
    public $objectType = 'dnepritnewsletter.campaign';

    public function beforeSet()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_campaigns_manage')) {
            return $this->modx->lexicon('access_denied');
        }

        $title = trim((string)$this->getProperty('title', ''));
        $subject = trim((string)$this->getProperty('subject', ''));
        $bodyHtml = $this->sanitizeHtml((string)$this->getProperty('body_html', ''));
        $bodyText = trim((string)$this->getProperty('body_text', ''));
        $senderEmail = strtolower(trim((string)$this->getProperty(
            'sender_email',
            $this->modx->getOption('dnepritnewsletter.sender_email', null, '')
        )));
        $senderName = trim((string)$this->getProperty(
            'sender_name',
            $this->modx->getOption('dnepritnewsletter.sender_name', null, '')
        ));
        $replyTo = strtolower(trim((string)$this->getProperty(
            'reply_to',
            $this->modx->getOption('dnepritnewsletter.reply_to', null, '')
        )));

        if ($title === '') {
            $this->addFieldError('title', $this->modx->lexicon('dnepritnewsletter_campaign_err_title'));
        }
        if ($subject === '') {
            $this->addFieldError('subject', $this->modx->lexicon('dnepritnewsletter_campaign_err_subject'));
        }
        if (trim(strip_tags($bodyHtml)) === '' && trim($bodyHtml) === '') {
            $this->addFieldError('body_html', $this->modx->lexicon('dnepritnewsletter_campaign_err_body'));
        }
        if ($senderEmail === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFieldError('sender_email', $this->modx->lexicon('dnepritnewsletter_err_email_invalid'));
        }
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $this->addFieldError('reply_to', $this->modx->lexicon('dnepritnewsletter_err_email_invalid'));
        }

        if ($this->hasErrors()) {
            return false;
        }

        if ($bodyText === '') {
            $bodyText = $this->makeTextVersion($bodyHtml);
        }

        $now = date('Y-m-d H:i:s');
        $this->setProperties([
            'title' => $title,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'sender_email' => $senderEmail,
            'sender_name' => $senderName,
            'reply_to' => $replyTo,
            'status' => 'draft',
            'recipients_total' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => (int)$this->modx->user->get('id'),
        ]);

        return parent::beforeSet();
    }

    protected function sanitizeHtml($html)
    {
        $html = trim((string)$html);
        $html = preg_replace('#<(script|iframe|object|embed|form)[^>]*>.*?</\\1>#is', '', $html);
        $html = preg_replace('#<(script|iframe|object|embed|form)[^>]*/?>#is', '', $html);
        $html = preg_replace('/\\son[a-z]+\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s>]+)/i', '', $html);
        $html = preg_replace('/javascript\\s*:/i', '', $html);

        return $html;
    }

    protected function makeTextVersion($html)
    {
        $text = preg_replace('/<\\s*br\\s*\\/?\\s*>/i', "\n", $html);
        $text = preg_replace('/<\\/(p|div|h[1-6]|li|tr)>/i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}

return 'DnepritNewsletterCampaignCreateProcessor';
