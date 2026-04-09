<?php

namespace Jield\Bootstrap5\View\Helper;

class InitHighlightJs
{
    public function __invoke(): static
    {
        $this->getView()->headScript()->appendFile('assets/js/jield-bootstrap5/highlight.js');
        $this->getView()->headLink()->appendStylesheet('assets/css/jield-bootstrap5/highlight.css');

        $this->getView()->inlineScript()->appendScript(
            'jQuery(document).ready(function() {hljs.highlightAll();});'
        );

        return $this;
    }
}
