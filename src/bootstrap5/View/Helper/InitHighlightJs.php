<?php

declare(strict_types=1);

namespace Jield\Bootstrap5\View\Helper;

use Laminas\View\Helper\HeadLink;
use Laminas\View\Helper\HeadScript;
use Laminas\View\Helper\InlineScript;

final readonly class InitHighlightJs
{
    public function __construct(
        private HeadScript $headScript,
        private HeadLink $headLink,
        private InlineScript $inlineScript,
    ) {}

    public function __invoke(): static
    {
        $this->headScript->appendFile('/assets/js/jield-bootstrap5/highlight.js');
        $this->headLink->appendStylesheet('/assets/css/jield-bootstrap5/highlight.css');

        $this->inlineScript->appendScript('jQuery(document).ready(function() {hljs.highlightAll();});');

        return $this;
    }
}
