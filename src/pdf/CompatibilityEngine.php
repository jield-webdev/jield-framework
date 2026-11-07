<?php

namespace Jield\Pdf;

// Adapted from TCPDF 7.0.21, copyright Tecnick.com LTD, LGPL-3.0-or-later.
/**
 * @class CompatibilityEngine
 * tc-lib-pdf engine specialization used by the Tcpdf facade.
 *
 * Every page content stream starts from the PDF default graphics state, and
 * the engine re-emits only the current font when it opens a page context
 * (explicit addPage() and the automatic page breaks performed inside its
 * flowing methods such as addTextCell()). The legacy Tcpdf API instead
 * carries ambient text state (text color, font spacing/stretching) across
 * page breaks, so this subclass lets the facade re-emit that state at the
 * start of every page the engine opens.
 *
 * @package com.tecnick.tcpdf
 *
 * @phpstan-import-type TBBox from \Com\Tecnick\Pdf\Base
 */
// The single-file layout (tcpdf.php as the only historical include) and the
// legacy ALL-CAPS underscore class naming (TCPDF_STATIC, TCPDF_FONTS, ...)
// are part of the Tcpdf compatibility contract and cannot change.
// @mago-expect lint:single-class-per-file
// @mago-expect lint:class-name
class CompatibilityEngine extends \Com\Tecnick\Pdf\Tcpdf
{
    /**
     * Facade callback returning the raw PDF operators for the ambient text
     * state to prepend to every new page content stream.
     *
     * @var (\Closure(): string)|null
     */
    public ?\Closure $pagecontexthook = null;

    public function setPageContext(int $pid = -1): void
    {
        $this->anchorPageRegionsToContentTop($pid);
        // The engine declares the protected implementation through a public
        // @method tag, which the analyzer resolves before the real method.
        parent::setPageContext($pid);
        if ($this->pagecontexthook instanceof \Closure) {
            // The hook is a public property: the cast defensively coerces a
            // non-string return, which the analyzer rejects as redundant because
            // the @var closure type already promises a string.
            // @mago-expect analysis:redundant-cast
            $content = (string) ($this->pagecontexthook)();
            if ($content !== '') {
                $this->page->addContent($content, $pid);
            }
        }
    }

    /**
     * Tallies of the open cell bounding-box windows, keyed by window id: the
     * number of cell boxes added and the largest bottom edge ordinate (user
     * units) among them.
     *
     * @var array<int, array{count: int, bottom: float}>
     */
    private array $cellBBoxWindows = [];

    /** Id of the last opened cell bounding-box window. */
    private int $cellBBoxWindowId = 0;

    /**
     * Start counting the cell bounding boxes added by the engine.
     *
     * @return int Window id to pass to closeCellBBoxWindow().
     */
    public function openCellBBoxWindow(): int
    {
        $this->cellBBoxWindows[++$this->cellBBoxWindowId] = ['count' => 0, 'bottom' => -INF];

        return $this->cellBBoxWindowId;
    }

    /**
     * Stop counting and return the number of cell bounding boxes added since
     * the window was opened, and their largest bottom edge ordinate (-INF when
     * none was added).
     *
     * @return array{count: int, bottom: float}
     */
    public function closeCellBBoxWindow(int $window): array
    {
        $tally = $this->cellBBoxWindows[$window] ?? ['count' => 0, 'bottom' => -INF];
        unset($this->cellBBoxWindows[$window]);

        return $tally;
    }

    /**
     * Adds the cell box to the tally of every open window.
     *
     * @param TBBox $box Cell bounding box in user units.
     */
    protected function pushCellBBox(array $box): void
    {
        parent::pushCellBBox($box);
        foreach ($this->cellBBoxWindows as $window => $tally) {
            $this->cellBBoxWindows[$window] = [
                'count' => $tally['count'] + 1,
                'bottom' => max($tally['bottom'], $box['y'] + $box['h']),
            ];
        }
    }

    /**
     * Anchor the page regions (columns) at the content top margin.
     *
     * Pages opened by the engine's internal flows clone the previous page
     * data, including regions that may start mid-page (legacy columns set
     * on the page where they were defined): on every following page the
     * columns restart at the top margin (legacy selectColumn() behavior).
     * The region list of an existing page has no mutator, so the adjusted
     * data is written directly into the page store.
     */
    protected function anchorPageRegionsToContentTop(int $pid): void
    {
        $pagedata = $this->page->getPage($pid);
        $regions = $pagedata['region'];
        $margintop = $pagedata['margin']['CT'];
        $pageheight = $pagedata['height'];
        $contentheight = $pageheight - $margintop - $pagedata['margin']['CB'];
        $changed = false;
        foreach ($regions as $idx => $region) {
            if (abs($region['RY'] - $margintop) < 0.0001 && abs($region['RH'] - $contentheight) < 0.0001) {
                continue;
            }

            $region['RY'] = $margintop;
            $region['RH'] = $contentheight;
            $region['RT'] = $margintop + $contentheight;
            $region['RB'] = $pageheight - $margintop - $contentheight;
            $region['y'] = $margintop;
            $regions[$idx] = $region;
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Page\Settings::class, 'page');
        $pages = $prop->getValue($this->page);
        if (!is_array($pages)) {
            return;
        }

        /** @var array<int, array<string, mixed>> $pages */
        $pages[(int) $pagedata['pid']]['region'] = $regions;
        $prop->setValue($this->page, $pages);
    }
}
