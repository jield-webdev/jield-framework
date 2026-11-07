<?php

declare(strict_types=1);

namespace Jield\ApiTools\Hal\Extractor;

use Jield\ApiTools\ApiProblem\Exception\DomainException;
use Jield\ApiTools\Hal\Link\Link;
use Jield\ApiTools\Hal\Link\LinkUrlBuilder;
use Jield\Stdlib\Value;
use Override;

use function sprintf;

class LinkExtractor implements LinkExtractorInterface
{
    public function __construct(
        protected LinkUrlBuilder $linkUrlBuilder,
    ) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function extract(Link $link): array
    {
        if (!$link->isComplete()) {
            throw new DomainException(message: sprintf(
                'Link from resource provided to %s was incomplete; must contain a URL or a route',
                __METHOD__,
            ));
        }

        $representation = $link->getAttributes();

        if ($link->getHref() !== '') {
            $representation['href'] = $link->getHref();

            return $representation;
        }

        $reuseMatchedParams = true;
        $options = $link->getRouteOptions();
        if (isset($options['reuse_matched_params'])) {
            $reuseMatchedParams = Value::truthy($options['reuse_matched_params']);
            unset($options['reuse_matched_params']);
        }

        $representation['href'] = $this->linkUrlBuilder->buildLinkUrl(
            route: Value::string($link->getRoute()),
            params: $link->getRouteParams(),
            options: $options,
            reUseMatchedParams: $reuseMatchedParams,
        );

        return $representation;
    }
}
