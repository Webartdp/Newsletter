<?php

require_once __DIR__ . '/controllers/home.class.php';

class DnepritnewsletterIndexManagerController extends DnepritNewsletterHomeManagerController
{
    public static function getDefaultController()
    {
        return 'home';
    }
}
