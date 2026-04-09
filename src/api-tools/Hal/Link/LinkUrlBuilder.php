<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Link;

use Laminas\View\Helper\ServerUrl;
use Laminas\View\Helper\Url;

use function call_user_func;
use function substr;

class LinkUrlBuilder
{
    /** @var ServerUrl */
    protected ServerUrl $serverUrlHelper;

    /** @var Url */
    protected Url $urlHelper;

    public function __construct(ServerUrl $serverUrlHelper, Url $urlHelper)
    {
        $this->serverUrlHelper = $serverUrlHelper;
        $this->urlHelper       = $urlHelper;
    }

    /**
     * @param string $route
     * @param array $params
     * @param array $options
     * @param bool $reUseMatchedParams
     * @return string
     */
    public function buildLinkUrl(string $route, array $params = [], array $options = [], bool $reUseMatchedParams = false): string
    {
        $path = call_user_func(
            $this->urlHelper,
            $route,
            $params,
            $options,
            $reUseMatchedParams
        );

        if (str_starts_with(haystack: $path, needle: 'http')) {
            return $path;
        }

        return call_user_func($this->serverUrlHelper, $path);
    }
}
