<?php

class DnepritNewsletterHomeManagerController extends modManagerController
{
    public function process(array $scriptProperties = [])
    {
        return '';
    }

    public function getPageTitle()
    {
        return $this->modx->lexicon('dnepritnewsletter');
    }

    public function loadCustomCssJs()
    {
        /** @var DnepritNewsletter $service */
        $service = $this->modx->controller->dnepritNewsletter;

        $this->addCss($service->config['cssUrl'] . 'mgr.css');
        $this->addJavascript($service->config['jsUrl'] . 'mgr/widgets/subscribers.grid.js');
        $this->addJavascript($service->config['jsUrl'] . 'mgr/widgets/home.panel.js');
        $this->addLastJavascript($service->config['jsUrl'] . 'mgr/sections/home.js');
    }

    public function getTemplateFile()
    {
        /** @var DnepritNewsletter $service */
        $service = $this->modx->controller->dnepritNewsletter;
        return $service->config['templatesPath'] . 'mgr/home.tpl';
    }
}
