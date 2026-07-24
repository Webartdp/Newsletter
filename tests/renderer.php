<?php

declare(strict_types=1);

class modX
{
    private $options;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function getOption($key, $options = null, $default = null)
    {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }

    public function makeUrl($resourceId, $context = '', array $params = [], $scheme = '')
    {
        return 'https://example.test/unsubscribe?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

require_once __DIR__ . '/../core/components/dnepritnewsletter/model/dnepritnewsletter/dnepritnewsletterrenderer.class.php';

$modx = new modX([
    'site_name' => 'Example & Site',
    'site_url' => 'https://example.test/',
    'dnepritnewsletter.unsubscribe_resource_id' => 12,
]);
$renderer = new DnepritNewsletterRenderer($modx);

$result = $renderer->render([
    'subject' => "Hello [[+name]]\r\nInjected",
    'body_html' => '<p>[[+name]] — [[+site_name]]</p><a href="[[+unsubscribe_url]]">Leave</a>',
    'body_text' => "Name: [[+name]]\nEmail: [[+email]]\n[[+unsubscribe_url]]",
], [
    'name' => '<Admin>',
    'email' => 'user@example.test',
    'unsubscribe_token' => 'abc 123',
]);

$expectedUrl = 'https://example.test/unsubscribe?newsletter_token=abc%20123';

assert($result['subject'] === 'Hello <Admin> Injected');
assert(strpos($result['body_html'], '&lt;Admin&gt;') !== false);
assert(strpos($result['body_html'], 'Example &amp; Site') !== false);
assert(strpos($result['body_html'], 'newsletter_token=abc%20123') !== false);
assert(strpos($result['body_text'], '<Admin>') !== false);
assert(strpos($result['body_text'], $expectedUrl) !== false);

echo "Renderer tests passed.\n";
