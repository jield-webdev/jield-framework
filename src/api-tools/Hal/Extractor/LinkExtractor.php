<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Extractor;

use Jield\ApiTools\ApiProblem\Exception\DomainException;
use Jield\ApiTools\Hal\Link\Link;
use Jield\ApiTools\Hal\Link\LinkUrlBuilder;

use Override;
use function sprintf;

class LinkExtractor implements LinkExtractorInterface
{
    /** @var LinkUrlBuilder */
    protected LinkUrlBuilder $linkUrlBuilder;

    public function __construct(LinkUrlBuilder $linkUrlBuilder)
    {
        $this->linkUrlBuilder = $linkUrlBuilder;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function extract(Link $link): array
    {
        if (! $link->isComplete()) {
            throw new DomainException(message: sprintf(
                'Link from resource provided to %s was incomplete; must contain a URL or a route',
                __METHOD__
            ));
        }

        $representation = $link->getAttributes();

        if ($link->hasUrl()) {
            $representation['href'] = $link->getHref();

            return $representation;
        }

        $reuseMatchedParams = true;
        $options            = $link->getRouteOptions();
        if (isset($options['reuse_matched_params'])) {
            $reuseMatchedParams = (bool) $options['reuse_matched_params'];
            unset($options['reuse_matched_params']);
        }

        $representation['href'] = $this->linkUrlBuilder->buildLinkUrl(
            route: $link->getRoute(),
            params: $link->getRouteParams(),
            options: $options,
            reUseMatchedParams: $reuseMatchedParams
        );

        return $representation;
    }
}
