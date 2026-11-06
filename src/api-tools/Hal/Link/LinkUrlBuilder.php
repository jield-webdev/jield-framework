<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

use Jield\Mvc\View\Helper\ServerUrl;
use Jield\Mvc\View\Helper\Url;

use function call_user_func;

class LinkUrlBuilder
{
    /** @var ServerUrl */
    protected ServerUrl $serverUrlHelper;

    /** @var Url */
    protected Url $urlHelper;

    public function __construct(ServerUrl $serverUrlHelper, Url $urlHelper)
    {
        $this->serverUrlHelper = $serverUrlHelper;
        $this->urlHelper = $urlHelper;
    }

    /**
     * @param string $route
     * @param array $params
     * @param array $options
     * @param bool $reUseMatchedParams
     * @return string
     */
    public function buildLinkUrl(
        string $route,
        array $params = [],
        array $options = [],
        bool $reUseMatchedParams = false,
    ): string {
        $path = call_user_func(\Jield\Stdlib\Value::callback($this->urlHelper), $route, $params, $options, $reUseMatchedParams);

        if (str_starts_with(haystack: \Jield\Stdlib\Value::string($path), needle: 'http')) {
            return \Jield\Stdlib\Value::string($path);
        }

        return call_user_func($this->serverUrlHelper, $path);
    }
}
