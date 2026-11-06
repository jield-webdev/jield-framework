<?php

declare(strict_types=1);

namespace Jield\GoogleAnalytics\View\Helper\Script;

use Jield\GoogleAnalytics\Analytics\Tracker;

interface ScriptInterface
{
    public function setTracker(Tracker $tracker);

    public function getCode(): ?string;
}
