<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

use Jield\Mvc\View\Helper\ServerUrl;
use Jield\Mvc\View\Helper\Url;

use function call_user_func;

class LinkUrlBuilder
{
    protected ServerUrl $serverUrlHelper;

    protected Url $urlHelper;

    public function __construct(ServerUrl $serverUrlHelper, Url $urlHelper)
    {
        $this->serverUrlHelper = $serverUrlHelper;
        $this->urlHelper = $urlHelper;
    }

    /**
     * @throws \Laminas\View\Exception\RuntimeException
     */
    public function buildLinkUrl(
        string $route,
        array $params = [],
        array $options = [],
        bool $reUseMatchedParams = false,
    ): string {
        $path = ($this->urlHelper)($route, $params, $options, $reUseMatchedParams);

        if (str_starts_with(haystack: $path, needle: 'http')) {
            return $path;
        }

        return call_user_func($this->serverUrlHelper, $path);
    }
}
