<?php

class DnepritNewsletterMailer
{
    /** @var modX */
    protected $modx;

    /** @var modMail|null */
    protected $mail;

    public function __construct(modX $modx, $mail = null)
    {
        $this->modx = $modx;
        $this->mail = $mail;
    }

    public function send(array $message)
    {
        $mail = $this->getMailService();
        $senderEmail = trim((string)($message['sender_email'] ?? ''));
        $senderName = trim((string)($message['sender_name'] ?? ''));
        $replyTo = trim((string)($message['reply_to'] ?? ''));
        $recipientEmail = trim((string)($message['email'] ?? ''));
        $recipientName = trim((string)($message['name'] ?? ''));

        if (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid sender email address.');
        }
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid recipient email address.');
        }
        if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid reply-to email address.');
        }

        try {
            $mail->set(modMail::MAIL_BODY, (string)($message['body_html'] ?? ''));
            $mail->set(modMail::MAIL_BODY_TEXT, (string)($message['body_text'] ?? ''));
            $mail->set(modMail::MAIL_FROM, $senderEmail);
            $mail->set(modMail::MAIL_FROM_NAME, $senderName);
            $mail->set(modMail::MAIL_SENDER, $senderEmail);
            $mail->set(modMail::MAIL_SUBJECT, (string)($message['subject'] ?? ''));

            if (!$mail->address('to', $recipientEmail, $recipientName)) {
                throw new RuntimeException('Could not add recipient address.');
            }
            if ($replyTo !== '' && !$mail->address('reply-to', $replyTo, $senderName)) {
                throw new RuntimeException('Could not add reply-to address.');
            }

            $mail->setHTML(true);
            if (!$mail->send()) {
                throw new RuntimeException($this->extractError($mail));
            }
        } finally {
            $mail->reset();
        }

        return true;
    }

    protected function getMailService()
    {
        if ($this->mail) {
            return $this->mail;
        }

        if (!$this->modx->getService('mail', 'mail.modPHPMailer')) {
            throw new RuntimeException('MODX mail service could not be loaded.');
        }

        $this->mail = $this->modx->mail;
        return $this->mail;
    }

    protected function extractError($mail)
    {
        if (isset($mail->mailer) && is_object($mail->mailer)) {
            $errorInfo = trim((string)($mail->mailer->ErrorInfo ?? ''));
            if ($errorInfo !== '') {
                return $errorInfo;
            }
        }

        if (method_exists($mail, 'getError')) {
            $error = $mail->getError();
            if ($error && method_exists($error, 'getErrors')) {
                $errors = $error->getErrors();
                if (is_array($errors) && $errors) {
                    return implode('; ', array_map('strval', $errors));
                }
            }
        }

        return 'Mail transport rejected the message.';
    }
}
