<?php

class DnepritNewsletterHomeManagerController extends modExtraManagerController
{
    /** @var DnepritNewsletter */
    public $dnepritNewsletter;

    public function initialize()
    {
        $corePath = $this->modx->getOption(
            'dnepritnewsletter.core_path',
            null,
            $this->modx->getOption('core_path') . 'components/dnepritnewsletter/'
        );

        require_once $corePath . 'model/dnepritnewsletter/dnepritnewsletter.class.php';
        $this->dnepritNewsletter = new DnepritNewsletter($this->modx);

        $this->addJavascript($this->dnepritNewsletter->config['jsUrl'] . 'mgr/dnepritnewsletter.js');
        $this->addHtml(
            '<script>Ext.onReady(function(){DnepritNewsletter.config=' .
            $this->modx->toJSON($this->dnepritNewsletter->config) .
            ';});</script>'
        );

        return parent::initialize();
    }

    public function getLanguageTopics()
    {
        return ['dnepritnewsletter:default'];
    }

    public function checkPermissions()
    {
        return (bool)($this->modx->user->sudo || $this->modx->hasPermission('newsletter_view'));
    }

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
        $this->addCss($this->dnepritNewsletter->config['cssUrl'] . 'mgr.css');
        $this->addJavascript($this->dnepritNewsletter->config['jsUrl'] . 'mgr/widgets/subscribers.window.js');
        $this->addJavascript($this->dnepritNewsletter->config['jsUrl'] . 'mgr/widgets/subscribers.import.window.js');
        $this->addJavascript($this->dnepritNewsletter->config['jsUrl'] . 'mgr/widgets/subscribers.grid.js');
        $this->addJavascript($this->dnepritNewsletter->config['jsUrl'] . 'mgr/widgets/home.panel.js');
        $this->addLastJavascript($this->dnepritNewsletter->config['jsUrl'] . 'mgr/sections/home.js');
    }

    public function getTemplateFile()
    {
        return $this->dnepritNewsletter->config['templatesPath'] . 'mgr/home.tpl';
    }
}
