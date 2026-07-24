<?php

class DnepritNewsletterCampaignUpdateProcessor extends modObjectUpdateProcessor
{
    public $classKey = 'DnepritNewsletterCampaign';
    public $languageTopics = ['dnepritnewsletter:default'];
    public $objectType = 'dnepritnewsletter.campaign';

    public function beforeSet()
    {
        if (!$this->modx->user->sudo && !$this->modx->hasPermission('newsletter_campaigns_manage')) {
            return $this->modx->lexicon('access_denied');
        }

        if ((string)$this->object->get('status') !== 'draft') {
            return $this->modx->lexicon('dnepritnewsletter_campaign_err_locked');
        }

        $title = trim((string)$this->getProperty('title', ''));
        $subject = trim((string)$this->getProperty('subject', ''));
        $bodyHtml = $this->sanitizeHtml((string)$this->getProperty('body_html', ''));
        $bodyText = trim((string)$this->getProperty('body_text', ''));
        $senderEmail = strtolower(trim((string)$this->getProperty('sender_email', '')));
        $senderName = trim((string)$this->getProperty('sender_name', ''));
        $replyTo = strtolower(trim((string)$this->getProperty('reply_to', '')));

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

        $this->setProperties([
            'title' => $title,
            'subject' => $subject,
            'body_html' => $bodyHtml,
            'body_text' => $bodyText,
            'sender_email' => $senderEmail,
            'sender_name' => $senderName,
            'reply_to' => $replyTo,
            'status' => 'draft',
            'updated_at' => date('Y-m-d H:i:s'),
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

return 'DnepritNewsletterCampaignUpdateProcessor';
