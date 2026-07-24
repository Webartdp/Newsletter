<?php

class DnepritNewsletter
{
    /** @var modX */
    public $modx;

    /** @var array */
    public $config = [];

    public function __construct(modX $modx, array $config = [])
    {
        $this->modx = $modx;

        $corePath = $modx->getOption(
            'dnepritnewsletter.core_path',
            $config,
            $modx->getOption('core_path') . 'components/dnepritnewsletter/'
        );
        $assetsUrl = $modx->getOption(
            'dnepritnewsletter.assets_url',
            $config,
            $modx->getOption('assets_url') . 'components/dnepritnewsletter/'
        );

        $this->config = array_merge([
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'processorsPath' => $corePath . 'processors/',
            'controllersPath' => $corePath . 'controllers/',
            'templatesPath' => $corePath . 'elements/templates/',
            'assetsUrl' => $assetsUrl,
            'connectorUrl' => $assetsUrl . 'connector.php',
            'cssUrl' => $assetsUrl . 'css/',
            'jsUrl' => $assetsUrl . 'js/',
        ], $config);

        $this->modx->addPackage(
            'dnepritnewsletter',
            $this->config['modelPath'],
            $this->modx->getOption('table_prefix')
        );
    }
}
