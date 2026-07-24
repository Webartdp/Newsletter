<?php

class DnepritNewsletterRenderer
{
    /** @var modX */
    protected $modx;

    public function __construct(modX $modx)
    {
        $this->modx = $modx;
    }

    public function render(array $campaign, array $subscriber)
    {
        $plainValues = [
            '[[+name]]' => trim((string)($subscriber['name'] ?? '')),
            '[[+email]]' => trim((string)($subscriber['email'] ?? '')),
            '[[+unsubscribe_url]]' => $this->buildUnsubscribeUrl(
                (string)($subscriber['unsubscribe_token'] ?? '')
            ),
            '[[+site_name]]' => (string)$this->modx->getOption('site_name', null, ''),
        ];

        $htmlValues = [];
        foreach ($plainValues as $placeholder => $value) {
            $htmlValues[$placeholder] = htmlspecialchars(
                $value,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }

        return [
            'subject' => $this->cleanHeader(strtr((string)$campaign['subject'], $plainValues)),
            'body_html' => strtr((string)$campaign['body_html'], $htmlValues),
            'body_text' => strtr((string)$campaign['body_text'], $plainValues),
        ];
    }

    protected function buildUnsubscribeUrl($token)
    {
        $token = trim((string)$token);
        $resourceId = (int)$this->modx->getOption(
            'dnepritnewsletter.unsubscribe_resource_id',
            null,
            0
        );
        $params = ['newsletter_token' => $token];

        if ($resourceId > 0) {
            return (string)$this->modx->makeUrl($resourceId, '', $params, 'full');
        }

        $siteUrl = rtrim((string)$this->modx->getOption('site_url', null, MODX_SITE_URL), '/');
        return $siteUrl . '/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    protected function cleanHeader($value)
    {
        return trim((string)preg_replace('/[\r\n]+/', ' ', (string)$value));
    }
}
