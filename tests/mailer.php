<?php

declare(strict_types=1);

class modMail
{
    public const MAIL_BODY = 'body';
    public const MAIL_BODY_TEXT = 'body_text';
    public const MAIL_FROM = 'from';
    public const MAIL_FROM_NAME = 'from_name';
    public const MAIL_SENDER = 'sender';
    public const MAIL_SUBJECT = 'subject';
}

class FakeMail
{
    public $values = [];
    public $addresses = [];
    public $html = false;
    public $resetCount = 0;
    public $shouldSend = true;
    public $mailer;

    public function __construct()
    {
        $this->mailer = (object)['ErrorInfo' => ''];
    }

    public function set($key, $value)
    {
        $this->values[$key] = $value;
    }

    public function address($type, $email, $name = '')
    {
        $this->addresses[] = [$type, $email, $name];
        return true;
    }

    public function setHTML($value)
    {
        $this->html = (bool)$value;
    }

    public function send()
    {
        return $this->shouldSend;
    }

    public function reset()
    {
        $this->resetCount++;
    }

    public function getError()
    {
        return null;
    }
}

class modX
{
    public $mail;

    public function __construct(FakeMail $mail)
    {
        $this->mail = $mail;
    }

    public function getService($name, $class)
    {
        return $this->mail;
    }
}

function ensure($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

require_once __DIR__ . '/../core/components/dnepritnewsletter/model/dnepritnewsletter/dnepritnewslettermailer.class.php';

$mail = new FakeMail();
$modx = new modX($mail);
$adapter = new DnepritNewsletterMailer($modx, $mail);
$adapter->send([
    'email' => 'reader@example.test',
    'name' => 'Reader',
    'subject' => 'Newsletter subject',
    'body_html' => '<p>Hello</p>',
    'body_text' => 'Hello',
    'sender_email' => 'sender@example.test',
    'sender_name' => 'Sender',
    'reply_to' => 'reply@example.test',
]);

ensure($mail->values[modMail::MAIL_BODY] === '<p>Hello</p>', 'HTML body was not passed.');
ensure($mail->values[modMail::MAIL_BODY_TEXT] === 'Hello', 'Text body was not passed.');
ensure($mail->values[modMail::MAIL_FROM] === 'sender@example.test', 'From address was not passed.');
ensure($mail->values[modMail::MAIL_FROM_NAME] === 'Sender', 'From name was not passed.');
ensure($mail->values[modMail::MAIL_SENDER] === 'sender@example.test', 'Envelope sender was not passed.');
ensure($mail->values[modMail::MAIL_SUBJECT] === 'Newsletter subject', 'Subject was not passed.');
ensure($mail->addresses[0] === ['to', 'reader@example.test', 'Reader'], 'Recipient was not passed.');
ensure($mail->addresses[1] === ['reply-to', 'reply@example.test', 'Sender'], 'Reply-To was not passed.');
ensure($mail->html === true, 'HTML mode was not enabled.');
ensure($mail->resetCount === 1, 'Mailer was not reset after successful delivery.');

$failedMail = new FakeMail();
$failedMail->shouldSend = false;
$failedMail->mailer->ErrorInfo = 'SMTP unavailable';
$failedAdapter = new DnepritNewsletterMailer(new modX($failedMail), $failedMail);
$error = '';

try {
    $failedAdapter->send([
        'email' => 'reader@example.test',
        'name' => '',
        'subject' => 'Failure',
        'body_html' => '<p>Failure</p>',
        'body_text' => 'Failure',
        'sender_email' => 'sender@example.test',
        'sender_name' => 'Sender',
        'reply_to' => '',
    ]);
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
}

ensure($error === 'SMTP unavailable', 'SMTP error was not propagated.');
ensure($failedMail->resetCount === 1, 'Mailer was not reset after failed delivery.');

echo "Mailer tests passed.\n";
