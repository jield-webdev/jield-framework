<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\View\Helper;

use Jield\GoogleAnalytics\View\Helper\Script\ScriptInterface;
use Jield\Mvc\View\Helper\AbstractHelper;
use Laminas\View\Helper\HeadScript;

class GoogleAnalytics extends AbstractHelper
{
    protected string $containerName = 'HeadScript';

    protected bool $rendered = false;

    public function __construct(
        protected ScriptInterface $script,
        protected string $id,
    ) {}

    public function getContainerName(): string
    {
        return $this->containerName;
    }

    public function setContainerName(string $container): GoogleAnalytics
    {
        $this->containerName = $container;

        return $this;
    }

    protected function getContainer(): HeadScript
    {
        $containerName = $this->getContainerName();
        return $this->getView()->plugin(name: $containerName, type: HeadScript::class);
    }

    public function __invoke(): GoogleAnalytics
    {
        return $this;
    }

    public function appendScript(): void
    {
        // Do not render the GA twice
        if ($this->rendered) {
            return;
        }

        $code = $this->script->getCode();
        if ($code === null || $code === '' || $code === '0') {
            return;
        }

        $container = $this->getContainer();
        $container->appendFile(
            '//www.googletagmanager.com/gtag/js?id=' . $this->id,
            ['type' => 'text/javascript'] + ['async' => true],
        );
        $container->appendScript($code);

        // Mark this GA as rendered
        $this->rendered = true;
    }
}
