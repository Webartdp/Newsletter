<?php

class DnepritNewsletterManagerController extends modExtraManagerController
{
    public function getLanguageTopics()
    {
        return ['dnepritnewsletter:default'];
    }

    public function checkPermissions()
    {
        return (bool)($this->modx->user->sudo || $this->modx->hasPermission('newsletter_view'));
    }

    public static function getDefaultController()
    {
        return 'home';
    }
}
