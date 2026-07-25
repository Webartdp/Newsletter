<?php

require_once MODX_CORE_PATH . 'components/dnepritnewsletter/controllers/home.class.php';

class DnepritnewsletterIndexManagerController extends DnepritNewsletterHomeManagerController
{
    public static function getDefaultController()
    {
        return 'home';
    }
}
