<?php

namespace Jield\Pdf;

//============================================================+
// File name    : tcpdf.php
// Version      : 7.0.21
// Author       : Nicola Asuni - Tecnick.com LTD - www.tecnick.com - info@tecnick.com
// License      : GNU-LGPL v3 (https://www.gnu.org/copyleft/lesser.html)
// Copyright (C): 2002-2026 Nicola Asuni - Tecnick.com LTD
//============================================================+

/**
 * @file
 * Legacy PDF API facade over the maintained tc-lib-pdf engine.
 * Adapted by Jield from the upstream 7.0.21 compatibility facade.
 * See: https://tcpdf.org
 * @package com.tecnick.tcpdf
 * @author Nicola Asuni
 * @version 7.0.21
 */

// Tcpdf configuration
require_once __DIR__ . '/../../resources/pdf/tcpdf_autoconfig.php';

/**
 * @class Tcpdf
 * Legacy PDF API facade over the maintained tc-lib-pdf engine.
 * Adapted by Jield from the upstream 7.0.21 compatibility facade.
 *
 * This class is a compatibility facade: it implements the legacy Tcpdf public
 * API as thin wrappers that delegate the actual PDF generation to the modern
 * tc-lib-pdf engine (\Com\Tecnick\Pdf\Tcpdf). The legacy stateful cursor and
 * page model is preserved by a small internal state layer.
 * See MAPPING.md for the per-method delegation status.
 *
 * See: https://tcpdf.org
 * @package com.tecnick.tcpdf
 *
 * @phpstan-import-type StyleDataOpt from \Com\Tecnick\Pdf\Graph\Style
 * @phpstan-import-type TAnnotOpts from \Com\Tecnick\Pdf\Base
 */
class Tcpdf
{
    /**
     * The tc-lib-pdf engine that renders the actual document.
     */
    protected ?\Com\Tecnick\Pdf\Tcpdf $eng = null;

    /** Unit of measure ('pt', 'mm', 'cm', 'in'). */
    protected string $docunit = 'mm';

    /** Scale factor: number of points per user unit. */
    protected float $kratio = 1.0;

    /** Unicode mode flag. */
    protected bool $unicode = true;

    /** Document encoding (only 'UTF-8' is supported by the engine). */
    protected string $charencoding = 'UTF-8';

    /** PDF/A mode passed to the engine ('' = disabled). */
    protected string $pdfamode = '';

    /** Default page orientation ('P' or 'L'). */
    protected string $deforientation = 'P';

    /** Default page format (format name string or [width, height] in user units). */
    protected mixed $defformat = 'A4';

    /** Current page orientation. */
    protected string $curorientation = 'P';

    /** Current page format. */
    protected mixed $curformat = 'A4';

    /** Document state: 0 = not started, 1 = open, 2 = has pages, 3 = closed. */
    protected int $docstate = 0;

    /** Cached raw PDF output (built once at close time). */
    protected string $pdfraw = '';

    /** Left margin in user units. */
    protected float $lmargin = 10.0;

    /** Top margin in user units. */
    protected float $tmargin = 10.0;

    /** Right margin in user units. */
    protected float $rmargin = 10.0;

    /** Page-break bottom margin in user units. */
    protected float $bmargin = 20.0;

    /** Original margins (used by header/footer rendering). */
    protected float $orig_lmargin = 10.0;

    /** Original right margin in user units. */
    protected float $orig_rmargin = 10.0;

    /** Automatic page break flag. */
    protected bool $autopagebreak = true;

    /** Current abscissa (user units, from left page edge). */
    protected float $posx = 0.0;

    /** Current ordinate (user units, from top page edge). */
    protected float $posy = 0.0;

    /** Height of the last printed cell (user units). */
    protected float $lasth = 0.0;

    /** Cell height ratio (line height = font size * ratio). */
    protected float $cellheightratio = K_CELL_HEIGHT_RATIO;

    /** @var array{L: float, T: float, R: float, B: float} Cell internal padding in user units. */
    protected array $cellpadding = ['L' => 0.0, 'T' => 0.0, 'R' => 0.0, 'B' => 0.0];

    /** @var array{L: float, T: float, R: float, B: float} Cell external margins in user units. */
    protected array $cellmargin = ['L' => 0.0, 'T' => 0.0, 'R' => 0.0, 'B' => 0.0];

    /** Current font family (normalized lowercase). */
    protected string $fontfamily = 'helvetica';

    /** Current font style letters (subset of 'B', 'I'). */
    protected string $fontstyle = '';

    /** @var array{U: bool, D: bool, O: bool} Current font decorations: underline, line-through, overline. */
    protected array $fontdecor = ['U' => false, 'D' => false, 'O' => false];

    /** Current font size in points. */
    protected float $fontsizept = 12.0;

    /** @var array<string, mixed> Current font metric array returned by the engine font stack. */
    protected array $fontmetric = [];

    /** Default font subsetting mode for setFont (legacy default: true). */
    protected bool $fontsubsetting = true;

    /** Extra font spacing (user units) applied via the engine font stack. */
    protected float $fontspacing = 0.0;

    /** Font stretching percentage (100 = none). */
    protected float $fontstretching = 100.0;

    /** Default monospaced font family. */
    protected string $monospacedfont = 'courier';

    /** Text color: engine color specification string. */
    protected string $textcolorspec = 'black';

    /** Draw (stroke) color: engine color specification string. */
    protected string $drawcolorspec = 'black';

    /** Fill color: engine color specification string. */
    protected string $fillcolorspec = 'white';

    /** @var array<int|float> Legacy components of the current text color. */
    protected array $textcolorlegacy = [0, 0, 0];

    /** @var array<int|float> Legacy components of the current draw color. */
    protected array $drawcolorlegacy = [0, 0, 0];

    /** @var array<int|float> Legacy components of the current fill color. */
    protected array $fillcolorlegacy = [255, 255, 255];

    /** Current line width in user units. */
    protected float $linewidth = 0.2;

    /** @var StyleDataOpt Current line style (engine style array fragment). */
    protected array $linestyle = [];

    /** RTL direction flag. */
    protected bool $rtlmode = false;

    /** Temporary RTL mode ('R', 'L' or false). */
    protected mixed $tmprtl = false;

    /** Print header flag. */
    protected bool $printheader = true;

    /** Print footer flag. */
    protected bool $printfooter = true;

    /** @var array{logo: string, logo_width: float, title: string, string: string, text_color: array<int, float|int|string>, line_color: array<int, float|int|string>} Header data. */
    protected array $headerdata = [
        'logo' => '',
        'logo_width' => 30.0,
        'title' => '',
        'string' => '',
        'text_color' => [0, 0, 0],
        'line_color' => [0, 0, 0],
    ];

    /** @var array{text_color: array<int, float|int|string>, line_color: array<int, float|int|string>} Footer text and line colors. */
    protected array $footerdata = [
        'text_color' => [0, 0, 0],
        'line_color' => [0, 0, 0],
    ];

    /** Header margin (minimum distance between header and top page margin). */
    protected float $headermargin = 10.0;

    /** Footer margin (minimum distance between footer and bottom page margin). */
    protected float $footermargin = 10.0;

    /** @var array{0: string, 1: string, 2: float} Header font: family, style, size in points. */
    protected array $headerfont = ['helvetica', '', 12.0];

    /** @var array{0: string, 1: string, 2: float} Footer font: family, style, size in points. */
    protected array $footerfont = ['helvetica', '', 12.0];

    /** True while rendering the page header or footer. */
    protected bool $inheaderfooter = false;

    /**
     * Language dependent strings (legacy $l array): built-in English
     * defaults, overridable via the K_TCPDF_DEFAULT_LANGUAGE constant or
     * setLanguageArray().
     *
     * @var array<int|string, mixed>
     */
    protected array $langdata = [
        'a_meta_charset' => 'UTF-8',
        'a_meta_dir' => 'ltr',
        'a_meta_language' => 'en',
        'w_page' => 'page',
    ];

    /** Image scale ratio (used when width/height are not specified). */
    protected float $imgscale = 1.0;

    /** JPEG quality used when re-encoding images. */
    protected int $jpegquality = 90;

    /** Bottom-right X coordinate of the last inserted image. */
    protected float $imagerbx = 0.0;

    /** Bottom-right Y coordinate of the last inserted image. */
    protected float $imagerby = 0.0;

    /** @var array<int, string> Registered soft-mask source files, keyed by the handle returned from Image($ismask=true). */
    protected array $imagemasks = [];

    /** Sequence for image mask handles. */
    protected int $imagemaskseq = 0;

    /** @var array{stroke: float, fill: bool, clip: bool} Text rendering mode: stroke width, fill, clip. */
    protected array $textrendermode = ['stroke' => 0.0, 'fill' => true, 'clip' => false];

    /** @var array{enabled: bool, depth_w: int|float, depth_h: int|float, color: mixed, opacity: int|float, blend_mode: string} Legacy text shadow parameters. */
    protected array $textshadow = [
        'enabled' => false,
        'depth_w' => 0,
        'depth_h' => 0,
        'color' => false,
        'opacity' => 1,
        'blend_mode' => 'Normal',
    ];

    /** Starting page number (used by PageNoFormatted). */
    protected int $startingpagenumber = 1;

    /** Total number of pages, available while rendering deferred decorations. */
    protected int $decortotalpages = 0;

    /** Document barcode string (printed in the footer when set). */
    protected string $docbarcode = '';

    /** Booklet mode flag. */
    protected bool $bookletmode = false;

    /**
     * True when the current page is a TOC page.
     * Kept protected under its legacy name: user subclasses read it.
     */
    protected $tocpage = false;

    /** @var array{CA: float, ca: float, BM: string, AIS: bool} Current alpha/blend state. */
    protected array $alpha = ['CA' => 1.0, 'ca' => 1.0, 'BM' => '/Normal', 'AIS' => false];

    /** @var array{OP: bool, op: bool, OPM: int} Current overprint state. */
    protected array $overprint = ['OP' => false, 'op' => false, 'OPM' => 0];

    /** Number of currently open optional-content layers. */
    protected int $openlayers = 0;

    /** Snapshot of the facade object used by the transaction API. */
    protected ?Tcpdf $transactionsnapshot = null;

    /** @var array<int, array{page: int, y: float}> Internal links created by AddLink(). */
    protected array $internallinks = [];

    /** @var array<string, mixed> Named destinations registered via setDestination(). */
    protected array $nameddests = [];

    /** Identifier of the currently open XObject template ('' = none). */
    protected string $xobjtid = '';

    /** Height of the currently open XObject template in user units. */
    protected float $xobjheight = 0.0;

    /** Page group number for the next added page (0 = default group). */
    protected int $nextpagegroup = 0;

    /** True when page groups are in use. */
    protected bool $pagegroupsused = false;

    /** Columns requested via setEqualColumns() for subsequently added pages. */
    protected int $pagecolumns = 0;

    /** Column width requested via setEqualColumns() (0 = divide the content width evenly). */
    protected float $pagecolumnwidth = 0.0;

    /** @var array<int, array{RX: float, RY: float, RW: float, RH: float}> Page regions for subsequently added pages. */
    protected array $pageregions = [];

    /**
     * Legacy no-write page regions (setPageRegions()): rectangular/trapezoidal
     * exclusion zones that flowing text and HTML must avoid. Stored verbatim in
     * the legacy form and converted to engine banded writable regions at flow
     * time (see applyNoWriteRegionsForFlow()).
     *
     * @var array<int, array{page: int, xt: float, yt: float, xb: float, yb: float, side: string}>
     */
    protected array $nowriteareas = [];

    /** @var array<string, mixed> Default form field properties. */
    protected array $formdefaultprop = [];

    /** @var array<int, float|int|string> HTML link color (legacy components). */
    protected array $htmllinkcolor = [0, 0, 255];

    /** HTML link font style letters. */
    protected string $htmllinkstyle = 'U';

    /** True while rendering HTML that must ignore the current cell padding (legacy writeHTML without $cell). */
    protected bool $htmlnopadding = false;

    /** @var array{create: int, modify: int} Document timestamps (facade state; the engine stamps output itself). */
    protected array $doctimestamps = ['create' => 0, 'modify' => 0];

    /** @var array<int, array{header: bool, footer: bool}> Per-page decoration flags, frozen when each page starts. */
    protected array $pagedecor = [];

    /** @var array{zoom: int|string, layout: string, mode: string} Viewer display mode storage. */
    protected array $displaymode = ['zoom' => 'fullwidth', 'layout' => 'SinglePage', 'mode' => 'UseNone'];

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Random\RandomException
     * @throws \RuntimeException
     */
    public function __construct(
        $orientation = 'P',
        $unit = 'mm',
        $format = 'A4',
        $unicode = true,
        $encoding = 'UTF-8',
        $diskcache = false,
        $pdfa = false,
    ) {
        // $diskcache is deprecated and intentionally ignored.
        unset($diskcache);
        // $_encoding: the engine always works in UTF-8.
        $this->unicode = (bool) $unicode;
        $this->charencoding = (string) $encoding;
        $this->pdfamode = $this->normalizePdfaMode($pdfa);
        $this->deforientation = $this->normalizeOrientation($orientation);
        $this->curorientation = $this->deforientation;
        $this->defformat = is_array($format) ? $format : (string) $format;
        $this->curformat = $this->defformat;
        $this->engineInit((string) $unit);

        // Legacy defaults: 1cm page margins, padding L/R = margin/10.
        $margin = 28.35 / $this->kratio;
        $this->setMargins($margin, $margin);
        $this->orig_lmargin = $this->lmargin;
        $this->orig_rmargin = $this->rmargin;
        $this->setCellPaddings($margin / 10, 0, $margin / 10, 0);
        $this->setCellMargins(0, 0, 0, 0);
        $this->linewidth = 0.57 / $this->kratio;
        $this->setAutoPageBreak(true, 2 * $margin);
        $this->setImageScale(PDF_IMAGE_SCALE_RATIO);
        $this->headermargin = (float) PDF_MARGIN_HEADER;
        $this->footermargin = (float) PDF_MARGIN_FOOTER;
        // Language-dependent strings: built-in English defaults, merged
        // with the optional configuration override.
        $langoverride = defined('K_TCPDF_DEFAULT_LANGUAGE') ? constant('K_TCPDF_DEFAULT_LANGUAGE') : null;
        $this->setLanguageArray(
            is_array($langoverride) ? array_merge($this->langdata, $langoverride) : $this->langdata,
        );

        $fontname = defined('PDF_FONT_NAME_MAIN') ? PDF_FONT_NAME_MAIN : 'helvetica';
        $this->setFont($fontname, '', 12.0);
        $this->setHeaderFont([$fontname, '', 12.0]);
        $this->setFooterFont([$fontname, '', 12.0]);
        $this->docstate = 1;
    }

    public function __destruct()
    {
        $this->eng = null;
    }

    /**
     * Legacy protected properties historically read by Tcpdf subclasses
     * (e.g. $this->AutoPageBreak, $this->lMargin), mapped to facade state.
     *
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function __get($name)
    {
        return match ($name) {
            'AutoPageBreak' => $this->autopagebreak,
            'lMargin', 'original_lMargin' => $this->lmargin,
            'rMargin', 'original_rMargin' => $this->rmargin,
            'tMargin' => $this->tmargin,
            'bMargin' => $this->bmargin,
            'x' => $this->posx,
            'y' => $this->posy,
            'w' => $this->getPageWidth(),
            'h' => $this->getPageHeight(),
            'k' => $this->kratio,
            'page' => $this->getPage(),
            'lasth' => $this->lasth,
            'FontFamily' => $this->fontfamily,
            'FontStyle' => $this->fontstyle,
            'FontSizePt' => $this->fontsizept,
            'FontSize' => $this->getFontSize(),
            'l' => $this->langdata,
            'header_margin' => $this->headermargin,
            'footer_margin' => $this->footermargin,
            'print_header' => $this->printheader,
            'print_footer' => $this->printfooter,
            'rtl' => $this->rtlmode,
            'img_rb_x' => $this->imagerbx,
            'img_rb_y' => $this->imagerby,
            'imgscale' => $this->imgscale,
            default => null,
        };
    }

    /**
     * @see __get()
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function __isset($name)
    {
        return $this->__get($name) !== null;
    }

    // ===================================================================
    // Internal engine and state helpers (not part of the public API).
    // ===================================================================

    /**
     * Create (or re-create) the tc-lib-pdf engine for the given unit.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Random\RandomException
     */
    protected function engineInit(string $unit): void
    {
        $unit = strtolower(trim($unit)) === '' ? 'mm' : strtolower(trim($unit));
        $this->docunit = $unit;
        $this->eng = $this->engineNew();
        $this->kratio = $this->eng->toPoints(1.0);
    }

    /**
     * Build a new engine instance wired to re-emit the facade ambient text
     * state at the start of every page content stream it opens.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Random\RandomException
     */
    private function engineNew(?\Com\Tecnick\Pdf\Encrypt\Encrypt $encrypt = null): CompatibilityEngine
    {
        $eng = new CompatibilityEngine(
            $this->docunit,
            $this->unicode,
            $this->fontsubsetting,
            true,
            $this->pdfamode,
            $encrypt,
            $this->fileOptions(),
        );
        $eng->pagecontexthook = $this->ambientPageContent(...);
        return $eng;
    }

    /**
     * Raw PDF operators for the ambient text state that must open every page
     * content stream: the engine re-emits the current font (carrying its
     * spacing/stretching) when it adds a page, so the facade only needs to
     * carry the legacy text color across page breaks.
     *
     * @throws \RuntimeException
     */
    protected function ambientPageContent(): string
    {
        return $this->engine()->color->getPdfFillColor($this->textcolorspec);
    }

    /**
     * Local paths the engine may read files from. The legacy API loaded
     * images and fonts from application-relative locations, so in addition
     * to the engine defaults this allows the configured Tcpdf paths, the
     * current working directory and the running script directory.
     *
     * Applications may extend this allowlist with the K_ALLOWED_PATHS
     * configuration constant (array of path prefixes): its entries are
     * merged on top of the built-in defaults, never replacing them.
     *
     * @return array<int, string>
     */
    protected function fileAllowedPaths(): array
    {
        $candidates = [
            sys_get_temp_dir(),
            K_PATH_MAIN,
            dirname(__FILE__) . '/vendor/tecnickcom/',
            getcwd(),
        ];
        if (defined('K_PATH_FONTS')) {
            $candidates[] = K_PATH_FONTS;
        }

        if (defined('K_PATH_IMAGES')) {
            $candidates[] = K_PATH_IMAGES;
        }

        // Additional trusted read locations from the configuration; merged on
        // top of the built-in defaults so bundled assets keep resolving.
        // The analyzer resolves K_ALLOWED_PATHS to its default value, so the
        // runtime type guard looks redundant; it still protects user overrides.
        // @mago-expect analysis:redundant-logical-operation
        if (defined('K_ALLOWED_PATHS') && is_array(K_ALLOWED_PATHS)) {
            foreach (K_ALLOWED_PATHS as $extrapath) {
                $candidates[] = $extrapath;
            }
        }

        $candidates[] = dirname($_SERVER['SCRIPT_FILENAME']);

        $paths = [];
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $real = realpath($candidate);
            if ($real !== false) {
                $paths[] = $real;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Local paths resources referenced by HTML, CSS and SVG markup may be read
     * from: the fileAllowedPaths() entries without the system temp directory,
     * unless K_ALLOWED_PATHS lists that directory.
     *
     * @return array<int, string>
     */
    protected function fileMarkupAllowedPaths(): array
    {
        $paths = $this->fileAllowedPaths();
        $temp = realpath(sys_get_temp_dir());
        if ($temp === false) {
            return $paths;
        }

        // @mago-expect analysis:redundant-logical-operation
        if (defined('K_ALLOWED_PATHS') && is_array(K_ALLOWED_PATHS)) {
            foreach (K_ALLOWED_PATHS as $extrapath) {
                if (is_string($extrapath) && $extrapath !== '' && realpath($extrapath) === $temp) {
                    return $paths;
                }
            }
        }

        return array_values(array_filter($paths, static fn(string $path): bool => $path !== $temp));
    }

    /**
     * Assemble the file-access options handed to the engine's shared file
     * helper (tc-lib-file). These drive the upstream security sandbox that
     * governs which local paths and remote hosts external resources (images,
     * fonts, SVG, ...) may be loaded from.
     *
     * The mapping is configuration-driven:
     *  - allowedPaths  <- built-in defaults + K_ALLOWED_PATHS (local reads)
     *  - markupAllowedPaths <- allowedPaths without the system temp directory
     *                     (local reads triggered by HTML, CSS and SVG markup)
     *  - allowedHosts  <- K_ALLOWED_HOSTS (remote HTTP/HTTPS reads; empty
     *                     disables remote loading entirely — the safe default)
     *  - maxRemoteSize <- K_MAX_REMOTE_SIZE (byte cap on remote downloads)
     *  - curlopts      <- K_CURLOPTS (per-request cURL overrides)
     *
     * Only explicitly configured keys are forwarded; anything omitted keeps
     * the upstream library's secure defaults.
     *
     * @return array{
     *   allowedPaths: array<int, string>,
     *   markupAllowedPaths: array<int, string>,
     *   allowedHosts?: array<int, string>,
     *   maxRemoteSize?: int,
     *   curlopts?: array<int, bool|int|string>,
     * }
     */
    protected function fileOptions(): array
    {
        $options = [
            'allowedPaths' => $this->fileAllowedPaths(),
            'markupAllowedPaths' => $this->fileMarkupAllowedPaths(),
        ];

        // Remote URL loading is disabled by default in the upstream library:
        // an empty (or unset) host allowlist keeps it disabled. Populate
        // K_ALLOWED_HOSTS with trusted host names to opt in to remote reads.
        // The K_* guards below are runtime-defensive: the analyzer resolves each
        // constant to its default value, which makes the type/value checks look
        // redundant even though they still validate user-supplied overrides.
        // @mago-expect analysis:redundant-logical-operation
        if (defined('K_ALLOWED_HOSTS') && is_array(K_ALLOWED_HOSTS) && K_ALLOWED_HOSTS !== []) {
            $options['allowedHosts'] = array_values(array_map(
                static fn(mixed $host): string => (string) $host,
                K_ALLOWED_HOSTS,
            ));
        }

        // @mago-expect analysis:redundant-comparison
        // @mago-expect analysis:redundant-logical-operation
        if (defined('K_MAX_REMOTE_SIZE') && (int) K_MAX_REMOTE_SIZE > 0) {
            $options['maxRemoteSize'] = (int) K_MAX_REMOTE_SIZE;
        }

        // @mago-expect analysis:redundant-logical-operation
        if (defined('K_CURLOPTS') && is_array(K_CURLOPTS) && K_CURLOPTS !== []) {
            $options['curlopts'] = K_CURLOPTS;
        }

        // The K_* security constants resolve to their concrete defaults during
        // static analysis, so the analyzer narrows array_map(K_ALLOWED_HOSTS)
        // to a less-specific nested type than the documented contract; the
        // declared return type is authoritative for the runtime-configurable
        // values.
        // @mago-expect analysis:less-specific-nested-return-statement
        return $options;
    }

    /**
     * Return the engine instance.
     *
     * @throws \RuntimeException
     */
    protected function engine(): \Com\Tecnick\Pdf\Tcpdf
    {
        if (!$this->eng instanceof \Com\Tecnick\Pdf\Tcpdf) {
            throw new \RuntimeException('Tcpdf engine is not initialized');
        }

        return $this->eng;
    }

    /**
     * Return the engine instance built by engineNew().
     *
     * @throws \RuntimeException
     */
    protected function facadeEngine(): CompatibilityEngine
    {
        if (!$this->eng instanceof CompatibilityEngine) {
            throw new \RuntimeException('Tcpdf engine is not initialized');
        }

        return $this->eng;
    }

    /**
     * Append raw PDF content to the current page (no-op when no page exists).
     *
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function emitToPage(string $content): void
    {
        if ($content === '' || $this->docstate < 2) {
            return;
        }

        if ($this->xobjtid !== '') {
            $this->engine()->addXObjectContent($this->xobjtid, $content);
            return;
        }

        $this->engine()->page->addContent($content);
    }

    /**
     * Normalize a legacy orientation value to 'P' or 'L'.
     */
    protected function normalizeOrientation(mixed $orientation): string
    {
        $val = strtoupper(substr((string) $orientation, 0, 1));
        return $val === 'L' ? 'L' : 'P';
    }

    /**
     * Map the legacy $pdfa constructor flag onto an engine conformance mode.
     */
    protected function normalizePdfaMode(mixed $pdfa): string
    {
        $level = (int) $pdfa;
        if ($level <= 0) {
            return '';
        }

        return match ($level) {
            2 => 'pdfa2b',
            3 => 'pdfa3b',
            default => 'pdfa1b',
        };
    }

    /**
     * Build the engine page data array for a new page.
     *
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function buildPageData(mixed $orientation, mixed $format): array
    {
        $orientation = (string) $orientation === '' ? $this->curorientation : $this->normalizeOrientation($orientation);
        if (!is_array($format)) {
            $format = (string) $format === '' ? $this->curformat : (string) $format;
        }

        $this->curorientation = $orientation;
        $this->curformat = $format;

        // In column mode the current margins are the column edges: the page
        // margins are the document ones, the columns are the page regions.
        $incolumns = $this->pagecolumns > 1 || $this->pageregions !== [];

        $data = [
            'orientation' => $orientation,
            'margin' => [
                'PL' => $incolumns ? $this->orig_lmargin : $this->lmargin,
                'PR' => $incolumns ? $this->orig_rmargin : $this->rmargin,
                'PT' => 0.0,
                'HB' => 0.0,
                'CT' => $this->tmargin,
                'CB' => $this->bmargin,
                'FT' => 0.0,
                'PB' => 0.0,
            ],
            'autobreak' => $this->autopagebreak,
        ];

        if ($this->pagegroupsused) {
            $data['group'] = $this->nextpagegroup;
        }

        if ($this->pageregions !== []) {
            // Columns restart at the top margin on every page after the one
            // where they were defined (legacy selectColumn() behavior).
            $data['region'] = array_map(/** @throws \Com\Tecnick\Pdf\Page\Exception|\RuntimeException */ fn(array $region): array => [
                'RX' => $region['RX'],
                'RY' => $this->tmargin,
                'RW' => $region['RW'],
                'RH' => $this->getPageHeight() - $this->tmargin - $this->bmargin,
            ], $this->pageregions);
        } elseif ($this->pagecolumns > 1) {
            $data['region'] = $this->equalColumnRegions($this->pagecolumns, $this->pagecolumnwidth, $this->tmargin);
        }

        if (is_array($format)) {
            $width = (float) ($format[0] ?? 0);
            $height = (float) ($format[1] ?? 0);
            if ($width > 0 && $height > 0) {
                $data['width'] = $width;
                $data['height'] = $height;
            } elseif (isset($format['MediaBox']) && is_array($format['MediaBox'])) {
                // Legacy extended format array: page boxes are given in user
                // units; the engine expects them in points.
                $boxes = [];
                foreach (['MediaBox', 'CropBox', 'BleedBox', 'TrimBox', 'ArtBox'] as $boxname) {
                    if (!(isset($format[$boxname]) && is_array($format[$boxname]))) {
                        continue;
                    }

                    $boxes = $this->engine()->page->setBox(
                        $boxes,
                        $boxname,
                        (float) ($format[$boxname]['llx'] ?? 0) * $this->kratio,
                        (float) ($format[$boxname]['lly'] ?? 0) * $this->kratio,
                        (float) ($format[$boxname]['urx'] ?? 0) * $this->kratio,
                        (float) ($format[$boxname]['ury'] ?? 0) * $this->kratio,
                    );
                }

                $data['box'] = $boxes;
                $mediabox = $boxes['MediaBox'] ?? ['llx' => 0.0, 'lly' => 0.0, 'urx' => 0.0, 'ury' => 0.0];
                $data['width'] = abs($mediabox['urx'] - $mediabox['llx']) / $this->kratio;
                $data['height'] = abs($mediabox['ury'] - $mediabox['lly']) / $this->kratio;
            } elseif (isset($format['format'])) {
                $data['format'] = strtoupper((string) $format['format']);
            } else {
                $data['format'] = is_string($this->defformat) ? strtoupper($this->defformat) : 'A4';
            }

            if (isset($format['Rotate']) && is_numeric($format['Rotate'])) {
                $data['rotation'] = (int) $format['Rotate'];
            }

            if (isset($format['PZ']) && is_numeric($format['PZ'])) {
                $data['zoom'] = (float) $format['PZ'];
            }

            $transition = [];
            if (isset($format['Dur']) && is_numeric($format['Dur'])) {
                $transition['Dur'] = (float) $format['Dur'];
            }

            if (isset($format['trans']) && is_array($format['trans'])) {
                foreach (['D', 'S', 'Dm', 'M', 'Di', 'SS', 'B'] as $key) {
                    if (!isset($format['trans'][$key])) {
                        continue;
                    }

                    $transition[$key] = $format['trans'][$key];
                }
            }

            if ($transition !== []) {
                $data['transition'] = $transition;
            }
        } else {
            $data['format'] = strtoupper((string) $format);
        }

        return $data;
    }

    /**
     * Convert a legacy color definition (component list or array) to an
     * engine color specification string.
     *
     * Legacy conventions: 1 component = grayscale 0-255; 3 components =
     * RGB 0-255; 4 components = CMYK 0-100; 5th component = spot color name.
     */
    protected function colorSpecFromLegacy(mixed $color): string
    {
        if (is_string($color) && $color !== '') {
            return $color;
        }

        if (!is_array($color) || $color === []) {
            return 'black';
        }

        $values = array_values($color);
        $num = count($values);
        if ($num >= 4 && is_numeric($values[0]) && is_numeric($values[3])) {
            return (
                'cmyk('
                . (float) $values[0]
                . '%,'
                . (float) $values[1]
                . '%,'
                . (float) $values[2]
                . '%,'
                . (float) $values[3]
                . '%)'
            );
        }

        if ($num >= 3) {
            return 'rgb(' . (int) $values[0] . ',' . (int) ($values[1] ?? 0) . ',' . (int) ($values[2] ?? 0) . ')';
        }

        $gray = (int) $values[0];
        return 'rgb(' . $gray . ',' . $gray . ',' . $gray . ')';
    }

    /**
     * Convert legacy color components (setColor-style) to a spec string.
     */
    protected function colorSpecFromComponents(mixed $col1, mixed $col2, mixed $col3, mixed $col4, string $name): string
    {
        if ($name !== '') {
            return $name;
        }

        if ((float) $col4 >= 0) {
            return $this->colorSpecFromLegacy([(float) $col1, (float) $col2, (float) $col3, (float) $col4]);
        }

        if ((float) $col2 >= 0 && (float) $col3 >= 0) {
            return $this->colorSpecFromLegacy([(int) $col1, (int) $col2, (int) $col3]);
        }

        return $this->colorSpecFromLegacy([(int) $col1]);
    }

    /**
     * Sanitize a legacy color component array (numbers, optional spot name).
     *
     * @param array<int, float|int|string> $default Fallback components.
     *
     * @return array<int, float|int|string>
     */
    protected function legacyColorComponents(mixed $color, array $default): array
    {
        if (!is_array($color)) {
            return $default;
        }

        /** @var array<int, float|int|string> $out */
        $out = [];
        foreach (array_values($color) as $val) {
            $out[] = is_string($val) ? $val : (float) $val;
        }

        return $out === [] ? $default : $out;
    }

    /**
     * Current line style as an engine style array (lineWidth, lineColor, ...).
     *
     * @return StyleDataOpt
     */
    protected function currentLineStyle(): array
    {
        $style = $this->linestyle;
        $style['lineWidth'] = $this->linewidth;
        // The draw color is the single source of truth for the stroke color:
        // setLineStyle() keeps it in sync, and setDrawColor() updates it on its
        // own, so an explicit setDrawColor() after a setLineStyle() still wins.
        $style['lineColor'] = $this->drawcolorspec;

        return $style;
    }

    /**
     * Build the engine per-side styles array from a legacy border argument.
     *
     * @param mixed $border 0/false = none, 1/true = full frame,
     *                      string with letters L,T,R,B = specific sides,
     *                      array of side => style = per-side styles.
     *
     * @return array{T?: StyleDataOpt, R?: StyleDataOpt, B?: StyleDataOpt, L?: StyleDataOpt, all?: StyleDataOpt}
     */
    protected function stylesFromLegacyBorder(mixed $border, bool $fill): array
    {
        $styles = [];
        $line = $this->currentLineStyle();

        if (is_array($border)) {
            foreach ($border as $key => $sty) {
                $side = is_string($key) ? strtoupper($key) : 'all';
                $sidestyle = $line;
                if (is_array($sty)) {
                    $sidestyle = $this->styleFromLegacyLineStyle($sty);
                }

                if ($side === 'all' || $side === 'LTRB' || $side === 'TRBL') {
                    $styles['all'] = $sidestyle;
                    continue;
                }

                foreach (str_split($side) as $letter) {
                    if (!in_array($letter, ['L', 'T', 'R', 'B'], true)) {
                        continue;
                    }

                    $styles[$letter] = $sidestyle;
                }
            }
        } elseif ((is_int($border) || is_bool($border)) && (int) $border === 1) {
            $styles['all'] = $line;
        } elseif (is_string($border) && $border !== '' && $border !== '0') {
            foreach (str_split(strtoupper($border)) as $letter) {
                if (!in_array($letter, ['L', 'T', 'R', 'B'], true)) {
                    continue;
                }

                $styles[$letter] = $line;
            }
        }

        if ($fill) {
            if (!isset($styles['all'])) {
                $styles['all'] = ['lineWidth' => 0.0];
            }

            $styles['all']['fillColor'] = $this->fillcolorspec;
        }

        if ($styles === []) {
            // Explicit zero line width: otherwise the engine derives a
            // minimum cell padding from the ambient line style, shifting
            // the text relative to the legacy layout.
            $styles['all'] = ['lineWidth' => 0.0];
        }

        return $styles;
    }

    /**
     * Mirror legacy adjustCellPadding(): a cell border reserves a minimum cell
     * padding so the stroke does not overlap the text, and the optional
     * position mode decides how much of the stroke falls inside the cell box:
     *   - ext    : the whole stroke is painted outside  -> 0 padding, the
     *              border rectangle grows outward;
     *   - int    : the whole stroke is painted inside     -> a full line-width
     *              padding, the border rectangle shrinks inward;
     *   - normal : the stroke straddles the edge          -> half line-width.
     * The derived padding only ever increases the current cell padding (it
     * never shrinks it) and grows the auto cell height exactly like legacy.
     *
     * @return array{pos: float, padding: array{T: float, R: float, B: float, L: float}}
     */
    protected function legacyBorderCellMetrics(mixed $border): array
    {
        $padding = [
            'T' => $this->cellpadding['T'],
            'R' => $this->cellpadding['R'],
            'B' => $this->cellpadding['B'],
            'L' => $this->cellpadding['L'],
        ];

        // Normalize the legacy border argument into a per-side map and pull the
        // optional position mode, like legacy adjustCellPadding().
        $mode = 'normal';
        $sides = [];
        if (is_array($border)) {
            $map = $border;
            if (isset($map['mode'])) {
                $mode = strtolower((string) $map['mode']);
                unset($map['mode']);
            }

            foreach ($map as $key => $style) {
                $side = is_string($key) ? strtoupper($key) : 'LTRB';
                if (in_array($side, ['ALL', 'LTRB', 'TRBL'], true)) {
                    $side = 'LTRB';
                }

                $sides[$side] = $style;
            }
        } elseif ((is_int($border) || is_bool($border)) && (int) $border === 1) {
            $sides['LTRB'] = true;
        } elseif (is_string($border) && $border !== '' && $border !== '0') {
            $sides[strtoupper($border)] = true;
        }

        $pos = match ($mode) {
            'ext' => \Com\Tecnick\Pdf\Tcpdf::BORDERPOS_EXTERNAL,
            'int' => \Com\Tecnick\Pdf\Tcpdf::BORDERPOS_INTERNAL,
            default => \Com\Tecnick\Pdf\Tcpdf::BORDERPOS_DEFAULT,
        };

        if ($sides === []) {
            return ['pos' => $pos, 'padding' => $padding];
        }

        foreach ($sides as $side => $style) {
            $linewidth = $this->linewidth;
            if (is_array($style) && isset($style['width']) && is_numeric($style['width'])) {
                $linewidth = (float) $style['width'];
            }

            $adj = match ($mode) {
                'ext' => 0.0,
                'int' => $linewidth,
                default => $linewidth / 2.0,
            };

            // Only sides present in this border entry reserve the padding
            // (literal keys keep the inferred array shape intact for the
            // static analyzer).
            if (str_contains($side, 'T')) {
                $padding['T'] = max($padding['T'], $adj);
            }

            if (str_contains($side, 'R')) {
                $padding['R'] = max($padding['R'], $adj);
            }

            if (str_contains($side, 'B')) {
                $padding['B'] = max($padding['B'], $adj);
            }

            if (str_contains($side, 'L')) {
                $padding['L'] = max($padding['L'], $adj);
            }
        }

        return ['pos' => $pos, 'padding' => $padding];
    }

    /**
     * Convert a legacy line style array (width, cap, join, dash, phase, color)
     * to an engine style array.
     *
     * @param array<int|string, mixed> $style Legacy style array.
     *
     * @return StyleDataOpt
     */
    protected function styleFromLegacyLineStyle(array $style): array
    {
        $out = $this->currentLineStyle();
        if (isset($style['width']) && is_numeric($style['width'])) {
            $out['lineWidth'] = (float) $style['width'];
        }

        if (isset($style['cap']) && is_string($style['cap'])) {
            $out['lineCap'] = $style['cap'];
        }

        if (isset($style['join']) && is_string($style['join'])) {
            $out['lineJoin'] = $style['join'];
        }

        if (isset($style['dash'])) {
            $dash = $style['dash'];
            $dasharray = [];
            if (is_string($dash) && $dash !== '' && $dash !== '0') {
                foreach (explode(',', $dash) as $val) {
                    $dasharray[] = (int) round((float) $val);
                }
            } elseif (is_numeric($dash) && (float) $dash > 0) {
                $dasharray = [(int) round((float) $dash)];
            }

            $out['dashArray'] = $dasharray;
            $out['dashPhase'] = (float) ($style['phase'] ?? 0);
        }

        if (isset($style['color']) && is_array($style['color'])) {
            $out['lineColor'] = $this->colorSpecFromLegacy($style['color']);
        }

        return $out;
    }

    /**
     * Map a legacy shape style letter combination to an engine paint mode.
     */
    protected function shapeModeFromLegacy(mixed $style, bool $hasfill, bool $close = false): string
    {
        $val = strtoupper((string) $style);
        if ($close) {
            // Closing variant for polygon-like shapes: the engine appends the
            // closing segment only for the lowercase "close" paint operators,
            // mirroring the legacy Polygon() which always closes the path.
            return match ($val) {
                'F' => 'f',
                'DF', 'FD' => 'b',
                'C' => 's',
                'CNZ' => 'CNZ',
                'CEO' => 'CEO',
                '' => $hasfill ? 'b' : 's',
                default => 's',
            };
        }

        return match ($val) {
            'F' => 'f',
            'DF', 'FD' => 'B',
            'C' => 's',
            'CNZ' => 'CNZ',
            'CEO' => 'CEO',
            '' => $hasfill ? 'B' : 'S',
            default => 'S',
        };
    }

    /**
     * Build the engine style for a legacy (line_style, fill_color) pair.
     *
     * The legacy drawing methods PERSIST these parameters: a style array
     * updates the current line style and a fill color array updates the
     * current fill color (subsequent shapes inherit them).
     *
     * @return StyleDataOpt
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function shapeStyleFromLegacy(mixed $linestyle, mixed $fillcolor): array
    {
        if (is_array($linestyle) && $linestyle !== []) {
            $this->setLineStyle($linestyle, true);
        }

        if (is_array($fillcolor) && $fillcolor !== []) {
            $this->fillcolorspec = $this->colorSpecFromLegacy($fillcolor);
        }

        $style = $this->currentLineStyle();
        $style['fillColor'] = $this->fillcolorspec;
        return $style;
    }

    /**
     * Convert a legacy per-segment/per-side style list to engine styles.
     * Integer 0 entries mean "do not draw this segment".
     *
     * @return array<int|string, StyleDataOpt>
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function segmentStylesFromLegacy(mixed $linestyle, mixed $fillcolor): array
    {
        $base = $this->shapeStyleFromLegacy([], $fillcolor);
        $styles = ['all' => $base];
        if (!is_array($linestyle)) {
            return $styles;
        }

        if (isset($linestyle['all']) && is_array($linestyle['all'])) {
            // Persist the global style like the legacy setLineStyle($style['all'])
            // so subsequent shapes inherit it as their current line style.
            $this->setLineStyle($linestyle['all'], true);
            $allstyle = $this->currentLineStyle();
            $allstyle['fillColor'] = $base['fillColor'] ?? $this->fillcolorspec;
            $styles['all'] = $allstyle;
            return $styles;
        }

        $islist = $linestyle !== [] && array_keys($linestyle) === range(0, count($linestyle) - 1);
        if ($islist) {
            foreach ($linestyle as $idx => $segstyle) {
                if (is_array($segstyle)) {
                    $styles[(int) $idx] = $this->styleFromLegacyLineStyle($segstyle);
                } else {
                    // 0 = segment not drawn
                    $styles[(int) $idx] = ['lineWidth' => 0.0];
                }
            }

            return $styles;
        }

        if ($linestyle !== []) {
            $single = $this->styleFromLegacyLineStyle($linestyle);
            $single['fillColor'] = $base['fillColor'] ?? $this->fillcolorspec;
            $styles['all'] = $single;
        }

        return $styles;
    }

    /**
     * Resolve a legacy optional coordinate ('' or null means current position).
     */
    protected function coordOrCursor(mixed $value, bool $vertical): float
    {
        if ($value === null || (string) $value === '') {
            return $vertical ? $this->posy : $this->posx;
        }

        return (float) $value;
    }

    /**
     * Complete partial per-side styles with the current graph style defaults
     * (the engine HTML renderer requires fully-populated style arrays).
     *
     * @param array{T?: StyleDataOpt, R?: StyleDataOpt, B?: StyleDataOpt, L?: StyleDataOpt, all?: StyleDataOpt} $styles
     *
     * @return array<int|string, array{cssBorderStyle?: string, dashArray: array<array-key, int>, dashPhase: float, fillColor: string, lineCap: string, lineColor: string, lineJoin: string, lineWidth: float, miterLimit: float}>
     * @throws \RuntimeException
     */
    protected function completeSideStyles(array $styles): array
    {
        $base = $this->engine()->graph->getCurrentStyleArray();
        $out = [];
        foreach ($styles as $side => $style) {
            $out[$side] = [
                'dashArray' => array_map(
                    static fn(mixed $val): int => (int) $val,
                    $style['dashArray'] ?? $base['dashArray'] ?? [],
                ),
                'dashPhase' => $style['dashPhase'] ?? $base['dashPhase'] ?? 0.0,
                'fillColor' => $style['fillColor'] ?? '',
                'lineCap' => $style['lineCap'] ?? $base['lineCap'] ?? 'butt',
                'lineColor' => $style['lineColor'] ?? $base['lineColor'] ?? 'black',
                'lineJoin' => $style['lineJoin'] ?? $base['lineJoin'] ?? 'miter',
                'lineWidth' => $style['lineWidth'] ?? 0.0,
                'miterLimit' => $style['miterLimit'] ?? $base['miterLimit'] ?? 10.0,
            ];
        }

        return $out;
    }

    /**
     * Whether the content can be moved to another column or page when it does
     * not fit below the current position.
     */
    protected function canBreakPage(): bool
    {
        return $this->autopagebreak && !$this->inheaderfooter && $this->xobjtid === '' && $this->docstate === 2;
    }

    /**
     * Trigger an automatic page break when the given height does not fit.
     *
     * @throws \Exception
     */
    protected function breakIfNeeded(float $height): void
    {
        if (!$this->canBreakPage()) {
            return;
        }

        $limit = $this->getPageHeight() - $this->bmargin;
        if (($this->posy + $height - $limit) <= 0.0001) {
            return;
        }

        // Legacy consults AcceptPageBreak() only for content that does not
        // fit, so an override with side effects runs once per real break.
        if (!$this->AcceptPageBreak()) {
            return;
        }

        if ($this->advanceToNextColumn()) {
            return;
        }

        // Legacy: a break past the last column restarts at the first column
        // of the new page (startPage() selects it), so the abscissa is only
        // preserved outside column mode.
        $posx = $this->posx;
        $this->AddPage($this->curorientation);
        if ($this->getNumberOfColumns() < 2) {
            $this->posx = $posx;
        }
    }

    /**
     * Move the cursor to the top of the next column, when column mode is
     * active and the current column is not the last one.
     *
     * Legacy AcceptPageBreak() flows the content through the remaining
     * columns before adding a page.
     *
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function advanceToNextColumn(): bool
    {
        $numcols = $this->getNumberOfColumns();
        $column = $this->getColumn();
        if ($numcols < 2 || $column >= ($numcols - 1)) {
            return false;
        }

        $this->selectColumn($column + 1);
        return true;
    }

    /**
     * Move to the top of the next page for an automatic break, reusing an
     * already-created page when one exists.
     *
     * Legacy startPage() reuses the next page (`if numpages > page: page++`)
     * instead of always appending, so a cell the caller placed back on an
     * earlier page (e.g. the MultiRow pattern of example_020, which writes
     * the two columns at the same Y on the start page) still breaks onto the
     * single shared continuation page rather than spawning a fresh one. The
     * current abscissa is preserved across the break, like breakIfNeeded().
     *
     * @throws \Exception
     */
    protected function advanceToNextPage(): void
    {
        $posx = $this->posx;
        if ($this->getPage() < $this->getNumPages()) {
            $this->setPage($this->getPage() + 1);
        } else {
            $this->AddPage($this->curorientation);
        }

        $this->posy = $this->tmargin;
        $this->posx = $posx;
        if ($this->getNumberOfColumns() > 1) {
            $this->selectColumn(0);
        }
    }

    /**
     * Map a legacy text vertical alignment letter to the engine convention.
     */
    protected function valignToEngine(mixed $valign): string
    {
        $val = strtoupper((string) $valign);
        return match ($val) {
            'T', 'B', 'A', 'L', 'D' => $val,
            default => 'C',
        };
    }

    /**
     * Legacy $calign cell vertical alignment: the amount (user units) by which
     * the cell box top is shifted UP relative to the current text line. The
     * font-relative modes (A/L/D) depend on $valign, mirroring getCellCode().
     *
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function cellCalignShift(string $calign, string $valign, float $height): float
    {
        $metric = $this->currentFontMetric();
        $ascent = (float) ($metric['ascent'] ?? 0.0) / $this->kratio;
        $descent = abs((float) ($metric['descent'] ?? 0.0)) / $this->kratio;
        $padt = $this->cellpadding['T'];
        $padb = $this->cellpadding['B'];
        $valign = strtoupper($valign);

        switch (strtoupper($calign)) {
            case 'A': // font top (ascent line at the text line)
                if ($valign === 'T') {
                    return $padt;
                }

                if ($valign === 'B') {
                    return $height - $padb - $ascent - $descent;
                }

                return ($height - $ascent - $descent) / 2;
            case 'L': // font baseline
                if ($valign === 'T') {
                    return $padt + $ascent;
                }

                if ($valign === 'B') {
                    return $height - $padb - $descent;
                }

                return ($height + $ascent - $descent) / 2;
            case 'D': // font bottom (descent line)
                if ($valign === 'T') {
                    return $padt + $ascent + $descent;
                }

                if ($valign === 'B') {
                    return $height - $padb;
                }

                return ($height + $ascent + $descent) / 2;
            case 'B': // cell bottom
                return $height;
            case 'C':
            case 'M': // cell center
                return $height / 2;
            default: // 'T' cell top
                return 0.0;
        }
    }

    /**
     * Map a legacy horizontal alignment letter to the engine convention.
     */
    protected function halignToEngine(mixed $align): string
    {
        $val = strtoupper((string) $align);
        if (in_array($val, ['L', 'C', 'R', 'J'], true)) {
            return $val;
        }

        return $this->isRTLTextDir() ? 'R' : 'L';
    }

    /**
     * Bookmark outline entries collected by the engine.
     *
     * @return array<int, array{t: string, l: int, p: int, y: float, s?: string, c?: string}>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function engineOutlines(): array
    {
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Base::class, 'outlines');
        $outlines = $prop->getValue($this->engine());
        if (!is_array($outlines)) {
            return [];
        }

        /** @var array<int, array{t: string, l: int, p: int, y: float, s?: string, c?: string}> $outlines */
        return $outlines;
    }

    /**
     * Replace the engine bookmark outline entries (used to remap page
     * targets after the TOC pages are relocated).
     *
     * @param array<int, array{t: string, l: int, p: int, y: float, s?: string, c?: string}> $outlines
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function setEngineOutlines(array $outlines): void
    {
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Base::class, 'outlines');
        $prop->setValue($this->engine(), $outlines);
    }

    /**
     * Emulate the legacy text shadow for engine-rendered HTML content.
     *
     * The engine HTML renderer has no shadow support, so the text objects
     * (BT..ET blocks) of the freshly rendered chunks are duplicated into an
     * offset, shadow-colored, alpha-blended layer painted underneath.
     *
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function applyHtmlTextShadow(int $pid, int $start): void
    {
        $shadow = $this->engineShadow();
        if ($shadow === null) {
            return;
        }

        $eng = $this->engine();
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Page\Page::class, 'page');
        $pages = $prop->getValue($eng->page);
        if (!is_array($pages) || !isset($pages[$pid]['content']) || !is_array($pages[$pid]['content'])) {
            return;
        }

        $content = $pages[$pid]['content'];
        $chunks = [];
        foreach (array_slice($content, $start) as $chunk) {
            $chunks[] = is_string($chunk) ? $chunk : '';
        }

        $rendered = implode('', $chunks);
        $blocks = [];
        if (preg_match_all('/BT .*? ET/s', $rendered, $blocks) === 0) {
            return;
        }

        $text = implode("\n", $blocks[0]);
        // Neutralize color and rendering-mode operators inside the copy so
        // the whole layer paints in the shadow color.
        $text = (string) preg_replace(
            ['#/CS[0-9]+ (cs|CS)#', '#[0-9.]+( [0-9.]+)* (scn|SCN|rg|RG|g|G|k|K)(?![a-zA-Z])#', '#[0-9]+ Tr#'],
            ['', '', '0 Tr'],
            $text,
        );

        $layer = $eng->graph->getStartTransform();
        $layer .= sprintf(
            '1 0 0 1 %F %F cm' . "\n",
            $shadow['xoffset'] * $this->kratio,
            -$shadow['yoffset'] * $this->kratio,
        );
        $layer .= $eng->graph->getAlpha($shadow['opacity'], $shadow['mode'], $shadow['opacity']);
        $layer .= $eng->color->getPdfFillColor($shadow['color']);
        $layer .= $text . "\n";
        $layer .= $eng->graph->getStopTransform();

        // Paint the shadow layer beneath the rendered chunks.
        array_splice($content, $start, 0, [$layer]);
        $pages[$pid]['content'] = $content;
        $prop->setValue($eng->page, $pages);
    }

    /**
     * Lowest bottom edge (user units from the page top) of the images
     * placed in the content chunks appended after the given index.
     * Image placements carry their position in the CTM:
     * "w 0 0 h x y cm /IMGn Do" with y = bottom edge in PDF coordinates.
     *
     * @throws \ReflectionException
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function pageContentImageBottom(int $pid, int $start, float $fallback): float
    {
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Page\Page::class, 'page');
        $pages = $prop->getValue($this->engine()->page);
        if (!is_array($pages) || !isset($pages[$pid]['content']) || !is_array($pages[$pid]['content'])) {
            return $fallback;
        }

        $chunks = [];
        foreach (array_slice($pages[$pid]['content'], $start) as $chunk) {
            $chunks[] = is_string($chunk) ? $chunk : '';
        }

        $matches = [];
        if (
            preg_match_all(
                '#[0-9.]+ 0 0 [0-9.]+ [0-9.]+ ([0-9.]+) cm /IMG[a-z]*[0-9]+ Do#',
                implode('', $chunks),
                $matches,
            ) === 0
        ) {
            return $fallback;
        }

        $pageheight = (float) ($pages[$pid]['height'] ?? $this->getPageHeight());
        $bottom = $fallback;
        foreach ($matches[1] as $pdfy) {
            $bottom = max($bottom, $pageheight - ((float) $pdfy / $this->kratio));
        }

        return $bottom;
    }

    /**
     * Number of raw content chunks of the given engine page.
     *
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function pageContentCount(int $pid): int
    {
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Page\Page::class, 'page');
        $pages = $prop->getValue($this->engine()->page);
        return is_array($pages) && isset($pages[$pid]['content']) && is_array($pages[$pid]['content'])
            ? count($pages[$pid]['content'])
            : 0;
    }

    /**
     * Move the content chunks appended after the given index to the front
     * of the page stream, so they paint *behind* the page body. Used for
     * deferred header rendering: the legacy engine painted headers at
     * page-start time, i.e. under the body content.
     *
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function movePageContentToFront(int $pid, int $start): void
    {
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Page\Page::class, 'page');
        $pages = $prop->getValue($this->engine()->page);
        if (!is_array($pages) || !isset($pages[$pid]['content']) || !is_array($pages[$pid]['content'])) {
            return;
        }

        $content = $pages[$pid]['content'];
        if ($start >= count($content)) {
            return;
        }

        $moved = array_splice($content, $start);
        $pages[$pid]['content'] = array_merge($moved, $content);
        $prop->setValue($this->engine()->page, $pages);
    }

    /**
     * PDF code for a cell background fill and the requested border sides.
     *
     * @param array{T?: StyleDataOpt, R?: StyleDataOpt, B?: StyleDataOpt, L?: StyleDataOpt, all?: StyleDataOpt} $styles
     * @throws \RuntimeException
     */
    protected function cellBoxOutput(
        float $posx,
        float $posy,
        float $width,
        float $height,
        array $styles,
        bool $fill,
    ): string {
        $eng = $this->engine();
        $out = '';
        if ($fill) {
            $out .= $eng->graph->getBasicRect($posx, $posy, $width, $height, 'f', [
                'lineWidth' => 0.0,
                'fillColor' => $this->fillcolorspec,
            ]);
        }

        $sides = [
            'T' => [$posx, $posy, $posx + $width, $posy],
            'R' => [$posx + $width, $posy, $posx + $width, $posy + $height],
            'B' => [$posx + $width, $posy + $height, $posx, $posy + $height],
            'L' => [$posx, $posy + $height, $posx, $posy],
        ];
        foreach ($sides as $letter => $line) {
            $sty = $styles[$letter] ?? $styles['all'] ?? null;
            if (is_array($sty) && isset($sty['lineWidth']) && $sty['lineWidth'] > 0) {
                $out .= $eng->graph->getLine($line[0], $line[1], $line[2], $line[3], $sty);
            }
        }

        return $out;
    }

    /**
     * Draw the per-page border/fill segments of an auto-height HTML cell
     * that flowed across pages (the engine skips the cell box in that case).
     * Legacy MultiCell splits the frame at each break in open-cell mode:
     * the segment before a page break has no bottom edge and runs to the
     * break trigger, the segment after it has no top edge and restarts from
     * the top margin. Each segment is inserted behind its page content.
     *
     * @param array{T?: StyleDataOpt, R?: StyleDataOpt, B?: StyleDataOpt, L?: StyleDataOpt, all?: StyleDataOpt} $styles
     * @param ?int $endpage Last page the cell content actually reached.
     *                      Defaults to the current page; callers pass an
     *                      explicit value when the engine advanced the page
     *                      pointer past the cell's real end (see MultiCell).
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function drawHtmlCellSegments(
        float $posx,
        float $width,
        float $starty,
        float $bottom,
        int $startpage,
        int $contentstart,
        array $styles,
        bool $fill,
        ?int $endpage = null,
    ): void {
        $eng = $this->engine();
        $endpage ??= $this->getPage();
        $breaky = $this->getPageHeight() - $this->bmargin;
        for ($page = $startpage; $page <= $endpage; $page++) {
            $top = $page === $startpage ? $starty : $this->tmargin;
            $segbottom = max($top, $page === $endpage ? $bottom : $breaky);
            $segstyles = $styles;
            if ($page > $startpage) {
                $segstyles['T'] = ['lineWidth' => 0.0];
            }

            if ($page < $endpage) {
                $segstyles['B'] = ['lineWidth' => 0.0];
            }

            $box = $this->cellBoxOutput($posx, $top, $width, $segbottom - $top, $segstyles, $fill);
            if ($box === '') {
                continue;
            }

            $this->insertPageContent(
                $page - 1,
                $page === $startpage ? $contentstart : 0,
                $eng->graph->getStartTransform() . $box . $eng->graph->getStopTransform(),
            );
        }
    }

    /**
     * Insert a PDF content chunk at the given position of an engine page
     * content stream, so it paints behind the chunks that follow it.
     *
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function insertPageContent(int $pid, int $start, string $content): void
    {
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Page\Page::class, 'page');
        $pages = $prop->getValue($this->engine()->page);
        if (!is_array($pages) || !isset($pages[$pid]['content']) || !is_array($pages[$pid]['content'])) {
            return;
        }

        $chunks = $pages[$pid]['content'];
        array_splice($chunks, $start, 0, [$content]);
        $pages[$pid]['content'] = $chunks;
        $prop->setValue($this->engine()->page, $pages);
    }

    /**
     * PDF code for a stretched cell text run (legacy Cell stretch modes).
     *
     * Modes 1/2 scale the glyphs horizontally (via a CTM transform around
     * the text anchor); modes 3/4 adjust the character spacing (via a Tc
     * operator). The engine renders the text at its NATURAL width (the font
     * stack is neutralised for this run), so its line splitting never
     * triggers, and the ambient spacing/stretching plus the fit transform are
     * emitted here as self-contained text-state operators.
     *
     * @param array{xoffset: float, yoffset: float, opacity: float, mode: string, color: string}|null $shadow
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function stretchedCellText(
        int $mode,
        string $txt,
        float $cellx,
        float $width,
        float $height,
        float $textw,
        string $valign,
        ?array $shadow,
    ): string {
        $eng = $this->engine();
        $padl = $this->cellpadding['L'];
        $padr = $this->cellpadding['R'];
        $avail = $width - $padl - $padr;

        // Render the text at its natural width: neutralise the ambient
        // spacing/stretching on the font stack so the engine emits no Tz/Tc
        // of its own and never splits the line. The box is sized from the
        // larger of the natural and the rendered widths.
        $eng->font->cloneFont($eng->pon, null, null, $this->fontsizept, 0.0, 1.0);
        $rawwidth =
            $eng->font->getOrdArrDims(array_values($eng->uniconv->strToOrdArr($txt)))['totwidth'] / $this->kratio;
        $naturalbox = max($rawwidth, $textw) + $padl + $padr;
        $boxx = $this->rtlmode ? $cellx + $width - $naturalbox : $cellx;

        $text = $this->cellTextRun(
            $txt,
            $boxx,
            $naturalbox,
            $height,
            $valign,
            $this->rtlmode ? 'R' : 'L',
            null,
            $shadow,
        );
        $eng->font->popLastFont();

        // Ambient text state (base spacing Tc + base stretch Tz) and the
        // reset back to the neutral text state after the run.
        $basestate = sprintf('%F Tc %F Tz ', $this->fontspacing * $this->kratio, $this->fontstretching);
        $reset = '0.000000 Tc 100.000000 Tz ';

        if ($mode <= 2) {
            // Glyph scaling anchored at the text start edge: the ambient
            // spacing/stretching apply inside the saved graphics state and
            // the CTM brings the rendered width up to the available width.
            $anchor = $this->rtlmode ? $cellx + $width - $padr : $cellx + $padl;
            return (
                $basestate
                . $eng->graph->getStartTransform()
                . $eng->graph->getHorizScaling($avail / $textw, $anchor, $this->posy)
                . $text
                . $eng->graph->getStopTransform()
                . $reset
            );
        }

        // Character spacing: solve (raw + S * chars) * stretching = avail
        // for the per-glyph spacing S (Tc applies to every glyph and is
        // scaled by the ambient horizontal scaling).
        $chars = (int) $this->GetNumChars($txt);
        if ($chars < 2) {
            return $basestate . $text . $reset;
        }

        $spacing = ((($avail * 100) / $this->fontstretching) - $rawwidth) / $chars;
        $spacingstate = sprintf('%F Tc %F Tz ', $spacing * $this->kratio, $this->fontstretching);
        return $spacingstate . $text . $reset;
    }

    /**
     * PDF code for a cell whose text is drawn on one line regardless of the
     * cell width.
     *
     * The box is drawn by the engine with the requested geometry and the
     * border-adjusted cell definition used by getTextCell(). The text is
     * drawn in a box wider than the text, anchored to the cell so the
     * engine alignment places it as in the cell: left edge for L, right
     * edge for R, center for C.
     *
     * @param array{margin: array{T: float, R: float, B: float, L: float}, padding: array{T: float, R: float, B: float, L: float}, borderpos: float}|null $engcell
     * @param array<int|string, array<string, mixed>> $styles
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \ReflectionException
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    protected function overflowCellOutput(
        string $txt,
        float $cellx,
        float $width,
        float $height,
        float $txtw,
        string $halign,
        string $valign,
        ?array $engcell,
        array $styles,
    ): string {
        $eng = $this->engine();
        /** @var array{margin: array{T: float, R: float, B: float, L: float}, padding: array{T: float, R: float, B: float, L: float}, borderpos: float} $cell */
        $cell = $this->engineMethod('adjustMinCellPadding')->invoke($eng, $styles, $engcell);
        $box = $this->engineMethod('drawCell')->invoke(
            $eng,
            $eng->toPoints($cellx) + $cell['margin']['L'],
            $eng->toYPoints($this->posy) - $cell['margin']['T'],
            $eng->toPoints($width),
            $eng->toPoints($height),
            $styles,
            $cell,
        );

        if ($halign === 'J') {
            $halign = $this->isRTLTextDir() ? 'R' : 'L';
        }

        // Twice the estimated text width leaves the engine enough room for
        // the prepared text, whose width can differ from the estimate.
        $boxw = $width + (2 * $txtw) + $eng->toUnit($cell['padding']['L'] + $cell['padding']['R']);
        $boxx = match ($halign) {
            'R' => $cellx + $width - $boxw,
            'C' => $cellx + (($width - $boxw) / 2),
            default => $cellx,
        };

        return (
            (is_string($box) ? $box : '')
            . $this->cellTextRun($txt, $boxx, $boxw, $height, $valign, $halign, $cell, $this->engineShadow())
        );
    }

    /**
     * Text-only engine cell run (no box) with the current text render mode
     * and decorations.
     *
     * @param array{margin: array{T: float, R: float, B: float, L: float}, padding: array{T: float, R: float, B: float, L: float}, borderpos: float}|null $cell
     * @param array{xoffset: float, yoffset: float, opacity: float, mode: string, color: string}|null $shadow
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    protected function cellTextRun(
        string $txt,
        float $posx,
        float $width,
        float $height,
        string $valign,
        string $halign,
        ?array $cell,
        ?array $shadow,
    ): string {
        return $this->engine()->getTextCell(
            $txt,
            $posx,
            $this->posy,
            $width,
            $height,
            0,
            0,
            $valign,
            $halign,
            $cell,
            ['all' => ['lineWidth' => 0.0]],
            $this->textrendermode['stroke'],
            0,
            0,
            0,
            true,
            $this->textrendermode['fill'],
            $this->textrendermode['stroke'] > 0,
            $this->fontdecor['U'],
            $this->fontdecor['D'],
            $this->fontdecor['O'],
            $this->textrendermode['clip'],
            false,
            $this->forcedTextDir(),
            $shadow,
        );
    }

    /**
     * Protected engine method made callable from the facade.
     *
     * @throws \ReflectionException
     */
    protected function engineMethod(string $name): \ReflectionMethod
    {
        static $methods = [];
        if (!isset($methods[$name])) {
            $methods[$name] = new \ReflectionMethod(\Com\Tecnick\Pdf\Tcpdf::class, $name);
        }

        return $methods[$name];
    }

    /**
     * Text width in user units measured with the current engine font,
     * including its spacing and stretching.
     *
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    protected function engineTextWidth(string $txt): float
    {
        $eng = $this->engine();
        return $eng->toUnit($eng->font->getOrdArrDims(array_values($eng->uniconv->strToOrdArr($txt)))['totwidth']);
    }

    /**
     * Re-apply the current font to the engine font stack so it carries the
     * up-to-date spacing/stretching.
     *
     * The engine reads spacing (points) and stretching (ratio) from the
     * current font and emits the matching Tc/Tz operators (with their resets)
     * per text run, so changing them only needs to refresh the stacked font;
     * the font selection (Tf) is unchanged and need not be re-emitted.
     *
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    protected function refreshFontState(): void
    {
        $eng = $this->engine();
        if (!$eng->font->hasCurrentFont()) {
            // No font selected yet: the next setFont() applies the value.
            return;
        }

        $this->fontmetric = $eng->font->cloneFont(
            $eng->pon,
            null,
            null,
            $this->fontsizept,
            $this->fontspacing * $this->kratio,
            $this->fontstretching / 100.0,
        );
        if ($this->xobjtid !== '') {
            $eng->addXObjectFontID($this->xobjtid, $this->fontmetric['key']);
        }
    }

    /**
     * Raw PDF code that renders the current font's natural (unstretched,
     * unspaced) measurement of the given text width in user units.
     *
     * Used by the legacy Cell stretch modes, whose fit math is expressed
     * against the natural glyph widths while the engine font stack already
     * carries the ambient spacing/stretching.
     *
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    protected function naturalOrdWidth(string $txt): float
    {
        $eng = $this->engine();
        $eng->font->cloneFont($eng->pon, null, null, $this->fontsizept, 0.0, 1.0);
        $width = $eng->font->getOrdArrDims(array_values($eng->uniconv->strToOrdArr($txt)))['totwidth'] / $this->kratio;
        $eng->font->popLastFont();
        return $width;
    }

    /**
     * Current engine text shadow array (or null when disabled).
     *
     * @return array{xoffset: float, yoffset: float, opacity: float, mode: string, color: string}|null
     */
    protected function engineShadow(): ?array
    {
        if (!$this->textshadow['enabled']) {
            return null;
        }

        return [
            'xoffset' => (float) $this->textshadow['depth_w'],
            'yoffset' => (float) $this->textshadow['depth_h'],
            'opacity' => (float) $this->textshadow['opacity'],
            'mode' => $this->textshadow['blend_mode'],
            'color' => $this->colorSpecFromLegacy($this->textshadow['color']),
        ];
    }

    /**
     * Forced text direction for the engine ('' = inherit document direction).
     */
    protected function forcedTextDir(): string
    {
        if ($this->tmprtl === 'R' || $this->tmprtl === 'L') {
            return $this->tmprtl;
        }

        return '';
    }

    /**
     * Canonicalize a local file path before handing it to the engine.
     *
     * The engine file helper rejects paths containing '..' components for
     * security; the legacy API accepted them, so resolve local paths first.
     */
    protected function resolveLocalFile(string $file): string
    {
        if ($file === '' || $file[0] === '@' || $file[0] === '*' || str_contains($file, '://')) {
            return $file;
        }

        $real = realpath($file);
        return $real === false ? $file : $real;
    }

    /**
     * Whether a local file exists inside the engine path allowlist.
     *
     * @throws \RuntimeException
     */
    protected function isAllowedLocalFile(string $file): bool
    {
        return $file !== '' && $this->engine()->file->isAllowedFile($file) && is_file($file);
    }

    /**
     * Register an external link annotation for the given rectangle.
     *
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function attachLink(mixed $link, float $posx, float $posy, float $width, float $height): void
    {
        if ($this->docstate !== 2) {
            return;
        }

        if (is_int($link) || is_string($link) && preg_match('/^@L([0-9]+)$/', $link) === 1) {
            // Internal link identifier created by AddLink().
            $lid = is_int($link) ? $link : (int) substr($link, 2);
            $dest = $this->internallinks[$lid] ?? null;
            if ($dest === null) {
                return;
            }

            $eng = $this->engine();
            $target = $eng->addInternalLink((int) $dest['page'], $dest['y']);
            $oid = $eng->setLink($posx, $posy, $width, $height, $target);
            $this->attachAnnotRef($oid);
            return;
        }

        if (!is_string($link) || $link === '') {
            return;
        }

        $oid = $this->engine()->setLink($posx, $posy, $width, $height, $link);
        $this->attachAnnotRef($oid);
    }

    /**
     * Render the deferred page decorations (headers and footers) on every
     * page. Called once when the document is closed: at that time the total
     * page count is known, so the default footer can print real numbers.
     *
     * @throws \Throwable
     */
    protected function decorateAllPages(): void
    {
        $any = $this->printheader || $this->printfooter;
        foreach ($this->pagedecor as $decor) {
            $any = $any || $decor['header'] || $decor['footer'];
        }

        if (!$any) {
            return;
        }

        $eng = $this->engine();
        $pages = $eng->page->getPages();
        $total = count($pages);
        $this->decortotalpages = $total;
        $this->inheaderfooter = true;

        $saved = [
            'x' => $this->posx,
            'y' => $this->posy,
            'lasth' => $this->lasth,
            'family' => $this->fontfamily,
            'style' => $this->fontstyle,
            'size' => $this->fontsizept,
            'padding' => $this->cellpadding,
            'lmargin' => $this->lmargin,
            'rmargin' => $this->rmargin,
        ];

        $savedspacing = $this->fontspacing;
        $savedstretching = $this->fontstretching;
        $savedshadow = $this->textshadow;
        $savedcellheightratio = $this->cellheightratio;
        foreach (array_keys($pages) as $pid) {
            $decor = $this->pagedecor[(int) $pid] ?? ['header' => true, 'footer' => true];
            if (!$decor['header'] && !$decor['footer']) {
                continue;
            }

            $eng->setCurrentPage((int) $pid);
            $layout = $this->setDecorationPageLayout((int) $pid);
            $this->lmargin = $this->orig_lmargin;
            $this->rmargin = $this->orig_rmargin;
            $this->setCellPadding(0);
            // Header/footer rendering starts from the default text state
            // (no spacing, no stretching, no text shadow, default line
            // height). Legacy resets the full graphic state to the document
            // defaults before drawing the header, so a user setCellHeightRatio()
            // for the body must not stretch the header/footer line pitch.
            $this->fontspacing = 0.0;
            $this->fontstretching = 100.0;
            $this->textshadow['enabled'] = false;
            $this->cellheightratio = K_CELL_HEIGHT_RATIO;
            if ($decor['header']) {
                // Headers paint *behind* the body (legacy drew them at
                // page-start): render normally, then move the chunks to
                // the front of the page stream.
                $start = $this->pageContentCount((int) $pid);
                $this->emitToPage($eng->graph->getStartTransform());
                $this->setXY($this->orig_lmargin, $this->headermargin);
                $this->setFont($this->headerfont[0], $this->headerfont[1], $this->headerfont[2]);
                $this->Header();
                $this->emitToPage($eng->graph->getStopTransform());
                $this->movePageContentToFront((int) $pid, $start);
            }

            if ($decor['footer']) {
                $this->emitToPage($eng->graph->getStartTransform());
                $footery = $this->getPageHeight((int) $pid + 1) - $this->footermargin;
                $this->setXY($this->orig_lmargin, $footery);
                $this->setFont($this->footerfont[0], $this->footerfont[1], $this->footerfont[2]);
                $this->Footer();
                $this->emitToPage($eng->graph->getStopTransform());
            }

            $this->setEnginePageLayout((int) $pid, $layout);
        }

        $this->posx = $saved['x'];
        $this->posy = $saved['y'];
        $this->lasth = $saved['lasth'];
        $this->lmargin = $saved['lmargin'];
        $this->rmargin = $saved['rmargin'];
        $this->cellpadding = $saved['padding'];
        $this->fontspacing = $savedspacing;
        $this->fontstretching = $savedstretching;
        $this->textshadow = $savedshadow;
        $this->cellheightratio = $savedcellheightratio;
        $this->setFont($saved['family'], $saved['style'], $saved['size']);
        $this->inheaderfooter = false;
        $this->decortotalpages = 0;
    }

    /**
     * Give a page a single region that starts at the page origin and ends far
     * below the page bottom, with the automatic page break disabled, so the
     * header and footer content (e.g. writeHTML()) never flows to another
     * column or page.
     *
     * @return array{region: array<int, array{RB: float, RH: float, RL: float, RR: float, RT: float, RW: float, RX: float, RY: float, x: float, y: float}>, columns: int, currentRegion: int, autobreak: bool} Original page layout.
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function setDecorationPageLayout(int $pid): array
    {
        $page = $this->engine()->page->getPage($pid);
        $original = [
            'region' => $page['region'],
            'columns' => $page['columns'],
            'currentRegion' => $page['currentRegion'],
            'autobreak' => $page['autobreak'],
        ];

        $width = $page['width'];
        $height = $page['height'] * 1000;
        $this->setEnginePageLayout($pid, [
            'region' => [[
                'RB' => $page['height'] - $height,
                'RH' => $height,
                'RL' => $width,
                'RR' => 0.0,
                'RT' => $height,
                'RW' => $width,
                'RX' => 0.0,
                'RY' => 0.0,
                'x' => 0.0,
                'y' => 0.0,
            ]],
            'columns' => 1,
            'currentRegion' => 0,
            'autobreak' => false,
        ]);

        return $original;
    }

    /**
     * Write the region and page break settings of an engine page.
     *
     * The engine exposes no mutator for the region list of an existing page:
     * the data is written directly into the page store.
     *
     * @param array{region: array<int, array{RB: float, RH: float, RL: float, RR: float, RT: float, RW: float, RX: float, RY: float, x: float, y: float}>, columns: int, currentRegion: int, autobreak: bool} $layout
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function setEnginePageLayout(int $pid, array $layout): void
    {
        $eng = $this->engine();
        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Page\Settings::class, 'page');
        $pages = $prop->getValue($eng->page);
        if (!is_array($pages) || !isset($pages[$pid]) || !is_array($pages[$pid])) {
            return;
        }

        /** @var array<int, array<string, mixed>> $pages */
        $pages[$pid] = array_merge($pages[$pid], $layout);
        $prop->setValue($eng->page, $pages);
    }

    // ===================================================================
    // Document settings.
    // ===================================================================

    /**
     * @throws \Exception
     */
    public function setPageUnit($unit)
    {
        if ($this->docstate >= 2) {
            $this->Error('setPageUnit() must be called before adding pages');
            return;
        }

        $family = $this->fontfamily;
        $style = $this->fontstyle;
        $size = $this->fontsizept;
        $this->engineInit((string) $unit);
        $this->setFont($family, $style, $size);
    }

    public function setPageOrientation($orientation, $autopagebreak = null, $bottommargin = null)
    {
        $this->curorientation = $this->normalizeOrientation($orientation);
        if ($autopagebreak !== null) {
            $this->autopagebreak = (bool) $autopagebreak;
        }

        if ($bottommargin !== null) {
            $this->bmargin = (float) $bottommargin;
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function setSpacesRE($re = '/[^\S\xa0]/')
    {
        $this->engine()->setSpaceRegexp((string) $re);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setRTL($enable, $resetx = true)
    {
        $this->rtlmode = (bool) $enable;
        $this->engine()->setRTL($this->rtlmode);
        if ((bool) $resetx) {
            $this->posx = $this->rtlmode ? $this->getPageWidth() - $this->rmargin : $this->lmargin;
        }
    }

    /**
     * @return bool Current RTL document direction.
     */
    public function getRTL()
    {
        return $this->rtlmode;
    }

    public function setTempRTL($mode)
    {
        $mode = is_string($mode) ? strtoupper($mode) : false;
        if (in_array($mode, ['R', 'RTL'], true)) {
            $this->tmprtl = 'R';
        } elseif (in_array($mode, ['L', 'LTR'], true)) {
            $this->tmprtl = 'L';
        } else {
            $this->tmprtl = false;
        }
    }

    /**
     * @return bool True when the current text direction is right-to-left.
     */
    public function isRTLTextDir()
    {
        return $this->rtlmode || $this->tmprtl === 'R';
    }

    public function setLastH($h)
    {
        $this->lasth = (float) $h;
    }

    /**
     * Return the cell height for the given font size.
     *
     * @param int|float $fontsize Font size in user units.
     * @param bool      $padding  When true include the top and bottom cell padding.
     *
     * @return float Cell height in user units.
     */
    public function getCellHeight($fontsize, $padding = true)
    {
        $height = (float) $fontsize * $this->cellheightratio;
        if ($padding) {
            $height += $this->cellpadding['T'] + $this->cellpadding['B'];
        }

        return round($height, 6);
    }

    public function resetLastH()
    {
        $this->lasth = $this->getCellHeight($this->getFontSize());
    }

    /**
     * @return float Height of the last printed cell in user units.
     */
    public function getLastH()
    {
        return $this->lasth;
    }

    public function setImageScale($scale)
    {
        $this->imgscale = (float) $scale;
    }

    /**
     * @return float Image scale ratio.
     */
    public function getImageScale()
    {
        return $this->imgscale;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getPageDimensions($pagenum = null)
    {
        $eng = $this->engine();
        $pid = $pagenum === null ? -1 : (int) $pagenum - 1;
        $page = $eng->page->getPage($pid);
        return [
            'w' => $page['pwidth'],
            'h' => $page['pheight'],
            'wk' => $page['width'],
            'hk' => $page['height'],
            'tm' => $page['margin']['CT'],
            'bm' => $page['margin']['CB'],
            'lm' => $page['margin']['PL'],
            'rm' => $page['margin']['PR'],
            'pb' => $page['autobreak'],
            'or' => $page['orientation'],
            'olm' => $this->orig_lmargin,
            'orm' => $this->orig_rmargin,
        ];
    }

    /**
     * Return the page width in user units.
     *
     * @param int|null $pagenum Page number (1-based) or null for the current page.
     *
     * @return float Page width in user units.
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getPageWidth($pagenum = null)
    {
        if ($this->docstate < 2) {
            $size = $this->engine()->page->getPageFormatSize(
                is_string($this->curformat) ? strtoupper($this->curformat) : 'A4',
                $this->curorientation,
            );
            return $size[0] / $this->kratio;
        }

        $pid = $pagenum === null ? -1 : (int) $pagenum - 1;
        $page = $this->engine()->page->getPage($pid);
        return $page['width'];
    }

    /**
     * Return the page height in user units.
     *
     * @param int|null $pagenum Page number (1-based) or null for the current page.
     *
     * @return float Page height in user units.
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getPageHeight($pagenum = null)
    {
        if ($this->docstate < 2) {
            $size = $this->engine()->page->getPageFormatSize(
                is_string($this->curformat) ? strtoupper($this->curformat) : 'A4',
                $this->curorientation,
            );
            return $size[1] / $this->kratio;
        }

        $pid = $pagenum === null ? -1 : (int) $pagenum - 1;
        $page = $this->engine()->page->getPage($pid);
        return $page['height'];
    }

    /**
     * Return the automatic page-break bottom margin.
     *
     * @param int|null $pagenum Page number (1-based) or null for the current page.
     *
     * @return float Break margin in user units.
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getBreakMargin($pagenum = null)
    {
        if ($this->docstate < 2) {
            return $this->bmargin;
        }

        $pid = $pagenum === null ? -1 : (int) $pagenum - 1;
        $page = $this->engine()->page->getPage($pid);
        return $page['margin']['CB'];
    }

    /**
     * @return float Document scale factor (number of points in user unit).
     */
    public function getScaleFactor()
    {
        return $this->kratio;
    }

    public function setMargins($left, $top, $right = null, $keepmargins = false)
    {
        $this->lmargin = (float) $left;
        $this->tmargin = (float) $top;
        $this->rmargin = $right === null ? (float) $left : (float) $right;
        if ((bool) $keepmargins) {
            $this->orig_lmargin = $this->lmargin;
            $this->orig_rmargin = $this->rmargin;
        }

        if ($this->docstate < 2) {
            $this->orig_lmargin = $this->lmargin;
            $this->orig_rmargin = $this->rmargin;
            $this->posx = $this->lmargin;
            $this->posy = $this->tmargin;
        }
    }

    public function setLeftMargin($margin)
    {
        $this->lmargin = (float) $margin;
        if ($this->docstate === 2 && $this->posx < $this->lmargin) {
            $this->posx = $this->lmargin;
        }
    }

    public function setTopMargin($margin)
    {
        $this->tmargin = (float) $margin;
        if ($this->docstate === 2 && $this->posy < $this->tmargin) {
            $this->posy = $this->tmargin;
        }
    }

    public function setRightMargin($margin)
    {
        $this->rmargin = (float) $margin;
    }

    /**
     * @throws \RuntimeException
     */
    public function setCellPadding($pad)
    {
        $this->setCellPaddings((float) $pad, (float) $pad, (float) $pad, (float) $pad);
    }

    /**
     * @throws \RuntimeException
     */
    public function setCellPaddings($left = null, $top = null, $right = null, $bottom = null)
    {
        if ($left !== null && (float) $left >= 0) {
            $this->cellpadding['L'] = (float) $left;
        }

        if ($top !== null && (float) $top >= 0) {
            $this->cellpadding['T'] = (float) $top;
        }

        if ($right !== null && (float) $right >= 0) {
            $this->cellpadding['R'] = (float) $right;
        }

        if ($bottom !== null && (float) $bottom >= 0) {
            $this->cellpadding['B'] = (float) $bottom;
        }

        $this->engine()->setDefaultCellPadding(
            $this->cellpadding['T'],
            $this->cellpadding['R'],
            $this->cellpadding['B'],
            $this->cellpadding['L'],
        );
    }

    /**
     * @return array{L: float, T: float, R: float, B: float} Cell padding in user units.
     */
    public function getCellPaddings()
    {
        return $this->cellpadding;
    }

    /**
     * @throws \RuntimeException
     */
    public function setCellMargins($left = null, $top = null, $right = null, $bottom = null)
    {
        if ($left !== null && (float) $left >= 0) {
            $this->cellmargin['L'] = (float) $left;
        }

        if ($top !== null && (float) $top >= 0) {
            $this->cellmargin['T'] = (float) $top;
        }

        if ($right !== null && (float) $right >= 0) {
            $this->cellmargin['R'] = (float) $right;
        }

        if ($bottom !== null && (float) $bottom >= 0) {
            $this->cellmargin['B'] = (float) $bottom;
        }

        $this->engine()->setDefaultCellMargin(
            $this->cellmargin['T'],
            $this->cellmargin['R'],
            $this->cellmargin['B'],
            $this->cellmargin['L'],
        );
    }

    /**
     * @return array{L: float, T: float, R: float, B: float} Cell margins in user units.
     */
    public function getCellMargins()
    {
        return $this->cellmargin;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setAutoPageBreak($auto, $margin = 0)
    {
        $this->autopagebreak = (bool) $auto;
        $this->bmargin = (float) $margin;
        if ($this->docstate === 2) {
            $this->engine()->page->enableAutoPageBreak($this->autopagebreak);
        }
    }

    /**
     * @return bool Automatic page break state.
     */
    public function getAutoPageBreak()
    {
        return $this->autopagebreak;
    }

    /**
     * @throws \RuntimeException
     */
    public function setDisplayMode($zoom, $layout = 'SinglePage', $mode = 'UseNone')
    {
        $zoom = is_numeric($zoom) ? (int) $zoom : (string) $zoom;
        $this->displaymode = ['zoom' => $zoom, 'layout' => (string) $layout, 'mode' => (string) $mode];
        $this->engine()->setDisplayMode($zoom, (string) $layout, (string) $mode);
    }

    public function setCompression($compress = true)
    {
        // Stream compression is configured at engine construction time and
        // is always enabled there; disabling it is not supported.
    }

    /**
     * @throws \RuntimeException
     */
    public function setSRGBmode($mode = false)
    {
        $this->engine()->setSRGB((bool) $mode);
    }

    public function setDocInfoUnicode($unicode = true)
    {
        // The engine always encodes document information in Unicode.
    }

    /**
     * @throws \RuntimeException
     */
    public function setTitle($title)
    {
        $this->engine()->setTitle((string) $title);
    }

    /**
     * @throws \RuntimeException
     */
    public function setSubject($subject)
    {
        $this->engine()->setSubject((string) $subject);
    }

    /**
     * @throws \RuntimeException
     */
    public function setAuthor($author)
    {
        $this->engine()->setAuthor((string) $author);
    }

    /**
     * @throws \RuntimeException
     */
    public function setKeywords($keywords)
    {
        $this->engine()->setKeywords((string) $keywords);
    }

    /**
     * @throws \RuntimeException
     */
    public function setCreator($creator)
    {
        $this->engine()->setCreator((string) $creator);
    }

    public function setAllowLocalFiles($allowLocalFiles)
    {
        // The engine restricts file access via constructor-bound options;
        // the legacy toggle has no effect on the modern engine.
    }

    /**
     * @throws \Exception
     */
    public function Error($msg)
    {
        $this->pdfraw = '';
        if (defined('K_TCPDF_THROW_EXCEPTION_ERROR') && !constant('K_TCPDF_THROW_EXCEPTION_ERROR')) {
            // die() is a language construct before PHP 8.4: no trailing comma,
            // so the message is built before the call.
            $out = htmlspecialchars((string) $msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            die('<strong>Tcpdf ERROR: </strong>' . $out);
        }

        throw new \Exception('Tcpdf ERROR: ' . (string) $msg);
    }

    public function Open()
    {
        $this->docstate = max(1, $this->docstate);
    }

    /**
     * @throws \Throwable
     */
    public function Close()
    {
        if ($this->docstate === 3) {
            return;
        }

        if ($this->docstate < 2) {
            $this->AddPage();
        }

        $this->decorateAllPages();
        $this->applyBookmarkDisplayMode();
        $this->pdfraw = $this->engine()->getOutPDFString();
        $this->docstate = 3;
    }

    /**
     * Match legacy Tcpdf: when bookmark outlines are present the document
     * opens with the outline (bookmark) panel visible (/PageMode /UseOutlines).
     *
     * The engine only promotes the page mode to UseOutlines when it is the
     * empty string, but the default mode is UseNone, so that promotion never
     * fires on its own. Apply it here from the facade, preserving the tracked
     * zoom and layout, unless the user explicitly selected a non-default
     * display mode.
     *
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function applyBookmarkDisplayMode(): void
    {
        if ($this->engineOutlines() === []) {
            return;
        }

        if (!in_array($this->displaymode['mode'], ['', 'UseNone'], true)) {
            return;
        }

        $this->engine()->setDisplayMode($this->displaymode['zoom'], $this->displaymode['layout'], 'UseOutlines');
    }

    // ===================================================================
    // Page handling.
    // ===================================================================

    /**
     * @throws \Exception
     */
    public function setPage($pnum, $resetmargins = false)
    {
        $pnum = (int) $pnum;
        if ($pnum < 1 || $pnum > $this->getNumPages()) {
            $this->Error('Wrong page number on setPage() function: ' . $pnum);
            return;
        }

        $this->engine()->setCurrentPage($pnum - 1);
        if ((bool) $resetmargins) {
            $this->lmargin = $this->orig_lmargin;
            $this->rmargin = $this->orig_rmargin;
            $this->posy = $this->tmargin;
        }
    }

    /**
     * @throws \Exception
     */
    public function lastPage($resetmargins = false)
    {
        $this->setPage($this->getNumPages(), $resetmargins);
    }

    /**
     * @return int Current page number (1-based, 0 when no page exists).
     * @throws \RuntimeException
     */
    public function getPage()
    {
        if ($this->docstate < 2) {
            return 0;
        }

        return (int) $this->engine()->page->getPageId() + 1;
    }

    /**
     * @return int Total number of pages.
     * @throws \RuntimeException
     */
    public function getNumPages()
    {
        if ($this->docstate < 2) {
            return 0;
        }

        return count($this->engine()->page->getPages());
    }

    /**
     * @throws \Exception
     */
    public function addTOCPage($orientation = '', $format = '', $keepmargins = false)
    {
        $this->AddPage($orientation, $format, $keepmargins, true);
    }

    public function endTOCPage()
    {
        $this->endPage(true);
    }

    /**
     * @throws \Exception
     */
    public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false)
    {
        if ($this->docstate === 3) {
            $this->Error('Unable to add pages on a closed document');
            return;
        }

        if ((bool) $keepmargins) {
            $this->orig_lmargin = $this->lmargin;
            $this->orig_rmargin = $this->rmargin;
        }

        $this->endPage();
        $this->startPage($orientation, $format, $tocpage);
    }

    public function endPage($tocpage = false)
    {
        // Headers and footers are rendered for all pages when the document
        // is closed, so no other per-page action is required here.
        if ((bool) $tocpage || $this->tocpage === true) {
            $this->tocpage = false;
        }
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function startPage($orientation = '', $format = '', $tocpage = false)
    {
        if ((bool) $tocpage) {
            $this->tocpage = true;
        }

        $eng = $this->engine();
        $eng->addPage($this->buildPageData($orientation, $format));
        $this->docstate = 2;
        $this->pagedecor[$this->getPage() - 1] = [
            'header' => $this->printheader,
            'footer' => $this->printfooter,
        ];
        $this->posx = $this->rtlmode ? $this->getPageWidth() - $this->rmargin : $this->lmargin;
        $this->posy = $this->tmargin;
        if ($this->inColumnMode()) {
            // Legacy: the columns of the new page carry the column margins
            // and the cursor of the selected (first) column.
            $this->selectColumn();
        }

        // The new page content stream already starts with the ambient text
        // state: the engine re-emits the current font and the page context
        // hook re-emits the facade state (see ambientPageContent()).
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setPageMark()
    {
        if ($this->docstate === 2) {
            $this->engine()->page->addContentMark();
        }
    }

    public function setHeaderData($ln = '', $lw = 0, $ht = '', $hs = '', $tc = [0, 0, 0], $lc = [0, 0, 0])
    {
        $this->headerdata = [
            'logo' => (string) $ln,
            'logo_width' => (float) $lw,
            'title' => (string) $ht,
            'string' => (string) $hs,
            'text_color' => $this->legacyColorComponents($tc, [0, 0, 0]),
            'line_color' => $this->legacyColorComponents($lc, [0, 0, 0]),
        ];
    }

    public function setFooterData($tc = [0, 0, 0], $lc = [0, 0, 0])
    {
        $this->footerdata = [
            'text_color' => $this->legacyColorComponents($tc, [0, 0, 0]),
            'line_color' => $this->legacyColorComponents($lc, [0, 0, 0]),
        ];
    }

    /**
     * @return array{logo: string, logo_width: float, title: string, string: string, text_color: array<int, float|int|string>, line_color: array<int, float|int|string>} Header data.
     */
    public function getHeaderData()
    {
        return $this->headerdata;
    }

    public function setHeaderMargin($hm = 10)
    {
        $this->headermargin = (float) $hm;
    }

    /**
     * @return float Header margin in user units.
     */
    public function getHeaderMargin()
    {
        return $this->headermargin;
    }

    public function setFooterMargin($fm = 10)
    {
        $this->footermargin = (float) $fm;
    }

    /**
     * @return float Footer margin in user units.
     */
    public function getFooterMargin()
    {
        return $this->footermargin;
    }

    public function setPrintHeader($val = true)
    {
        $this->printheader = (bool) $val;
    }

    /**
     * @throws \RuntimeException
     */
    public function setPrintFooter($val = true)
    {
        $this->printfooter = (bool) $val;
        if ($this->docstate === 2) {
            $pid = $this->getPage() - 1;
            if (isset($this->pagedecor[$pid])) {
                $this->pagedecor[$pid]['footer'] = $this->printfooter;
            }
        }
    }

    /**
     * @return float Right-bottom X coordinate of the last inserted image.
     */
    public function getImageRBX()
    {
        return $this->imagerbx;
    }

    /**
     * @return float Right-bottom Y coordinate of the last inserted image.
     */
    public function getImageRBY()
    {
        return $this->imagerby;
    }

    public function resetHeaderTemplate()
    {
        // The facade renders headers directly (no cached XObject template).
    }

    public function setHeaderTemplateAutoreset($val = true)
    {
        // The facade renders headers directly (no cached XObject template).
    }

    /**
     * @throws \Throwable
     */
    public function Header()
    {
        $headerfont = $this->getHeaderFont();
        $headerdata = $this->getHeaderData();
        $this->posy = $this->headermargin;
        $this->posx = $this->orig_lmargin;
        $imgy = $this->posy;
        $logo = $headerdata['logo'];
        if ($logo !== '' && $logo !== K_BLANK_IMAGE && defined('K_PATH_IMAGES')) {
            $logofile = K_PATH_IMAGES . $logo;
            if (is_file($logofile)) {
                $this->Image($logofile, '', '', $headerdata['logo_width']);
                $imgy = $this->getImageRBY();
            }
        }

        $cell_height = $this->getCellHeight($headerfont[2] / $this->kratio);
        $header_x = $this->orig_lmargin + ($headerdata['logo_width'] * 1.1);
        $cw = $this->getPageWidth() - $this->orig_lmargin - $this->orig_rmargin - ($headerdata['logo_width'] * 1.1);
        $this->setTextColorArray($headerdata['text_color']);
        // header title
        $this->setFont($headerfont[0], 'B', $headerfont[2] + 1);
        $this->setX($header_x);
        $this->Cell($cw, $cell_height, $headerdata['title'], 0, 1, '', 0, '', 0);
        // header string
        $this->setFont($headerfont[0], $headerfont[1], $headerfont[2]);
        $this->setX($header_x);
        $this->MultiCell(
            $cw,
            $cell_height,
            $headerdata['string'],
            0,
            '',
            0,
            1,
            '',
            '',
            true,
            0,
            false,
            true,
            0,
            'T',
            false,
        );
        // header line
        $this->setLineStyle([
            'width' => 0.85 / $this->kratio,
            'cap' => 'butt',
            'join' => 'miter',
            'dash' => 0,
            'color' => $headerdata['line_color'],
        ]);
        $this->setY((2.835 / $this->kratio) + max($imgy, $this->posy));
        $this->setX($this->orig_lmargin);
        $this->Cell($this->getPageWidth() - $this->orig_lmargin - $this->orig_rmargin, 0, '', 'T', 0, 'C');
    }

    /**
     * @throws \Exception
     */
    public function Footer()
    {
        $cur_y = $this->posy;
        $this->setTextColorArray($this->footerdata['text_color']);
        $line_width = 0.85 / $this->kratio;
        $this->setLineStyle([
            'width' => $line_width,
            'cap' => 'butt',
            'join' => 'miter',
            'dash' => 0,
            'color' => $this->footerdata['line_color'],
        ]);
        $w_page = isset($this->langdata['w_page']) ? (string) $this->langdata['w_page'] . ' ' : '';
        if ($this->pagegroupsused) {
            $pagenumtxt = $w_page . $this->getPageNumGroupAlias() . ' / ' . $this->getPageGroupAlias();
        } else {
            $pagenumtxt = $w_page . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages();
        }
        $this->setY($cur_y);
        $this->setX($this->orig_lmargin);
        $this->Cell(0, 0, $pagenumtxt, 'T', 0, 'R');
    }

    /**
     * @return int Current page number.
     * @throws \RuntimeException
     */
    public function PageNo()
    {
        return $this->getPage();
    }

    // ===================================================================
    // Colors.
    // ===================================================================

    /**
     * @throws \RuntimeException
     */
    public function getAllSpotColors()
    {
        return $this->engine()->color->getSpotColors();
    }

    /**
     * @throws \Com\Tecnick\Color\Exception
     * @throws \RuntimeException
     */
    public function AddSpotColor($name, $c, $m, $y, $k)
    {
        $cmyk = new \Com\Tecnick\Color\Model\Cmyk([
            'cyan' => (float) $c / 100,
            'magenta' => (float) $m / 100,
            'yellow' => (float) $y / 100,
            'key' => (float) $k / 100,
            'alpha' => 1,
        ]);
        $this->engine()->color->addSpotColor((string) $name, $cmyk);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setSpotColor($type, $name, $tint = 100)
    {
        $eng = $this->engine();
        $name = (string) $name;
        $tint = (float) $tint / 100;
        $type = strtolower((string) $type);
        $cmd = '';
        switch ($type) {
            case 'draw':
                $this->drawcolorspec = $name;
                $cmd = $eng->color->getPdfColor($name, true, $tint);
                break;
            case 'fill':
                $this->fillcolorspec = $name;
                $cmd = $eng->color->getPdfColor($name, false, $tint);
                break;
            case 'text':
                $this->textcolorspec = $name;
                return '';
        }

        $this->emitToPage($cmd);
        return $cmd;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setDrawSpotColor($name, $tint = 100)
    {
        return $this->setSpotColor('draw', $name, $tint);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setFillSpotColor($name, $tint = 100)
    {
        return $this->setSpotColor('fill', $name, $tint);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setTextSpotColor($name, $tint = 100)
    {
        return $this->setSpotColor('text', $name, $tint);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setColorArray($type, $color, $ret = false)
    {
        $color = is_array($color) ? $color : [0, 0, 0];
        $values = array_values($color);
        return $this->setColor(
            (string) $type,
            (float) ($values[0] ?? 0),
            (float) ($values[1] ?? -1),
            (float) ($values[2] ?? -1),
            (float) ($values[3] ?? -1),
            (bool) $ret,
            isset($values[4]) && is_string($values[4]) ? $values[4] : '',
        );
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setDrawColorArray($color, $ret = false)
    {
        return $this->setColorArray('draw', $color, $ret);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setFillColorArray($color, $ret = false)
    {
        return $this->setColorArray('fill', $color, $ret);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setTextColorArray($color, $ret = false)
    {
        return $this->setColorArray('text', $color, $ret);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setColor($type, $col1 = 0, $col2 = -1, $col3 = -1, $col4 = -1, $ret = false, $name = '')
    {
        $spec = $this->colorSpecFromComponents($col1, $col2, $col3, $col4, (string) $name);
        $legacy = [(float) $col1, (float) $col2, (float) $col3, (float) $col4];
        $eng = $this->engine();
        $type = strtolower((string) $type);
        $cmd = '';
        switch ($type) {
            case 'draw':
                $this->drawcolorspec = $spec;
                $this->drawcolorlegacy = $legacy;
                $cmd = $eng->color->getPdfColor($spec, true);
                break;
            case 'fill':
                $this->fillcolorspec = $spec;
                $this->fillcolorlegacy = $legacy;
                $cmd = $eng->color->getPdfFillColor($spec);
                break;
            case 'text':
                $this->textcolorspec = $spec;
                $this->textcolorlegacy = $legacy;
                // Text color is emitted before each text operation.
                return '';
        }

        if ((bool) $ret) {
            return $cmd;
        }

        $this->emitToPage($cmd);
        return $cmd;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setDrawColor($col1 = 0, $col2 = -1, $col3 = -1, $col4 = -1, $ret = false, $name = '')
    {
        return $this->setColor('draw', $col1, $col2, $col3, $col4, $ret, $name);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setFillColor($col1 = 0, $col2 = -1, $col3 = -1, $col4 = -1, $ret = false, $name = '')
    {
        return $this->setColor('fill', $col1, $col2, $col3, $col4, $ret, $name);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setTextColor($col1 = 0, $col2 = -1, $col3 = -1, $col4 = -1, $ret = false, $name = '')
    {
        return $this->setColor('text', $col1, $col2, $col3, $col4, $ret, $name);
    }

    // ===================================================================
    // Fonts and text metrics.
    // ===================================================================

    /**
     * Return the length of a string in user units.
     *
     * @param string $s         Input string.
     * @param string $fontname  Optional family to measure with (default: current font).
     * @param string $fontstyle Optional font style.
     * @param int|float $fontsize Optional font size in points.
     * @param bool   $getarray  When true return an array of per-character widths.
     *
     * @return float|array<int, float> Total width, or per-character widths.
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function GetStringWidth($s, $fontname = '', $fontstyle = '', $fontsize = 0, $getarray = false)
    {
        return $this->GetArrStringWidth(
            array_values($this->engine()->uniconv->strToOrdArr($s)),
            $fontname,
            $fontstyle,
            $fontsize,
            $getarray,
        );
    }

    /**
     * Return the width of a character array in user units.
     *
     * @param array<int, int|string> $sa Characters (codepoints or strings).
     * @param string $fontname  Optional family to measure with (default: current font).
     * @param string $fontstyle Optional font style.
     * @param int|float $fontsize Optional font size in points.
     * @param bool   $getarray  When true return an array of per-character widths.
     *
     * @return float|array<int, float> Total width, or per-character widths.
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function GetArrStringWidth($sa, $fontname = '', $fontstyle = '', $fontsize = 0, $getarray = false)
    {
        $eng = $this->engine();
        $ordarr = [];
        foreach ($sa as $item) {
            $ordarr[] = is_int($item) ? $item : $eng->uniconv->ord($item);
        }

        // The engine font stack already carries the ambient spacing/stretching
        // (set via setFont/setFontStretching/setFontSpacing): its glyph widths
        // are scaled by the stretching ratio and getOrdArrDims() folds in the
        // inter-character spacing. A temporary measuring font inherits the same
        // ambient values so the result stays consistent with rendering.
        $usetemp = $fontname !== '';
        if ($usetemp) {
            $size = (float) $fontsize > 0 ? (float) $fontsize : $this->fontsizept;
            $eng->font->insert(
                $eng->pon,
                $fontname,
                $fontstyle,
                $size,
                $this->fontspacing * $this->kratio,
                $this->fontstretching / 100.0,
            );
        }

        if ($getarray) {
            $charspacing = $this->fontspacing * ($this->fontstretching / 100.0);
            $widths = [];
            foreach ($ordarr as $ord) {
                $widths[] = ($eng->font->getCharWidth((int) $ord) / $this->kratio) + $charspacing;
            }

            $result = $widths;
        } else {
            $dim = $eng->font->getOrdArrDims($ordarr);
            $result = $dim['totwidth'] / $this->kratio;
        }

        if ($usetemp) {
            $eng->font->popLastFont();
        }

        return $result;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function GetCharWidth($char, $notlast = true)
    {
        $char = is_int($char) ? $char : (string) $char;
        return $this->getRawCharWidth($char);
    }

    /**
     * @param int|string $char Character codepoint or string.
     *
     * @return float Character width in user units.
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function getRawCharWidth($char)
    {
        $ord = is_int($char) ? $char : $this->engine()->uniconv->ord($char);
        return $this->engine()->font->getCharWidth($ord) / $this->kratio;
    }

    /**
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function GetNumChars($s)
    {
        return count($this->engine()->uniconv->strToOrdArr((string) $s));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function AddFont($family, $style = '', $fontfile = '', $subset = 'default')
    {
        $eng = $this->engine();
        $subset = $subset === 'default' ? $this->fontsubsetting : (bool) $subset;
        $metric = $eng->font->insert(
            $eng->pon,
            strtolower((string) $family),
            (string) $style,
            null,
            null,
            null,
            (string) $fontfile,
            $subset,
        );
        $eng->font->popLastFont();
        return [
            'fontkey' => $metric['key'],
            'family' => strtolower((string) $family),
            'style' => $metric['style'],
        ];
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setFont($family, $style = '', $size = null, $fontfile = '', $subset = 'default', $out = true)
    {
        $eng = $this->engine();
        $family = strtolower(trim((string) $family));
        if ($family === '') {
            $family = $this->fontfamily;
        }

        $style = strtoupper((string) $style);
        $match = [];
        if (preg_match('/^(courier|helvetica|times)(bi|b|i)$/', $family, $match) === 1) {
            // Legacy core font names may carry the style suffix in the
            // family ('courierB'); the engine expects family + style.
            $family = $match[1];
            $style .= strtoupper($match[2]);
        }
        $this->fontdecor = [
            'U' => str_contains($style, 'U'),
            'D' => str_contains($style, 'D'),
            'O' => str_contains($style, 'O'),
        ];
        $fontstyle = str_replace(['U', 'D', 'O'], '', $style);
        $size = $size === null || (float) $size <= 0 ? $this->fontsizept : (float) $size;
        $subset = $subset === 'default' ? $this->fontsubsetting : (bool) $subset;

        // Carry the current font spacing/stretching into the engine font
        // stack: the engine lays text out with the stretching as a ratio and
        // emits the matching Tz/Tc text-state operators (paired with their
        // resets) per text run, so every text path (Cell/Text/Write/HTML)
        // renders consistently. The legacy API stores stretching as a
        // percentage and spacing in user units; the engine wants a ratio and
        // points respectively.
        $this->fontmetric = $eng->font->insert(
            $eng->pon,
            $family,
            $fontstyle,
            $size,
            $this->fontspacing * $this->kratio,
            $this->fontstretching / 100.0,
            (string) $fontfile,
            $subset,
        );
        $this->fontfamily = $family;
        $this->fontstyle = $fontstyle;
        $this->fontsizept = $size;
        if ($this->xobjtid !== '') {
            // Fonts used inside an XObject template must be listed in the
            // template resource dictionary.
            $eng->addXObjectFontID($this->xobjtid, $this->fontmetric['key']);
        }

        if ((bool) $out) {
            $this->emitToPage($this->fontmetric['out']);
        }
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setFontSize($size, $out = true)
    {
        $this->setFont($this->fontfamily, $this->getFontStyle(), (float) $size, '', 'default', $out);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getFontBBox()
    {
        $metric = $this->currentFontMetric();
        $fbbox = is_array($metric['fbbox'] ?? null) ? $metric['fbbox'] : [0.0, 0.0, 0.0, 0.0];
        return array_map(fn(mixed $val): float => (float) $val / $this->kratio, array_values($fbbox));
    }

    public function getAbsFontMeasure($s)
    {
        return (((float) $s * $this->fontsizept) / 1000.0) / $this->kratio;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function getCharBBox($char)
    {
        $ord = is_int($char) ? $char : $this->engine()->uniconv->ord((string) $char);
        $bbox = $this->engine()->font->getCharBBox($ord);
        return array_map(fn(mixed $val): float => $val / $this->kratio, $bbox);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function getFontDescent($font, $style = '', $size = 0)
    {
        $eng = $this->engine();
        $eng->font->insert($eng->pon, strtolower((string) $font), (string) $style, (float) $size);
        $metric = $eng->font->getCurrentFont();
        $eng->font->popLastFont();
        return abs($metric['descent']) / $this->kratio;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function getFontAscent($font, $style = '', $size = 0)
    {
        $eng = $this->engine();
        $eng->font->insert($eng->pon, strtolower((string) $font), (string) $style, (float) $size);
        $metric = $eng->font->getCurrentFont();
        $eng->font->popLastFont();
        return $metric['ascent'] / $this->kratio;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function isCharDefined($char, $font = '', $style = '')
    {
        $eng = $this->engine();
        $ord = is_int($char) ? $char : $eng->uniconv->ord((string) $char);
        $usetemp = (string) $font !== '';
        if ($usetemp) {
            $eng->font->insert($eng->pon, strtolower((string) $font), (string) $style, null);
        }

        $defined = $eng->font->isCharDefined($ord);
        if ($usetemp) {
            $eng->font->popLastFont();
        }

        return $defined;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function replaceMissingChars($text, $font = '', $style = '', $subs = [])
    {
        $eng = $this->engine();
        $usetemp = (string) $font !== '';
        if ($usetemp) {
            $eng->font->insert($eng->pon, strtolower((string) $font), (string) $style, null);
        }

        $ordarr = $eng->uniconv->strToOrdArr((string) $text);
        $inputSubs = $subs;
        $subs = [];
        foreach (is_array($inputSubs) ? $inputSubs : [] as $key => $values) {
            $subs[(int) $key] = array_map(static fn(mixed $val): int => (int) $val, (array) $values);
        }

        $ordarr = $eng->font->replaceMissingChars(array_values($ordarr), $subs);
        if ($usetemp) {
            $eng->font->popLastFont();
        }

        return implode('', $eng->uniconv->ordArrToChrArr($ordarr));
    }

    public function setDefaultMonospacedFont($font)
    {
        $this->monospacedfont = strtolower((string) $font);
    }

    // ===================================================================
    // Links and annotations.
    // ===================================================================

    /**
     * @throws \RuntimeException
     */
    public function AddLink()
    {
        $lid = count($this->internallinks) + 1;
        $this->internallinks[$lid] = [
            'page' => max(0, $this->getPage() - 1),
            'y' => $this->posy,
        ];
        return $lid;
    }

    /**
     * @throws \RuntimeException
     */
    public function setLink($link, $y = 0, $page = -1)
    {
        $lid = (int) $link;
        if (!isset($this->internallinks[$lid])) {
            return;
        }

        $posy = (float) $y === -1.0 ? $this->posy : (float) $y;
        $page = (int) $page;
        $this->internallinks[$lid] = [
            'page' => $page < 1 ? max(0, $this->getPage() - 1) : $page - 1,
            'y' => $posy,
        ];
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Link($x, $y, $w, $h, $link, $spaces = 0)
    {
        $this->attachLink($link, (float) $x, (float) $y, (float) $w, (float) $h);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Annotation($x, $y, $w, $h, $text, $opt = ['Subtype' => 'Text'], $spaces = 0)
    {
        if ($this->docstate !== 2) {
            return;
        }

        $inputOptions = $opt;
        $opt = [];
        foreach (is_array($inputOptions) ? $inputOptions : [] as $key => $val) {
            $key = strtolower((string) $key);
            if ($key === 'fs' && is_string($val)) {
                $val = $this->resolveLocalFile($val);
                if (!$this->isAllowedLocalFile($val)) {
                    // The legacy implementation silently skipped attachments
                    // whose source file cannot be read.
                    return;
                }
            }

            $opt[$key] = $val;
        }

        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        /** @var TAnnotOpts $opt */
        $oid = $this->engine()->setAnnotation($posx, $posy, (float) $w, (float) $h, (string) $text, $opt);
        $this->attachAnnotRef($oid);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function EmbedFile($opt)
    {
        $opt = is_array($opt) ? $opt : ['file' => (string) $opt];
        $file = $this->resolveLocalFile((string) ($opt['file'] ?? ''));
        if ($file === '') {
            return;
        }

        $this->engine()->addEmbeddedFile(
            $file,
            (string) ($opt['mime'] ?? 'application/octet-stream'),
            (string) ($opt['afrel'] ?? 'Source'),
            (string) ($opt['desc'] ?? ''),
        );
    }

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function EmbedFileFromString($filename, $content)
    {
        $this->engine()->addContentAsEmbeddedFile((string) $content, (string) $filename);
    }

    // ===================================================================
    // Text rendering.
    // ===================================================================

    /**
     * @throws \Exception
     */
    public function Text(
        $x,
        $y,
        $txt,
        $fstroke = 0,
        $fclip = false,
        $ffill = true,
        $border = 0,
        $ln = 0,
        $align = '',
        $fill = false,
        $link = '',
        $stretch = 0,
        $ignore_min_height = false,
        $calign = 'T',
        $valign = 'M',
        $rtloff = false,
    ) {
        $this->setXY((float) $x, (float) $y, (bool) $rtloff);
        $this->Cell(
            0,
            0,
            $txt,
            $border,
            $ln,
            $align,
            $fill,
            $link,
            $stretch,
            $ignore_min_height,
            $calign,
            $valign,
        );
    }

    /**
     * @return bool True when an automatic page break is allowed.
     */
    public function AcceptPageBreak()
    {
        return $this->autopagebreak;
    }

    /**
     * @throws \Exception
     */
    public function Cell(
        $w,
        $h = 0,
        $txt = '',
        $border = 0,
        $ln = 0,
        $align = '',
        $fill = false,
        $link = '',
        $stretch = 0,
        $ignore_min_height = false,
        $calign = 'T',
        $valign = 'M',
    ) {
        if ($this->docstate !== 2) {
            $this->Error('Unable to print a cell: no page has been started');
            return;
        }

        $eng = $this->engine();
        $txt = (string) $txt;

        // A border reserves a minimum cell padding (and an external/internal
        // border position) like legacy adjustCellPadding(); the implied top and
        // bottom padding grow the auto cell height so the stroke is contained.
        $bordermetrics = $this->legacyBorderCellMetrics($border);
        $borderpos = $bordermetrics['pos'];
        $borderpadding = $bordermetrics['padding'];

        $height = (float) $h;
        if (!(bool) $ignore_min_height) {
            $minheight =
                $this->getCellHeight($this->getFontSize()) + $borderpadding['T'] - $this->cellpadding['T']
                + ($borderpadding['B'] - $this->cellpadding['B']);
            $height = max($height, $minheight);
        }

        $this->breakIfNeeded($height);

        $width = (float) $w;
        if ($width <= 0) {
            $width = $this->rtlmode
                ? max(0.0, $this->posx - $this->lmargin)
                : $this->getPageWidth() - $this->rmargin - $this->posx;
        }

        // In RTL the cursor X marks the cell's right edge; the engine draws
        // every cell from its left edge, so shift left by the cell width.
        // The ln cursor advance below keeps using $this->posx.
        $cellx = $this->rtlmode ? $this->posx - $width : $this->posx;

        // Legacy $calign shifts the whole cell box vertically relative to the
        // text line; the engine still positions the text inside the box per
        // $valign. Apply the shift to the draw Y only (the ln cursor advance
        // below uses the original Y, like getCellCode()).
        $cellposy = $this->posy;
        $this->posy -= $this->cellCalignShift((string) $calign, (string) $valign, $height);

        $styles = $this->stylesFromLegacyBorder($border, (bool) $fill);
        $out = $eng->color->getPdfFillColor($this->textcolorspec);

        // For an external/internal border the engine offsets the stroke by
        // (line width * borderpos) and derives the matching text padding; the
        // default position keeps the byte-identical null-cell path.
        $engcell = null;
        if ($borderpos !== \Com\Tecnick\Pdf\Tcpdf::BORDERPOS_DEFAULT) {
            $engcell = \Com\Tecnick\Pdf\Tcpdf::ZEROCELL;
            $engcell['borderpos'] = $borderpos;
        }

        // Legacy stretch modes: 1 scale / 3 space when the text exceeds the
        // cell width; 2 force scaling / 4 force spacing to fill it exactly.
        $stretchmode = (int) $stretch;
        $stretchtextw = 0.0;
        if ($stretchmode > 0 && $txt !== '') {
            // True rendered width per the PDF imaging model: character
            // spacing (Tc) applies to every glyph and is scaled by the
            // horizontal scaling (Tz) together with the glyph widths. The fit
            // math is expressed against the natural glyph widths, so measure
            // with the ambient spacing/stretching neutralised.
            $stretchraw = $this->naturalOrdWidth($txt);
            $stretchchars = (int) $this->GetNumChars($txt);
            $stretchtextw = (($stretchraw + ($this->fontspacing * $stretchchars)) * $this->fontstretching) / 100;
            $stretchavail = $width - $this->cellpadding['L'] - $this->cellpadding['R'];
            if ($stretchtextw <= 0 || $stretchmode !== 2 && $stretchmode !== 4 && $stretchtextw <= $stretchavail) {
                $stretchmode = 0;
            }
        }

        // Legacy Cell never wraps. Text that may not fit the inner cell
        // width is drawn on one line by overflowCellOutput(). The width
        // estimate skips the engine text preparation (cleanup, Bidi,
        // shaping, hyphenation), so near-fitting text takes the same path;
        // its rendering is unchanged when the text fits.
        $txtw = 0.0;
        if ($stretchmode === 0 && $txt !== '') {
            $txtw = $stretchtextw > 0 ? $stretchtextw : $this->engineTextWidth($txt);
            $innerw = $width - $borderpadding['L'] - $borderpadding['R'];
            if ($txtw <= ($innerw * 0.95)) {
                $txtw = 0.0;
            }
        }

        if ($txtw > 0) {
            $out .= $this->overflowCellOutput(
                $txt,
                $cellx,
                $width,
                $height,
                $txtw,
                $this->halignToEngine($align),
                $this->valignToEngine($valign === 'M' ? 'C' : $valign),
                $engcell,
                $styles,
            );
        } elseif ($stretchmode > 0 && $txt !== '') {
            // Box and text drawn separately: the border must keep the
            // requested geometry while the text run is scaled or spaced.
            $out .= $this->cellBoxOutput($cellx, $this->posy, $width, $height, $styles, (bool) $fill);
            $out .= $this->stretchedCellText(
                $stretchmode,
                $txt,
                $cellx,
                $width,
                $height,
                $stretchtextw,
                $this->valignToEngine($valign === 'M' ? 'C' : $valign),
                $this->engineShadow(),
            );
        } elseif ($txt !== '') {
            $out .= $eng->getTextCell(
                $txt,
                $cellx,
                $this->posy,
                $width,
                $height,
                0,
                0,
                $this->valignToEngine($valign === 'M' ? 'C' : $valign),
                $this->halignToEngine($align),
                $engcell,
                $styles,
                $this->textrendermode['stroke'],
                0,
                0,
                0,
                true,
                $this->textrendermode['fill'],
                $this->textrendermode['stroke'] > 0,
                $this->fontdecor['U'],
                $this->fontdecor['D'],
                $this->fontdecor['O'],
                $this->textrendermode['clip'],
                true,
                $this->forcedTextDir(),
                $this->engineShadow(),
            );
        } elseif ($styles !== []) {
            // Empty text: getTextCell() would return nothing, but the legacy
            // Cell still draws the background fill and the requested border
            // sides (and only those: a 'T' border is a single line, not a
            // rectangle).
            if ((bool) $fill) {
                $out .= $eng->graph->getBasicRect($cellx, $this->posy, $width, $height, 'f', [
                    'lineWidth' => 0.0,
                    'fillColor' => $this->fillcolorspec,
                ]);
            }

            $sides = [
                'T' => [$cellx, $this->posy, $cellx + $width, $this->posy],
                'R' => [$cellx + $width, $this->posy, $cellx + $width, $this->posy + $height],
                'B' => [$cellx + $width, $this->posy + $height, $cellx, $this->posy + $height],
                'L' => [$cellx, $this->posy + $height, $cellx, $this->posy],
            ];
            foreach ($sides as $letter => $line) {
                $sty = $styles[$letter] ?? $styles['all'] ?? null;
                if (is_array($sty) && isset($sty['lineWidth']) && $sty['lineWidth'] > 0) {
                    $out .= $eng->graph->getLine($line[0], $line[1], $line[2], $line[3], $sty);
                }
            }
        }

        $this->emitToPage($out);
        $this->attachLink($link, $cellx, $this->posy, $width, $height);

        $this->posy = $cellposy;
        $this->lasth = $height;
        $ln = (int) $ln;
        if ($ln === 1) {
            $this->posy += $height;
            $this->posx = $this->rtlmode ? $this->getPageWidth() - $this->rmargin : $this->lmargin;
        } elseif ($ln === 2) {
            $this->posy += $height;
        } elseif ($this->rtlmode) {
            $this->posx -= $width;
        } else {
            $this->posx += $width;
        }
    }

    /**
     * @throws \Throwable
     */
    public function MultiCell(
        $w,
        $h,
        $txt,
        $border = 0,
        $align = 'J',
        $fill = false,
        $ln = 1,
        $x = null,
        $y = null,
        $reseth = true,
        $stretch = 0,
        $ishtml = false,
        $autopadding = true,
        $maxh = 0,
        $valign = 'T',
        $fitcell = false,
    ) {
        if ($this->docstate !== 2) {
            $this->Error('Unable to print a cell: no page has been started');
            return 0;
        }

        if ($x !== null && (float) $x !== 0.0) {
            $this->setX((float) $x);
        }

        if ($y !== null && (float) $y !== 0.0) {
            $this->setY((float) $y, false);
        }

        if ((bool) $ishtml) {
            $this->writeHTMLCell(
                $w,
                $h,
                $this->posx,
                $this->posy,
                $txt,
                $border,
                $ln,
                $fill,
                $reseth,
                $align,
                $autopadding,
            );
            return 1;
        }

        $eng = $this->engine();
        $width = (float) $w;
        if ($width <= 0) {
            $width = $this->getPageWidth() - $this->rmargin - $this->posx;
        }

        $txt = $this->preserveBlankLines((string) $txt);
        $styles = $this->stylesFromLegacyBorder($border, (bool) $fill);
        $startx = $this->posx;
        $starty = $this->posy;
        $startpid = $this->getPage();

        // Legacy MultiCell delegates to Write() with the current lasth as
        // the row height, after resetting it on $reseth and zeroing the
        // vertical cell padding (the padding wraps the whole cell, not the
        // single rows), so the row pitch excludes the cell padding.
        if ((bool) $reseth || $this->lasth <= 0) {
            $this->lasth = $this->getCellHeight($this->getFontSize(), false);
        }

        $pitch = max($this->lasth, $this->getCellHeight($this->getFontSize(), false));
        $row = $this->legacyRowPitch($pitch, true);

        // The legacy height parameter is a minimum: the cell grows to fit
        // its text. Measure the natural height first (no output emitted).
        // With fitcell the height is a fixed limit instead: the engine
        // shrinks the font to make the text fit ('F' fit mode).
        $height = (float) $h;
        if ($txt !== '' && $height > 0 && !(bool) $fitcell) {
            $eng->getTextCell(
                $txt,
                $this->posx,
                $this->posy,
                $width,
                0,
                0,
                $row['linespace'],
                $this->valignToEngine($valign),
                $this->halignToEngine($align),
                $row['cell'],
                $styles,
                0,
                0,
                0,
                0,
                false,
                true,
                false,
                false,
                false,
                false,
                false,
                false,
            );
            // The engine cell ends at the text bottom: add the trailing
            // remainder of the last row so the grown height covers whole
            // legacy rows.
            $natural = $eng->getLastCellBBox()['h'] + ($row['linespace'] / 2.0);
            $height = max($height, $natural);
            if ((float) $maxh > 0) {
                $height = min($height, (float) $maxh);
            }
        }

        $this->emitToPage($eng->color->getPdfFillColor($this->textcolorspec));
        $pagecapacity = $this->getPageHeight() - $this->tmargin - $this->bmargin;
        $facadebox = false;
        $contentstart = 0;
        $bboxtally = ['count' => 0, 'bottom' => -INF];
        // With no-write page regions the text must hug the obstacle band by
        // band, which only the region-advancing flowing path does; the
        // single-placement getTextCell call below would wrap every line at the
        // starting band's width instead of following the shape.
        $hasnowrite = $this->currentPageNoWriteAreas() !== [];
        if ($txt !== '' && $height > 0 && !$hasnowrite && ($height <= $pagecapacity || !$this->autopagebreak)) {
            // The whole cell fits on one page: render it via the
            // non-flowing engine call (the flowing variant mis-splits
            // cells near the page bottom and re-applies the explicit
            // height to every continuation block). The facade handles
            // the page break itself.
            $this->breakIfNeeded($height);
            $this->emitToPage($eng->getTextCell(
                $txt,
                $this->posx,
                $this->posy,
                $width,
                $height,
                0,
                $row['linespace'],
                $this->valignToEngine($valign),
                $this->halignToEngine($align),
                $row['cell'],
                $styles,
                $this->textrendermode['stroke'],
                0,
                0,
                0,
                // Justify the last line too (legacy getCellCode behavior).
                false,
                $this->textrendermode['fill'],
                $this->textrendermode['stroke'] > 0,
                $this->fontdecor['U'],
                $this->fontdecor['D'],
                $this->fontdecor['O'],
                $this->textrendermode['clip'],
                true,
                $this->forcedTextDir(),
                $this->engineShadow(),
                (bool) $fitcell ? 'F' : '',
            ));
        } elseif ($txt !== '') {
            // Legacy writes the cell text line by line and breaks to the next
            // page before the first line when it would not fit at the current
            // Y; the whole cell then starts on the new page and the rest
            // flows. The engine on its own keeps drawing past the bottom
            // margin (it does not break a block that starts below the region
            // bottom), so trigger the first-line break here.
            $firstrow = $pitch + $this->cellpadding['T'];
            if (
                $this->canBreakPage()
                && ($this->posy + $firstrow - ($this->getPageHeight() - $this->bmargin)) > 0.0001
                && $this->AcceptPageBreak()
            ) {
                if (!$this->advanceToNextColumn()) {
                    $this->advanceToNextPage();
                }

                $startx = $this->posx;
                $starty = $this->posy;
                $startpid = $this->getPage();
            }

            // Legacy no-write page regions: build the engine banded writable
            // regions for the current page so the text hugs the obstacles band
            // by band (each band uses its own writable width) before continuing
            // full width on the next page.
            if ($this->nowriteareas !== [] && $this->applyNoWriteRegionsForFlow($pitch, $this->posy)) {
                $width = 0.0;
                $region = $eng->page->getRegion();
                $this->posx = $region['RX'];
                $startx = $this->posx;
                // Snap the cursor down to the band top when it lands inside a
                // one-line obstacle band: a fractional offset into such a band
                // (the cursor rarely lands on a boundary) would leave less than
                // one line of room and the engine would render nothing there.
                // The shift is capped at one band so the cursor is NOT pulled up
                // when it sits in a *tall merged* region: the engine collapses
                // the full-width slices above/below an obstacle into a single
                // tall region, and there the flow must start at the cursor, not
                // at the region top (otherwise the MultiCell jumps to the page
                // start and overlaps the preceding content).
                if ($this->posy > $region['RY'] && ($this->posy - $region['RY']) < $pitch) {
                    $this->posy = $region['RY'];
                    $starty = $this->posy;
                }
            }

            // Taller than a page (or auto-height): let the engine flow and
            // split the text across pages, but draw the border/fill box in
            // the facade (drawcell=false below). The engine sizes the box of
            // every split block from the *full* remaining text height, so on
            // each page but the last the frame overflows past the bottom
            // margin to the page edge. The facade instead clamps each page's
            // frame segment to its region, matching legacy open-cell
            // MultiCell (see drawHtmlCellSegments).
            $facadebox = true;
            $contentstart = $this->pageContentCount($startpid - 1);
            $bboxwindow = $this->facadeEngine()->openCellBBoxWindow();
            try {
                $eng->addTextCellXY(
                    $txt,
                    -1,
                    $this->posx,
                    $this->posy,
                    $width,
                    0,
                    0,
                    $row['linespace'],
                    $this->valignToEngine($valign),
                    $this->halignToEngine($align),
                    $row['cell'],
                    $styles,
                    $this->textrendermode['stroke'],
                    0,
                    0,
                    0,
                    // Justify the last line too (legacy getCellCode behavior).
                    false,
                    $this->textrendermode['fill'],
                    $this->textrendermode['stroke'] > 0,
                    $this->fontdecor['U'],
                    $this->fontdecor['D'],
                    $this->fontdecor['O'],
                    $this->textrendermode['clip'],
                    // The facade draws the box per page (see $facadebox below).
                    false,
                    $this->forcedTextDir(),
                    $this->engineShadow(),
                    (bool) $fitcell ? 'F' : '',
                );
            } finally {
                $bboxtally = $this->facadeEngine()->closeCellBBoxWindow($bboxwindow);
            }
        }

        $bbox = $eng->getLastCellBBox();
        $cellh = $txt === '' ? $height : $bbox['h'];
        $cellbottom = $txt === '' ? $this->posy + $this->cellmargin['T'] + $cellh : $bbox['y'] + $cellh;

        if ($facadebox) {
            // Real end page from the number of rendered blocks (one cell
            // bounding box per block), not getPage(): the engine advances
            // the page pointer past a cell that ends exactly at a region
            // boundary, which would otherwise paint a phantom full-page
            // frame on a page the cell never reached.
            $blocks = max(1, $bboxtally['count']);
            $endpage = min($startpid + $blocks - 1, $this->getPage());
            // Draw the border/fill box the engine omitted, as per-page frame
            // segments clamped to each page region (a no-op when neither
            // border nor fill is requested).
            $this->drawHtmlCellSegments(
                $startx,
                $width,
                $starty,
                $cellbottom,
                $startpid,
                $contentstart,
                $styles,
                (bool) $fill,
                $endpage,
            );
        }

        // Estimate the number of rendered text lines from the cell height.
        $numlines = $pitch > 0
            ? max(
                1,
                (int) round(
                    ($bbox['h'] - $this->cellpadding['T'] - $this->cellpadding['B'] + ($row['linespace'] / 2.0))
                    / $pitch,
                ),
            )
            : 1;

        $this->lasth = $pitch;
        $ln = (int) $ln;
        if ($ln === 0) {
            // Legacy semantics: the cursor returns to the page and vertical
            // position where the cell started, with X advanced past it
            // (cell margins included).
            if ($this->getPage() > $startpid) {
                $this->setPage($startpid);
            }

            $this->posy = $starty;
            $this->posx = $startx + $width + $this->cellmargin['L'] + $this->cellmargin['R'];
        } else {
            $this->posy = $cellbottom + $this->cellmargin['B'];
            if ($ln === 2) {
                // Below the cell, with X past its right edge.
                $this->posx = $startx + $width + $this->cellmargin['L'] + $this->cellmargin['R'];
            } else {
                $this->posx = $this->rtlmode ? $this->getPageWidth() - $this->rmargin : $this->lmargin;
            }
        }

        return $numlines;
    }

    /**
     * Estimate the number of lines needed to print the given text.
     *
     * @return int Estimated number of lines.
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function getNumLines(
        $txt,
        $w = 0,
        $reseth = false,
        $autopadding = true,
        $cellpadding = null,
        $border = 0,
    ) {
        $width = (float) $w;
        if ($width <= 0) {
            $width = $this->getPageWidth() - $this->rmargin - $this->posx;
        }

        $textwidth = $width - $this->cellpadding['L'] - $this->cellpadding['R'];
        if ($textwidth <= 0) {
            return 1;
        }

        $lines = 0;
        foreach (explode("\n", (string) $txt) as $block) {
            $blockwidth = (float) $this->GetStringWidth($block);
            $lines += max(1, (int) ceil($blockwidth / $textwidth));
        }

        return max(1, $lines);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function getStringHeight(
        $w,
        $txt,
        $reseth = false,
        $autopadding = true,
        $cellpadding = null,
        $border = 0,
    ) {
        $lines = (int) $this->getNumLines($txt, $w, $reseth, $autopadding, $cellpadding, $border);
        $height = $lines * $this->getCellHeight($this->getFontSize(), false);
        if ((bool) $autopadding) {
            $height += $this->cellpadding['T'] + $this->cellpadding['B'];
        }

        return $height;
    }

    /**
     * Fill the empty lines of a multi-line text with a single space.
     *
     * The engine drops empty lines from the rendered output: a line with no
     * characters produces no text operator and no bounding-box advance, so
     * consecutive line breaks collapse into one. The legacy API preserves
     * each empty line as a full line feed, so give every empty line a single
     * (invisible) space character to force the engine to render and advance
     * past it. Line counting is unaffected: the engine already counts empty
     * lines when measuring the cell, only their rendering position is lost.
     */
    protected function preserveBlankLines(string $txt): string
    {
        if (!str_contains($txt, "\n")) {
            return $txt;
        }

        if (str_starts_with($txt, "\n")) {
            $txt = ' ' . $txt;
        }

        return preg_replace('/\n(?=\n)/', "\n ", $txt) ?? $txt;
    }

    /**
     * Map the legacy text row pitch onto the engine text-cell inputs.
     *
     * Legacy rendering emits every text line as a cell at least
     * getCellHeight() tall with the text vertically centered in it, while
     * the engine stacks the lines at the bare font metric height below the
     * cell top. The extra leading between the rows becomes the engine
     * inter-line space and half of it (the centering of the first line
     * inside its row) is added to the cell top padding.
     *
     * @param float $pitch       Row pitch in user units (the caller resolves
     *                           the legacy minimum cell height).
     * @param bool  $withpadding When true the cell carries the legacy cell
     *                           padding and margins (MultiCell); when false
     *                           the rows span the whole cell like legacy
     *                           Write().
     *
     * @return array{
     *     linespace: float,
     *     cell: array{
     *         margin: array{T: float, R: float, B: float, L: float},
     *         padding: array{T: float, R: float, B: float, L: float},
     *         borderpos: float,
     *     },
     * } Engine inter-line space in user units and cell definition (in
     *   internal points).
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function legacyRowPitch(float $pitch, bool $withpadding = false): array
    {
        $metric = $this->currentFontMetric();
        $lineh = (float) ($metric['height'] ?? ($this->fontsizept * $this->cellheightratio)) / $this->kratio;
        $linespace = $pitch - $lineh;

        $cell = \Com\Tecnick\Pdf\Tcpdf::ZEROCELL;
        $cell['padding']['T'] = $this->kratio * ($linespace / 2.0);
        if ($withpadding) {
            $cell['padding']['T'] += $this->kratio * $this->cellpadding['T'];
            $cell['padding']['R'] = $this->kratio * $this->cellpadding['R'];
            $cell['padding']['B'] = $this->kratio * $this->cellpadding['B'];
            $cell['padding']['L'] = $this->kratio * $this->cellpadding['L'];
            $cell['margin']['T'] = $this->kratio * $this->cellmargin['T'];
            $cell['margin']['R'] = $this->kratio * $this->cellmargin['R'];
            $cell['margin']['B'] = $this->kratio * $this->cellmargin['B'];
            $cell['margin']['L'] = $this->kratio * $this->cellmargin['L'];
        }

        return [
            'linespace' => $linespace,
            'cell' => $cell,
        ];
    }

    /**
     * @throws \Exception
     */
    public function Write(
        $h,
        $txt,
        $link = '',
        $fill = false,
        $align = '',
        $ln = false,
        $stretch = 0,
        $firstline = false,
        $firstblock = false,
        $maxh = 0,
        $wadj = 0,
        $margin = null,
    ) {
        if ($this->docstate !== 2) {
            $this->Error('Unable to write text: no page has been started');
            return 0;
        }

        $eng = $this->engine();
        $txt = $this->preserveBlankLines((string) $txt);
        if ($txt === '') {
            return 0;
        }

        $pitch = max((float) $h, $this->getCellHeight($this->getFontSize()));
        // Break before measuring the flow origin: the region and the cursor
        // both belong to the page the text starts on.
        $this->breakIfNeeded($pitch);

        // In column mode the text flows inside the current column region
        // (and through the following columns/pages inside the engine call).
        $incolumns = $this->inColumnMode();
        $region = $eng->page->getRegion();
        $flowx = $incolumns ? $region['RX'] : $this->lmargin;
        $width = $incolumns ? $region['RW'] : $this->getPageWidth() - $this->rmargin - $this->lmargin;
        // Legacy wraps the lines inside the horizontal cell padding and
        // starts them at the padded edge.
        $originx = $flowx + $this->cellpadding['L'];
        $width = max(0.0, $width - $this->cellpadding['L'] - $this->cellpadding['R']);
        // The cursor carries the padding too, so it is measured from the
        // unpadded flow origin.
        $offset = max(0.0, $this->posx - $flowx);

        $styles = ['all' => ['lineWidth' => 0.0]];
        if ((bool) $fill) {
            $styles['all']['fillColor'] = $this->fillcolorspec;
        }

        $row = $this->legacyRowPitch($pitch);

        $this->emitToPage($eng->color->getPdfFillColor($this->textcolorspec));
        if ($this->canBreakPage()) {
            $eng->addTextCellXY(
                $txt,
                -1,
                $originx,
                $this->posy,
                $width,
                0,
                $offset,
                $row['linespace'],
                'T',
                $this->halignToEngine($align),
                $row['cell'],
                $styles,
                $this->textrendermode['stroke'],
                0,
                0,
                0,
                // Justify the last line too (legacy getCellCode behavior; lines
                // ending with an explicit line break are still excluded).
                false,
                $this->textrendermode['fill'],
                $this->textrendermode['stroke'] > 0,
                $this->fontdecor['U'],
                $this->fontdecor['D'],
                $this->fontdecor['O'],
                $this->textrendermode['clip'],
                (bool) $fill,
                $this->forcedTextDir(),
                $this->engineShadow(),
            );
        } else {
            // Without a page to flow into, the flowing call renders only the
            // lines that fit in the region and drops the rest. Legacy keeps
            // writing line by line past the bottom margin, so place the whole
            // text at the cursor instead.
            $this->emitToPage($eng->getTextCell(
                $txt,
                $originx,
                $this->posy,
                $width,
                0,
                $offset,
                $row['linespace'],
                'T',
                $this->halignToEngine($align),
                $row['cell'],
                $styles,
                $this->textrendermode['stroke'],
                0,
                0,
                0,
                false,
                $this->textrendermode['fill'],
                $this->textrendermode['stroke'] > 0,
                $this->fontdecor['U'],
                $this->fontdecor['D'],
                $this->fontdecor['O'],
                $this->textrendermode['clip'],
                (bool) $fill,
                $this->forcedTextDir(),
                $this->engineShadow(),
            ));
        }

        $textbbox = $eng->getLastBBox();
        $cellbbox = $eng->getLastCellBBox();
        // The cell bbox covers the last rendered block: the top padding plus
        // the text lines without the trailing remainder of the last row, so
        // the block spans rows * pitch - linespace / 2.
        $numlines = $pitch > 0 ? max(1, (int) round(($cellbbox['h'] + ($row['linespace'] / 2.0)) / $pitch)) : 1;
        $this->lasth = $pitch;

        if ((bool) $ln || str_ends_with($txt, "\n")) {
            // Explicit line feed, or the text itself ends with a newline:
            // the cursor moves to the start of the next line (in column
            // mode: of the column the flow ended in).
            $this->posy = $cellbbox['y'] + ($numlines * $pitch);
            if ($incolumns) {
                $region = $eng->page->getRegion();
                $this->posx = $this->rtlmode ? $region['RX'] + $region['RW'] : $region['RX'];
            } else {
                $this->posx = $this->rtlmode ? $this->getPageWidth() - $this->rmargin : $this->lmargin;
            }
        } else {
            // Cursor stays on the last written line: the top of the last
            // row (single line: the cell top).
            $this->posy = $cellbbox['y'] + (($numlines - 1) * $pitch);
            $this->posx = (float) ($textbbox['x'] ?? 0) + (float) ($textbbox['w'] ?? 0);
        }

        if (is_string($link) && $link !== '') {
            $this->attachLink($link, $cellbbox['x'], $cellbbox['y'], $cellbbox['w'], $cellbbox['h']);
        }

        return $numlines;
    }

    /**
     * Return the current font metric array (inserting the default when missing).
     *
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function currentFontMetric(): array
    {
        if ($this->fontmetric === []) {
            $this->setFont($this->fontfamily, $this->fontstyle, $this->fontsizept, '', 'default', false);
        }

        return $this->fontmetric;
    }

    // ===================================================================
    // Images.
    // ===================================================================

    /**
     * @throws \Exception
     */
    public function Image(
        $file,
        $x = null,
        $y = null,
        $w = 0,
        $h = 0,
        $type = '',
        $link = '',
        $align = '',
        $resize = false,
        $dpi = 300,
        $palign = '',
        $ismask = false,
        $imgmask = false,
        $border = 0,
        $fitbox = false,
        $hidden = false,
        $fitonpage = false,
        $alt = false,
        $altimgs = [],
    ) {
        if ($this->docstate !== 2) {
            $this->Error('Unable to add an image: no page has been started');
            return;
        }

        $eng = $this->engine();
        $file = $this->resolveLocalFile((string) $file);

        // Legacy image masks: Image($ismask=true) registers a soft-mask source
        // and returns a handle without drawing anything. A later Image() given
        // that handle as $imgmask gets the mask merged into its alpha channel
        // and is rendered through the engine's embedded-alpha path.
        if ((bool) $ismask) {
            $this->imagemasks[++$this->imagemaskseq] = $file;
            return $this->imagemaskseq;
        }

        $maskhandle = is_numeric($imgmask) ? (int) $imgmask : 0;
        $maskedpng = $maskhandle > 0 && isset($this->imagemasks[$maskhandle])
            ? $this->mergeImageAlphaMask($file, $this->imagemasks[$maskhandle])
            : null;

        $autoposy = $y === null || (string) $y === '';
        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $autoposy ? $this->posy : (float) $y;
        $width = (float) $w;
        $height = (float) $h;

        if ($width <= 0 || $height <= 0) {
            [$pixw, $pixh] = $this->imagePixelSize($file);
            if ($pixw > 0 && $pixh > 0) {
                if ($width <= 0 && $height <= 0) {
                    $width = $pixw / ($this->imgscale * $this->kratio);
                    $height = $pixh / ($this->imgscale * $this->kratio);
                } elseif ($width <= 0) {
                    $width = ($height * $pixw) / $pixh;
                } else {
                    $height = ($width * $pixh) / $pixw;
                }
            }
        }

        if ($width <= 0 || $height <= 0) {
            $this->Error('Unable to determine the size of the image: ' . $file);
            return;
        }

        if ((bool) $fitbox && (float) $w > 0 && (float) $h > 0) {
            // Scale the image to fit the given box, preserving the original
            // pixel proportions; the fitbox letters select the alignment
            // inside the box.
            [$pixw, $pixh] = $this->imagePixelSize($file);
            if ($pixw > 0 && $pixh > 0) {
                $fit = is_string($fitbox) ? strtoupper($fitbox) : '';
                $boxw = (float) $w;
                $boxh = (float) $h;
                $ratio = min($boxw / $pixw, $boxh / $pixh);
                $neww = $pixw * $ratio;
                $newh = $pixh * $ratio;
                $posx += match (true) {
                    str_contains($fit, 'C') => ($boxw - $neww) / 2,
                    str_contains($fit, 'R') => $boxw - $neww,
                    default => 0.0,
                };
                $posy += match (true) {
                    str_contains($fit, 'M') => ($boxh - $newh) / 2,
                    str_contains($fit, 'B') => $boxh - $newh,
                    default => 0.0,
                };
                $width = $neww;
                $height = $newh;
            }
        }

        // Horizontal alignment relative to the page content area.
        $palign = strtoupper((string) $palign);
        if ($palign === 'C') {
            $posx = ($this->getPageWidth() - $width) / 2;
        } elseif ($palign === 'R') {
            $posx = $this->getPageWidth() - $this->rmargin - $width;
        } elseif ($palign === 'L') {
            $posx = $this->lmargin;
        }

        // An implicit ordinate follows the cursor across an automatic break,
        // keeping the fitbox vertical alignment shift relative to it.
        $posyshift = $autoposy ? $posy - $this->posy : 0.0;
        $this->breakIfNeeded($autoposy ? $posyshift + $height : 0.0);
        if ($autoposy) {
            $posy = $this->posy + $posyshift;
        }

        if ((bool) $hidden) {
            return;
        }

        $iid = $eng->image->add($maskedpng === null ? $file : '@' . $maskedpng, null, null, false, $this->jpegquality);
        if ($this->xobjtid !== '') {
            $eng->addXObjectImageID($this->xobjtid, $iid);
            $flipheight = $this->xobjheight;
        } else {
            $page = $eng->page->getPage();
            $flipheight = $page['height'];
        }

        $this->emitToPage($eng->image->getSetImage($iid, $posx, $posy, $width, $height, $flipheight));

        if ($border !== 0 && $border !== false && $border !== '') {
            $styles = $this->stylesFromLegacyBorder($border, false);
            if ($styles !== []) {
                $this->emitToPage($eng->graph->getRect($posx, $posy, $width, $height, 'S', $styles));
            }
        }

        $this->attachLink($link, $posx, $posy, $width, $height);

        $this->imagerbx = $posx + $width;
        $this->imagerby = $posy + $height;

        switch (strtoupper((string) $align)) {
            case 'T':
                $this->posy = $posy;
                $this->posx = $this->imagerbx;
                break;
            case 'M':
                $this->posy = $posy + round($height / 2);
                $this->posx = $this->imagerbx;
                break;
            case 'B':
                $this->posy = $this->imagerby;
                $this->posx = $this->imagerbx;
                break;
            case 'N':
                $this->setY($this->imagerby);
                break;
            default:
                break;
        }
    }

    /**
     * Apply a legacy 8-bit soft mask to an image by writing it into the image's
     * alpha channel, then return the merged PNG content. The engine's import
     * path then splits that alpha back out into a /SMask (the only soft-mask
     * mechanism tc-lib-pdf-image exposes), reproducing legacy's separate-mask
     * Image() call. Both files are read through the engine file helper, so the
     * path allowlist applies. Returns null when the images cannot be read
     * (caller falls back to the unmasked file).
     *
     * Mask polarity follows the PDF soft-mask convention used by legacy: a
     * white mask pixel is fully opaque, a black one fully transparent.
     *
     * @throws \RuntimeException
     */
    protected function mergeImageAlphaMask(string $basefile, string $maskfile): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $filehelper = $this->engine()->file;
        try {
            $basedata = $filehelper->getLocalFileData($basefile);
            $maskdata = $filehelper->getLocalFileData($maskfile);
        } catch (\Throwable) {
            return null;
        }

        if (!is_string($basedata) || $basedata === '' || !is_string($maskdata) || $maskdata === '') {
            return null;
        }

        // Validate the bytes before decoding so imagecreatefromstring() does
        // not raise a warning on malformed data.
        if (getimagesizefromstring($basedata) === false || getimagesizefromstring($maskdata) === false) {
            return null;
        }

        $base = imagecreatefromstring($basedata);
        $mask = imagecreatefromstring($maskdata);
        if ($base === false || $mask === false) {
            return null;
        }

        imagepalettetotruecolor($base);
        imagepalettetotruecolor($mask);
        $width = imagesx($base);
        $height = imagesy($base);

        // Scale the mask to the base image size (legacy ignores the mask's own
        // width/height and stretches it onto the target image).
        if (imagesx($mask) !== $width || imagesy($mask) !== $height) {
            $scaled = imagecreatetruecolor($width, $height);
            imagecopyresampled($scaled, $mask, 0, 0, 0, 0, $width, $height, imagesx($mask), imagesy($mask));
            $mask = $scaled;
        }

        $out = imagecreatetruecolor($width, $height);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        for ($y = 0; $y < $height; ++$y) {
            for ($x = 0; $x < $width; ++$x) {
                $rgb = imagecolorat($base, $x, $y) & 0xFF_FF_FF;
                // Gray value of the mask (R=G=B); white => opaque, black => transparent.
                $gray = imagecolorat($mask, $x, $y) & 0xFF;
                // GD alpha is inverted (0 = opaque, 127 = transparent).
                $alpha = 127 - (int) round(($gray / 255) * 127);
                imagesetpixel($out, $x, $y, ($alpha << 24) | $rgb);
            }
        }

        ob_start();
        imagepng($out);
        $png = (string) ob_get_clean();

        return $png === '' ? null : $png;
    }

    /**
     * Return the pixel size of an image file or '@'-prefixed data stream.
     * Files outside the path allowlist report an unknown size.
     *
     * @return array{0: int, 1: int} Width and height in pixels (0 when unknown).
     * @throws \RuntimeException
     */
    protected function imagePixelSize(string $file): array
    {
        if ($file !== '' && $file[0] === '@') {
            $info = getimagesizefromstring(substr($file, 1));
        } else {
            $info = $this->isAllowedLocalFile($file) ? getimagesize($file) : false;
        }

        return is_array($info) ? [(int) $info[0], (int) $info[1]] : [0, 0];
    }

    // ===================================================================
    // Cursor positioning.
    // ===================================================================

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Ln($h = null, $cell = false)
    {
        $height = $h === null || (string) $h === '' ? $this->lasth : (float) $h;
        $cellmargin = (bool) $cell ? $this->cellmargin['L'] : 0.0;
        $this->posy += $height;
        $this->posx = $this->rtlmode
            ? $this->getPageWidth() - $this->rmargin - $cellmargin
            : $this->lmargin + $cellmargin;
    }

    /**
     * @return float Relative X coordinate of the cursor in user units.
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function GetX()
    {
        return $this->rtlmode ? $this->getPageWidth() - $this->posx : $this->posx;
    }

    /**
     * @return float Absolute X coordinate of the cursor in user units.
     */
    public function GetAbsX()
    {
        return $this->posx;
    }

    /**
     * @return float Y coordinate of the cursor in user units.
     */
    public function GetY()
    {
        return $this->posy;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setX($x, $rtloff = false)
    {
        $posx = (float) $x;
        if (!(bool) $rtloff && $this->rtlmode) {
            $this->posx = $posx >= 0 ? $this->getPageWidth() - $posx : -$posx;
            return;
        }

        $this->posx = $posx >= 0 ? $posx : $this->getPageWidth() + $posx;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setY($y, $resetx = true, $rtloff = false)
    {
        $posy = (float) $y;
        $this->posy = $posy >= 0 ? $posy : $this->getPageHeight() + $posy;
        if ((bool) $resetx) {
            $this->posx = !(bool) $rtloff && $this->rtlmode ? $this->getPageWidth() - $this->rmargin : $this->lmargin;
        }
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setXY($x, $y, $rtloff = false)
    {
        $this->setY($y, false, $rtloff);
        $this->setX($x, $rtloff);
    }

    public function setAbsX($x)
    {
        $this->posx = (float) $x;
    }

    public function setAbsY($y)
    {
        $this->posy = (float) $y;
    }

    public function setAbsXY($x, $y)
    {
        $this->posx = (float) $x;
        $this->posy = (float) $y;
    }

    // ===================================================================
    // Output.
    // ===================================================================

    /**
     * Send the document to the given destination: string, local file or browser.
     *
     * For 'F', 'FI' and 'FD' the name is a full or relative file path, unlike
     * \Com\Tecnick\Pdf\Output::savePDF(), which takes a directory. The file name
     * is sanitized by the engine, so the file written may differ from the name
     * given: getPDFFilename() returns the name actually used. The output
     * directory must be inside an allowed path (K_ALLOWED_PATHS).
     *
     * @param mixed $name File name.
     * @param mixed $dest Destination: I, D, F, S, FI, FD or E. A boolean is
     *                     accepted for legacy callers: true is D, false is F.
     *
     * @return string
     * @throws \Throwable
     */
    public function Output($name = 'doc.pdf', $dest = 'I')
    {
        $this->Close();
        $name = (string) $name === '' ? 'doc.pdf' : (string) $name;
        if (is_bool($dest)) {
            $dest = $dest ? 'D' : 'F';
        }

        $dest = strtoupper((string) $dest);
        if ($dest === '') {
            $dest = 'I';
        }

        $eng = $this->engine();
        // The engine sanitizes the name, so the value in use may differ: the
        // 'F' destinations read it back through savePDF().
        $eng->setPDFFilename(basename($name));
        switch ($dest) {
            case 'I':
                $eng->renderPDF($this->pdfraw);
                return '';
            case 'D':
                if (PHP_SAPI === 'cli') {
                    $eng->renderPDF($this->pdfraw);
                    return '';
                }

                $eng->downloadPDF($this->pdfraw);
                return '';
            case 'F':
            case 'FI':
            case 'FD':
                // savePDF() takes the directory and appends the engine file
                // name, so the caller's path is split rather than forwarded.
                try {
                    $eng->savePDF(dirname($name), $this->pdfraw);
                } catch (\Throwable $e) {
                    $this->Error(
                        'Unable to create output file: '
                        . $name
                        . ' - '
                        . $e->getMessage()
                        . ' - the output directory must exist and be inside an allowed path;'
                        . ' add it to K_ALLOWED_PATHS before loading tcpdf.php',
                    );
                    return '';
                }

                if ($dest === 'FI') {
                    $eng->renderPDF($this->pdfraw);
                } elseif ($dest === 'FD') {
                    if (PHP_SAPI === 'cli') {
                        $eng->renderPDF($this->pdfraw);
                    } else {
                        $eng->downloadPDF($this->pdfraw);
                    }
                }

                return '';
            case 'E':
                return $eng->getMIMEAttachmentPDF($this->pdfraw);
            case 'S':
                return $this->pdfraw;
            default:
                $this->Error('Incorrect output destination: ' . $dest);
                return '';
        }
    }

    /**
     * @throws \Throwable
     */
    public function getPDFData()
    {
        $this->Close();
        return $this->pdfraw;
    }

    /**
     * Return the PDF file name in use, as sanitized by the engine.
     *
     * Not part of the legacy Tcpdf API. After Output() with the 'F', 'FI' or
     * 'FD' destination this is the base name of the file that was written,
     * which may differ from the name passed in.
     *
     * @return string
     * @throws \RuntimeException
     */
    public function getPDFFilename()
    {
        return $this->engine()->getPDFFilename();
    }

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function setExtraXMP($xmp)
    {
        $this->engine()->setCustomXMP('tcpdf_extra', (string) $xmp);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function setExtraXMPRDF($xmp)
    {
        $this->engine()->setCustomXMP('tcpdf_extra_rdf', (string) $xmp);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function setExtraXMPPdfaextension($xmp)
    {
        $this->engine()->setCustomXMP('tcpdf_extra_pdfaextension', (string) $xmp);
    }

    public function setDocCreationTimestamp($time)
    {
        $this->doctimestamps['create'] = is_numeric($time) ? (int) $time : (int) strtotime((string) $time);
    }

    public function setDocModificationTimestamp($time)
    {
        $this->doctimestamps['modify'] = is_numeric($time) ? (int) $time : (int) strtotime((string) $time);
    }

    public function getDocCreationTimestamp()
    {
        return $this->doctimestamps['create'];
    }

    public function getDocModificationTimestamp()
    {
        return $this->doctimestamps['modify'];
    }

    public function setHeaderFont($font)
    {
        $font = is_array($font) ? array_values($font) : [];
        $this->headerfont = [
            (string) ($font[0] ?? $this->fontfamily),
            (string) ($font[1] ?? ''),
            (float) ($font[2] ?? $this->fontsizept),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: float} Header font: family, style, size in points.
     */
    public function getHeaderFont()
    {
        return $this->headerfont;
    }

    public function setFooterFont($font)
    {
        $font = is_array($font) ? array_values($font) : [];
        $this->footerfont = [
            (string) ($font[0] ?? $this->fontfamily),
            (string) ($font[1] ?? ''),
            (float) ($font[2] ?? $this->fontsizept),
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: float} Footer font: family, style, size in points.
     */
    public function getFooterFont()
    {
        return $this->footerfont;
    }

    /**
     * @throws \RuntimeException
     */
    public function setLanguageArray($language)
    {
        $this->langdata = is_array($language) ? $language : [];
        $lang = [];
        foreach ($this->langdata as $key => $val) {
            if (!(is_string($key) && is_string($val))) {
                continue;
            }

            $lang[$key] = $val;
        }

        if ($lang !== []) {
            $this->engine()->setLanguageArray($lang);
        }

        // Legacy semantics: a_meta_dir == 'rtl' switches the whole document
        // to right-to-left (the engine and all cursor positioning follow).
        $dir = $this->langdata['a_meta_dir'] ?? '';
        $this->rtlmode = is_string($dir) && strtolower($dir) === 'rtl';
        $this->engine()->setRTL($this->rtlmode);
        $this->tmprtl = false;
    }

    /**
     * @throws \Exception
     */
    public function addHtmlLink(
        $url,
        $name,
        $fill = false,
        $firstline = false,
        $color = null,
        $style = -1,
        $firstblock = false,
    ) {
        $prevcolor = $this->textcolorspec;
        $prevstyle = $this->getFontStyle();
        if (is_array($color) && $color !== []) {
            $this->textcolorspec = $this->colorSpecFromLegacy($color);
        } else {
            $this->textcolorspec = $this->colorSpecFromLegacy($this->htmllinkcolor);
        }

        $style = is_string($style) && $style !== '' && $style !== '-1' ? $style : $this->htmllinkstyle;
        $this->setFont($this->fontfamily, $style, $this->fontsizept);
        $ret = $this->Write(
            $this->lasth,
            (string) $name,
            (string) $url,
            $fill,
            '',
            false,
            0,
            $firstline,
            $firstblock,
            0,
        );
        $this->textcolorspec = $prevcolor;
        $this->setFont($this->fontfamily, $prevstyle, $this->fontsizept);
        return $ret;
    }

    public function pixelsToUnits($px)
    {
        return (float) $px / ($this->imgscale * $this->kratio);
    }

    public function unhtmlentities($text_to_convert)
    {
        return html_entity_decode((string) $text_to_convert, ENT_QUOTES, 'UTF-8');
    }

    // ===================================================================
    // Security (Stage 4).
    // ===================================================================

    /**
     * @throws \Exception
     */
    public function setProtection(
        $permissions = [
            'print',
            'modify',
            'copy',
            'annot-forms',
            'fill-forms',
            'extract',
            'assemble',
            'print-high',
        ],
        $user_pass = '',
        $owner_pass = null,
        $mode = 0,
        $pubkeys = null,
    ) {
        if ($this->docstate >= 2) {
            $this->Error('setProtection() must be called before adding pages');
            return;
        }

        $permissions = array_map(
            static fn(mixed $val): string => (string) $val,
            is_array($permissions) ? array_values($permissions) : [],
        );
        $ownerpass = $owner_pass === null ? md5(uniqid('tcpdf', true)) : (string) $owner_pass;
        // The engine deprecates the legacy RC4 modes (0 and 1); the legacy
        // API accepted them silently, so that specific notice is muted here.
        set_error_handler(static fn(int $errno, string $errstr): bool => str_contains(
            $errstr,
            'RC4 encryption',
        ), E_USER_DEPRECATED | E_DEPRECATED);
        /** @var array{0: array{c: string, p: array<array-key, string>}}|null $pubkeys */
        $pubkeys = is_array($pubkeys) && $pubkeys !== [] ? array_values($pubkeys) : null;
        try {
            $encrypt = new \Com\Tecnick\Pdf\Encrypt\Encrypt(
                true,
                md5(uniqid('tcpdf_fid', true)),
                (int) $mode,
                $permissions,
                (string) $user_pass,
                $ownerpass,
                $pubkeys,
            );
        } finally {
            restore_error_handler();
        }

        // Encryption is constructor-bound in the engine: re-initialize it
        // and replay the construction-time settings.
        $family = $this->fontfamily;
        $style = $this->fontstyle;
        $size = $this->fontsizept;
        $this->eng = $this->engineNew($encrypt);
        $this->kratio = $this->eng->toPoints(1.0);
        $this->setFont($family, $style, $size);
    }

    // ===================================================================
    // Transformations.
    // ===================================================================

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function StartTransform()
    {
        $this->emitToPage($this->engine()->graph->getStartTransform());
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function StopTransform()
    {
        $this->emitToPage($this->engine()->graph->getStopTransform());
    }

    /**
     * @throws \Exception
     */
    public function ScaleX($s_x, $x = '', $y = '')
    {
        $this->Scale($s_x, 100, $x, $y);
    }

    /**
     * @throws \Exception
     */
    public function ScaleY($s_y, $x = '', $y = '')
    {
        $this->Scale(100, $s_y, $x, $y);
    }

    /**
     * @throws \Exception
     */
    public function ScaleXY($s, $x = '', $y = '')
    {
        $this->Scale($s, $s, $x, $y);
    }

    /**
     * @throws \Exception
     */
    public function Scale($s_x, $s_y, $x = null, $y = null)
    {
        $scalex = (float) $s_x;
        $scaley = (float) $s_y;
        if ($scalex === 0.0 || $scaley === 0.0) {
            $this->Error('Please do not use values equal to zero for scaling');
            return;
        }

        $this->emitToPage($this->engine()->graph->getScaling(
            $scalex / 100,
            $scaley / 100,
            $this->coordOrCursor($x, false),
            $this->coordOrCursor($y, true),
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function MirrorH($x = null)
    {
        $this->emitToPage($this->engine()->graph->getHorizMirroring($this->coordOrCursor($x, false)));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function MirrorV($y = null)
    {
        $this->emitToPage($this->engine()->graph->getVertMirroring($this->coordOrCursor($y, true)));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function MirrorP($x = null, $y = null)
    {
        $this->emitToPage($this->engine()->graph->getPointMirroring(
            $this->coordOrCursor($x, false),
            $this->coordOrCursor($y, true),
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function MirrorL($angle = 0, $x = null, $y = null)
    {
        $this->emitToPage($this->engine()->graph->getReflection(
            (float) $angle,
            $this->coordOrCursor($x, false),
            $this->coordOrCursor($y, true),
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function TranslateX($t_x)
    {
        $this->Translate($t_x, 0);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function TranslateY($t_y)
    {
        $this->Translate(0, $t_y);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Translate($t_x, $t_y)
    {
        $this->emitToPage($this->engine()->graph->getTranslation((float) $t_x, (float) $t_y));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Rotate($angle, $x = null, $y = null)
    {
        $this->emitToPage($this->engine()->graph->getRotation(
            (float) $angle,
            $this->coordOrCursor($x, false),
            $this->coordOrCursor($y, true),
        ));
    }

    /**
     * @throws \Exception
     */
    public function SkewX($angle_x, $x = null, $y = null)
    {
        $this->Skew($angle_x, 0, $x, $y);
    }

    /**
     * @throws \Exception
     */
    public function SkewY($angle_y, $x = null, $y = null)
    {
        $this->Skew(0, $angle_y, $x, $y);
    }

    /**
     * @throws \Exception
     */
    public function Skew($angle_x, $angle_y, $x = null, $y = null)
    {
        $anglex = (float) $angle_x;
        $angley = (float) $angle_y;
        if ($anglex <= -90 || $anglex >= 90 || $angley <= -90 || $angley >= 90) {
            $this->Error('Please use values between -90 and +90 degrees for Skewing.');
            return;
        }

        $this->emitToPage($this->engine()->graph->getSkewing(
            $anglex,
            $angley,
            $this->coordOrCursor($x, false),
            $this->coordOrCursor($y, true),
        ));
    }

    // ===================================================================
    // Graphics.
    // ===================================================================

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setLineWidth($width)
    {
        $this->linewidth = (float) $width;
        $this->emitToPage(sprintf('%F w' . "\n", $this->linewidth * $this->kratio));
    }

    /**
     * @return float Current line width in user units.
     */
    public function GetLineWidth()
    {
        return $this->linewidth;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setLineStyle($style, $ret = false)
    {
        $style = is_array($style) ? $style : [];
        $engstyle = $this->styleFromLegacyLineStyle($style);
        if (isset($style['width']) && is_numeric($style['width'])) {
            $this->linewidth = (float) $style['width'];
        }

        if (isset($engstyle['lineColor'])) {
            $this->drawcolorspec = $engstyle['lineColor'];
        }

        $this->linestyle = $engstyle;
        $cmd = $this->engine()->graph->getStyleCmd($engstyle);
        if (!(bool) $ret) {
            $this->emitToPage($cmd);
        }

        return $cmd;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Line($x1, $y1, $x2, $y2, $style = [])
    {
        if ($this->docstate !== 2) {
            return;
        }

        if (is_array($style) && $style !== []) {
            $this->setLineStyle($style, true);
        }

        $this->emitToPage($this->engine()->graph->getLine(
            (float) $x1,
            (float) $y1,
            (float) $x2,
            (float) $y2,
            $this->currentLineStyle(),
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Rect($x, $y, $w, $h, $style = '', $border_style = [], $fill_color = [])
    {
        if ($this->docstate !== 2) {
            return;
        }

        $eng = $this->engine();
        $mode = $this->shapeModeFromLegacy($style, false);

        if (is_array($fill_color) && $fill_color !== []) {
            $this->fillcolorspec = $this->colorSpecFromLegacy($fill_color);
        }

        $border = is_array($border_style) ? $border_style : [];
        $sidekeys = array_intersect(array_keys($border), ['L', 'T', 'R', 'B']);
        if ($sidekeys !== []) {
            // Per-side border styles: fill first (stroke removed), then stroke
            // each requested side as an independent line, exactly like the
            // legacy library. Routing each side through Line()/setLineStyle()
            // makes the line state persist between sides, so a side that omits
            // a style key (e.g. 'color') inherits it from the previous side.
            if (str_contains($mode, 'f') || str_contains($mode, 'B') || str_contains($mode, 'b')) {
                $this->emitToPage($eng->graph->getBasicRect((float) $x, (float) $y, (float) $w, (float) $h, 'f', [
                    'lineWidth' => 0.0,
                    'fillColor' => $this->fillcolorspec,
                ]));
            }

            $posx = (float) $x;
            $posy = (float) $y;
            $width = (float) $w;
            $height = (float) $h;
            if (isset($border['L']) && $border['L']) {
                $this->Line($posx, $posy, $posx, $posy + $height, is_array($border['L']) ? $border['L'] : []);
            }

            if (isset($border['T']) && $border['T']) {
                $this->Line($posx, $posy, $posx + $width, $posy, is_array($border['T']) ? $border['T'] : []);
            }

            if (isset($border['R']) && $border['R']) {
                $this->Line(
                    $posx + $width,
                    $posy,
                    $posx + $width,
                    $posy + $height,
                    is_array($border['R']) ? $border['R'] : [],
                );
            }

            if (isset($border['B']) && $border['B']) {
                $this->Line(
                    $posx,
                    $posy + $height,
                    $posx + $width,
                    $posy + $height,
                    is_array($border['B']) ? $border['B'] : [],
                );
            }

            return;
        }

        if (isset($border['all']) && is_array($border['all'])) {
            $this->setLineStyle($border['all'], true);
        }

        $style = $this->currentLineStyle();
        $style['fillColor'] = $this->fillcolorspec;
        $this->emitToPage($eng->graph->getBasicRect((float) $x, (float) $y, (float) $w, (float) $h, $mode, $style));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Curve(
        $x0,
        $y0,
        $x1,
        $y1,
        $x2,
        $y2,
        $x3,
        $y3,
        $style = '',
        $line_style = [],
        $fill_color = [],
    ) {
        $style = $this->shapeStyleFromLegacy($line_style, $fill_color);
        $this->emitToPage($this->engine()->graph->getCurve(
            (float) $x0,
            (float) $y0,
            (float) $x1,
            (float) $y1,
            (float) $x2,
            (float) $y2,
            (float) $x3,
            (float) $y3,
            $this->shapeModeFromLegacy($style, false),
            $style,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Polycurve($x0, $y0, $segments, $style = '', $line_style = [], $fill_color = [])
    {
        $inputSegments = $segments;
        $segments = [];
        foreach (is_array($inputSegments) ? $inputSegments : [] as $segment) {
            if (!is_array($segment)) {
                continue;
            }

            $segments[] = array_map(static fn(mixed $val): float => (float) $val, array_values($segment));
        }

        $graphStyle = $this->shapeStyleFromLegacy($line_style, $fill_color);
        $this->emitToPage($this->engine()->graph->getPolycurve(
            (float) $x0,
            (float) $y0,
            $segments,
            $this->shapeModeFromLegacy($style, false),
            $graphStyle,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Ellipse(
        $x0,
        $y0,
        $rx,
        $ry = 0,
        $angle = 0,
        $astart = 0,
        $afinish = 360,
        $style = '',
        $line_style = [],
        $fill_color = [],
        $nc = 2,
    ) {
        $vrad = (float) $ry;
        if ($vrad <= 0) {
            $vrad = (float) $rx;
        }

        $style = $this->shapeStyleFromLegacy($line_style, $fill_color);
        $this->emitToPage($this->engine()->graph->getEllipse(
            (float) $x0,
            (float) $y0,
            (float) $rx,
            $vrad,
            (float) $angle,
            (float) $astart,
            (float) $afinish,
            $this->shapeModeFromLegacy($style, false),
            $style,
            max(2, (int) $nc),
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Circle(
        $x0,
        $y0,
        $r,
        $angstr = 0,
        $angend = 360,
        $style = '',
        $line_style = [],
        $fill_color = [],
        $nc = 2,
    ) {
        $style = $this->shapeStyleFromLegacy($line_style, $fill_color);
        $this->emitToPage($this->engine()->graph->getCircle(
            (float) $x0,
            (float) $y0,
            (float) $r,
            (float) $angstr,
            (float) $angend,
            $this->shapeModeFromLegacy($style, false),
            $style,
            max(2, (int) $nc),
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function PolyLine($p, $style = '', $line_style = [], $fill_color = [])
    {
        /** @var array<int, float> $points */
        $points = [];
        foreach (is_array($p) ? array_values($p) : [] as $val) {
            $points[] = (float) $val;
        }
        $style = $this->shapeStyleFromLegacy($line_style, $fill_color);
        /** @var array<array-key, float> $points */
        $this->emitToPage($this->engine()->graph->getBasicPolygon(
            $points,
            $this->shapeModeFromLegacy($style, false),
            $style,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Polygon($p, $style = '', $line_style = [], $fill_color = [], $closed = true)
    {
        /** @var array<int, float> $points */
        $points = [];
        foreach (is_array($p) ? array_values($p) : [] as $val) {
            $points[] = (float) $val;
        }

        $styles = $this->segmentStylesFromLegacy($line_style, $fill_color);
        /** @var array<array-key, float> $points */
        $this->emitToPage($this->engine()->graph->getPolygon(
            $points,
            $this->shapeModeFromLegacy($style, false, (bool) $closed),
            $styles,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function RegularPolygon(
        $x0,
        $y0,
        $r,
        $ns,
        $angle = 0,
        $draw_circle = false,
        $style = '',
        $line_style = [],
        $fill_color = [],
        $circle_style = '',
        $circle_outLine_style = [],
        $circle_fill_color = [],
    ) {
        // Legacy draws the inscribed circle before the polygon, so its
        // style parameters persist first.
        $cirmode = (bool) $draw_circle ? $this->shapeModeFromLegacy($circle_style, false) : '';
        $cirstyle = (bool) $draw_circle
            ? $this->shapeStyleFromLegacy($circle_outLine_style, $circle_fill_color)
            : [];
        $styles = $this->segmentStylesFromLegacy($line_style, $fill_color);
        $this->emitToPage($this->engine()->graph->getRegularPolygon(
            (float) $x0,
            (float) $y0,
            (float) $r,
            (int) $ns,
            (float) $angle,
            $this->shapeModeFromLegacy($style, false, true),
            $styles,
            $cirmode,
            $cirstyle,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function StarPolygon(
        $x0,
        $y0,
        $r,
        $nv,
        $ng,
        $angle = 0,
        $draw_circle = false,
        $style = '',
        $line_style = [],
        $fill_color = [],
        $circle_style = '',
        $circle_outLine_style = [],
        $circle_fill_color = [],
    ) {
        $cirmode = (bool) $draw_circle ? $this->shapeModeFromLegacy($circle_style, false) : '';
        $cirstyle = (bool) $draw_circle
            ? $this->shapeStyleFromLegacy($circle_outLine_style, $circle_fill_color)
            : [];
        $styles = $this->segmentStylesFromLegacy($line_style, $fill_color);
        $this->emitToPage($this->engine()->graph->getStarPolygon(
            (float) $x0,
            (float) $y0,
            (float) $r,
            (int) $nv,
            (int) $ng,
            (float) $angle,
            $this->shapeModeFromLegacy($style, false, true),
            $styles,
            $cirmode,
            $cirstyle,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function RoundedRect(
        $x,
        $y,
        $w,
        $h,
        $r,
        $round_corner = '1111',
        $style = '',
        $border_style = [],
        $fill_color = [],
    ) {
        $this->RoundedRectXY($x, $y, $w, $h, $r, $r, $round_corner, $style, $border_style, $fill_color);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function RoundedRectXY(
        $x,
        $y,
        $w,
        $h,
        $rx,
        $ry,
        $round_corner = '1111',
        $style = '',
        $border_style = [],
        $fill_color = [],
    ) {
        $style = $this->shapeStyleFromLegacy($border_style, $fill_color);
        $this->emitToPage($this->engine()->graph->getRoundedRect(
            (float) $x,
            (float) $y,
            (float) $w,
            (float) $h,
            (float) $rx,
            (float) $ry,
            (string) $round_corner,
            $this->shapeModeFromLegacy($style, false),
            $style,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Arrow($x0, $y0, $x1, $y1, $head_style = 0, $arm_size = 5, $arm_angle = 15)
    {
        $this->emitToPage($this->engine()->graph->getArrow(
            (float) $x0,
            (float) $y0,
            (float) $x1,
            (float) $y1,
            (int) $head_style,
            (float) $arm_size,
            (int) $arm_angle,
            $this->currentLineStyle(),
        ));
    }

    // ===================================================================
    // Destinations and bookmarks (Stage 4).
    // ===================================================================

    /**
     * @throws \RuntimeException
     */
    public function setDestination($name, $y = -1, $page = '', $x = -1)
    {
        if ($this->docstate !== 2) {
            return false;
        }

        $name = (string) $name;
        $posx = (float) $x === -1.0 ? $this->posx : (float) $x;
        $posy = (float) $y === -1.0 ? $this->posy : (float) $y;
        $page = (string) $page === '' ? $this->getPage() : (int) $page;
        $this->engine()->setNamedDestination($name, $page - 1, $posx, $posy);
        $this->nameddests[$name] = ['x' => $posx, 'y' => $posy, 'p' => $page];
        return $name;
    }

    public function getDestination()
    {
        return $this->nameddests;
    }

    /**
     * @throws \RuntimeException
     */
    public function setBookmark(
        $txt,
        $level = 0,
        $y = -1,
        $page = '',
        $style = '',
        $color = [0, 0, 0],
        $x = -1,
        $link = '',
    ) {
        $this->Bookmark($txt, $level, $y, $page, $style, $color, $x, $link);
    }

    /**
     * @throws \RuntimeException
     */
    public function Bookmark(
        $txt,
        $level = 0,
        $y = -1,
        $page = '',
        $style = '',
        $color = [0, 0, 0],
        $x = -1,
        $link = '',
    ) {
        if ($this->docstate !== 2) {
            return;
        }

        $posx = (float) $x === -1.0 ? $this->posx : (float) $x;
        $posy = (float) $y === -1.0 ? $this->posy : (float) $y;
        $page = (string) $page === '' ? $this->getPage() : (int) $page;
        $color = is_array($color) && $color !== [] ? $this->colorSpecFromLegacy($color) : '';
        $this->engine()->setBookmark(
            (string) $txt,
            is_string($link) ? $link : '',
            max(0, (int) $level),
            $page - 1,
            $posx,
            $posy,
            (string) $style,
            $color,
        );
    }

    // ===================================================================
    // JavaScript and forms (Stage 4).
    // ===================================================================

    /**
     * @throws \RuntimeException
     */
    public function IncludeJS($script)
    {
        $this->engine()->appendRawJavaScript((string) $script);
    }

    /**
     * @throws \RuntimeException
     */
    public function addJavascriptObject($script, $onload = false)
    {
        return $this->engine()->addRawJavaScriptObj((string) $script, (bool) $onload);
    }

    /**
     * @throws \RuntimeException
     */
    public function setFormDefaultProp($prop = [])
    {
        $this->formdefaultprop = [];
        foreach (is_array($prop) ? $prop : [] as $key => $val) {
            $this->formdefaultprop[(string) $key] = $val;
        }

        $this->engine()->setDefJSAnnotProp($this->formdefaultprop);
    }

    public function getFormDefaultProp()
    {
        return $this->formdefaultprop;
    }

    /**
     * Normalize legacy list/combo values: either a list of strings or a
     * list of [export, display] pairs (the engine requires a homogeneous list).
     *
     * @return array<array-key, array{0: string, 1: string}>|array<array-key, string>
     */
    protected function formFieldValues(mixed $values): array
    {
        $input = is_array($values) ? array_values($values) : [];
        $first = $input[0] ?? null;
        if (is_array($first)) {
            $pairs = [];
            foreach ($input as $val) {
                $pair = is_array($val) ? array_values($val) : [$val, $val];
                $export = (string) ($pair[0] ?? '');
                $display = array_key_exists(1, $pair) ? (string) $pair[1] : $export;
                $pairs[] = [$export, $display];
            }

            return $pairs;
        }

        $strings = [];
        foreach ($input as $val) {
            $strings[] = is_scalar($val) ? (string) $val : '';
        }

        return $strings;
    }

    /**
     * Normalize legacy form-field (prop, opt) pairs for the engine.
     *
     * @return array{0: TAnnotOpts, 1: array<string, string>} [opt, jsp]
     */
    protected function formFieldOptions(mixed $prop, mixed $opt): array
    {
        /** @var array<string, string> $jsp */
        $jsp = [];
        foreach (array_merge($this->formdefaultprop, is_array($prop) ? $prop : []) as $key => $val) {
            if (is_array($val)) {
                // Legacy color properties are component arrays.
                $jsp[(string) $key] = $this->colorSpecFromLegacy($val);
            } elseif (is_bool($val)) {
                $jsp[(string) $key] = $val ? 'true' : 'false';
            } elseif (is_scalar($val)) {
                $jsp[(string) $key] = (string) $val;
            }
        }

        /** @var array<string, mixed> $annot */
        $annot = [];
        foreach (is_array($opt) ? $opt : [] as $key => $val) {
            $annot[strtolower((string) $key)] = $val;
        }

        $subtype = $annot['subtype'] ?? '';
        $annot['subtype'] = is_string($subtype) && $subtype !== '' ? $subtype : 'Widget';

        /** @var TAnnotOpts $annot */
        return [$annot, $jsp];
    }

    /**
     * Attach a freshly created annotation/form-field widget to the current
     * page so the engine emits it in the page's /Annots array.
     *
     * The engine's addFF*()/setAnnotation()/setLink() methods register the
     * annotation object and return its id, but leave page attachment to the
     * caller (see Com\Tecnick\Pdf\JavaScript::setAnnotation). An id of 0 means
     * the object was not created (e.g. captured by an XObject template or
     * suppressed in PDF/X mode) and must not be referenced.
     *
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function attachAnnotRef(int $oid): void
    {
        if ($oid > 0) {
            $this->engine()->page->addAnnotRef($oid);
        }
    }

    /**
     * Advance the horizontal cursor after placing a form field, matching the
     * legacy behaviour where each field method shifts the current X position
     * by the field width (RTL-aware). The shift is applied to the live cursor
     * regardless of any explicit x/y override, exactly as legacy Tcpdf does.
     */
    protected function advanceFormFieldX(float $width): void
    {
        if ($this->rtlmode) {
            $this->posx -= $width;
        } else {
            $this->posx += $width;
        }
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function TextField($name, $w, $h, $prop = [], $opt = [], $x = null, $y = null, $js = false)
    {
        if ($this->docstate !== 2) {
            return;
        }

        [$opt, $jsp] = $this->formFieldOptions($prop, $opt);
        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        if ((bool) $js) {
            $this->engine()->addJSText((string) $name, $posx, $posy, (float) $w, (float) $h, $jsp);
            return;
        }

        $oid = $this->engine()->addFFText((string) $name, $posx, $posy, (float) $w, (float) $h, $opt, $jsp);
        $this->attachAnnotRef($oid);
        $this->advanceFormFieldX((float) $w);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function RadioButton(
        $name,
        $w,
        $prop = [],
        $opt = [],
        $onvalue = 'On',
        $checked = false,
        $x = null,
        $y = null,
        $js = false,
    ) {
        if ($this->docstate !== 2) {
            return;
        }

        [$opt, $jsp] = $this->formFieldOptions($prop, $opt);
        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        if ((bool) $js) {
            $this->engine()->addJSRadioButton((string) $name, $posx, $posy, (float) $w, $jsp);
            return;
        }

        $oid = $this->engine()->addFFRadioButton(
            (string) $name,
            $posx,
            $posy,
            (float) $w,
            (string) $onvalue,
            (bool) $checked,
            $opt,
            $jsp,
        );
        $this->attachAnnotRef($oid);
        $this->advanceFormFieldX((float) $w);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function ListBox($name, $w, $h, $values, $prop = [], $opt = [], $x = null, $y = null, $js = false)
    {
        if ($this->docstate !== 2) {
            return;
        }

        [$opt, $jsp] = $this->formFieldOptions($prop, $opt);
        $values = $this->formFieldValues($values);
        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        if ((bool) $js) {
            $this->engine()->addJSListBox((string) $name, $posx, $posy, (float) $w, (float) $h, $values, $jsp);
            return;
        }

        $oid = $this->engine()->addFFListBox(
            (string) $name,
            $posx,
            $posy,
            (float) $w,
            (float) $h,
            $values,
            $opt,
            $jsp,
        );
        $this->attachAnnotRef($oid);
        $this->advanceFormFieldX((float) $w);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function ComboBox($name, $w, $h, $values, $prop = [], $opt = [], $x = null, $y = null, $js = false)
    {
        if ($this->docstate !== 2) {
            return;
        }

        [$opt, $jsp] = $this->formFieldOptions($prop, $opt);
        $values = $this->formFieldValues($values);
        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        if ((bool) $js) {
            $this->engine()->addJSComboBox((string) $name, $posx, $posy, (float) $w, (float) $h, $values, $jsp);
            return;
        }

        $oid = $this->engine()->addFFComboBox(
            (string) $name,
            $posx,
            $posy,
            (float) $w,
            (float) $h,
            $values,
            $opt,
            $jsp,
        );
        $this->attachAnnotRef($oid);
        $this->advanceFormFieldX((float) $w);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function CheckBox(
        $name,
        $w,
        $checked = false,
        $prop = [],
        $opt = [],
        $onvalue = 'Yes',
        $x = null,
        $y = null,
        $js = false,
    ) {
        if ($this->docstate !== 2) {
            return;
        }

        [$opt, $jsp] = $this->formFieldOptions($prop, $opt);
        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        if ((bool) $js) {
            $this->engine()->addJSCheckBox((string) $name, $posx, $posy, (float) $w, $jsp);
            return;
        }

        $oid = $this->engine()->addFFCheckBox(
            (string) $name,
            $posx,
            $posy,
            (float) $w,
            (string) $onvalue,
            (bool) $checked,
            $opt,
            $jsp,
        );
        $this->attachAnnotRef($oid);
        $this->advanceFormFieldX((float) $w);
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function Button(
        $name,
        $w,
        $h,
        $caption,
        $action,
        $prop = [],
        $opt = [],
        $x = null,
        $y = null,
        $js = false,
    ) {
        if ($this->docstate !== 2) {
            return;
        }

        [$opt, $jsp] = $this->formFieldOptions($prop, $opt);
        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        if (is_array($action)) {
            /** @var array<string, mixed> $action */
            $action = [];
            foreach ($action as $key => $val) {
                $action[(string) $key] = $val;
            }
        } else {
            $action = (string) $action;
        }
        if ((bool) $js) {
            $this->engine()->addJSButton(
                (string) $name,
                $posx,
                $posy,
                (float) $w,
                (float) $h,
                (string) $caption,
                is_string($action) ? $action : '',
                $jsp,
            );
            return;
        }

        $ffopt = $opt;

        $oid = $this->engine()->addFFButton(
            (string) $name,
            $posx,
            $posy,
            (float) $w,
            (float) $h,
            (string) $caption,
            $action,
            $ffopt,
            $jsp,
        );
        $this->attachAnnotRef($oid);
        $this->advanceFormFieldX((float) $w);
    }

    // ===================================================================
    // Signatures and rights (Stage 4).
    // ===================================================================

    /**
     * @throws \RuntimeException
     */
    public function setUserRights(
        $enable = true,
        $document = '/FullSave',
        $annots = '/Create/Delete/Modify/Copy/Import/Export',
        $form = '/Add/Delete/FillIn/Import/Export/SubmitStandalone/SpawnTemplate',
        $signature = '/Modify',
        $ef = '/Create/Delete/Modify/Import',
        $formex = '',
    ) {
        $this->engine()->setUserRights([
            'enabled' => (bool) $enable,
            'document' => (string) $document,
            'annots' => (string) $annots,
            'form' => (string) $form,
            'signature' => (string) $signature,
            'ef' => (string) $ef,
            'formex' => (string) $formex,
        ]);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function setSignature(
        $signing_cert = '',
        $private_key = '',
        #[\SensitiveParameter]
        $private_key_password = '',
        $extracerts = '',
        $cert_type = 2,
        $info = [],
        $approval = '',
    ) {
        $info = is_array($info) ? $info : [];
        $extracerts = (string) $extracerts;
        /** @var array{appearance: array{ap: array<string, string|array<string, string>>, as: string, empty: array<int, array{objid: int, name: string, page: int, rect: string}>, name: string, page: int, rect: string, xobj: string}, approval: string, cert_type: int, extracerts: ?string, info: array{ContactInfo: string, Location: string, Name: string, Reason: string}, password: string, privkey: string, signcert: string} $data */
        $data = [
            'appearance' => [
                'ap' => [],
                'as' => '',
                'empty' => [],
                'name' => '',
                'page' => 0,
                'rect' => '',
                'xobj' => '',
            ],
            'approval' => (string) $approval,
            'cert_type' => (int) $cert_type,
            'extracerts' => $extracerts === '' ? null : $extracerts,
            'info' => [
                'ContactInfo' => (string) ($info['ContactInfo'] ?? ''),
                'Location' => (string) ($info['Location'] ?? ''),
                'Name' => (string) ($info['Name'] ?? ''),
                'Reason' => (string) ($info['Reason'] ?? ''),
            ],
            'password' => (string) $private_key_password,
            'privkey' => (string) ($private_key === '' ? $signing_cert : $private_key),
            'signcert' => (string) $signing_cert,
        ];
        $this->engine()->setSignature($data);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setSignatureAppearance($x = 0, $y = 0, $w = 0, $h = 0, $page = -1, $name = '')
    {
        $this->engine()->setSignatureAppearance(
            (float) $x,
            (float) $y,
            (float) $w,
            (float) $h,
            (int) $page > 0 ? (int) $page - 1 : -1,
            (string) $name,
        );
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function addEmptySignatureAppearance($x = 0, $y = 0, $w = 0, $h = 0, $page = -1, $name = '')
    {
        $this->engine()->addEmptySignatureAppearance(
            (float) $x,
            (float) $y,
            (float) $w,
            (float) $h,
            (int) $page > 0 ? (int) $page - 1 : -1,
            (string) $name,
        );
    }

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function setTimeStamp(
        $tsa_host = '',
        $tsa_username = '',
        #[\SensitiveParameter]
        $tsa_password = '',
        $tsa_cert = '',
    ) {
        $this->engine()->setSignTimeStamp([
            'enabled' => (string) $tsa_host !== '',
            'host' => (string) $tsa_host,
            'username' => (string) $tsa_username,
            'password' => (string) $tsa_password,
            'cert' => (string) $tsa_cert,
            'hash_algorithm' => 'sha256',
            'policy_oid' => '',
            'nonce_enabled' => true,
            'timeout' => 30,
            'verify_peer' => true,
        ]);
    }

    // ===================================================================
    // Page groups and numbering.
    // ===================================================================

    public function startPageGroup($page = null)
    {
        // The group takes effect from the next added page.
        $this->pagegroupsused = true;
        $this->nextpagegroup++;
    }

    public function setStartingPageNumber($num = 1)
    {
        $this->startingpagenumber = max(1, (int) $num);
    }

    /**
     * @return string Right-shift compensation prefix (unused by the facade).
     */
    public function getAliasRightShift()
    {
        return '';
    }

    /**
     * @return string Total number of pages, or the legacy alias placeholder.
     */
    public function getAliasNbPages()
    {
        if ($this->decortotalpages > 0) {
            return (string) $this->decortotalpages;
        }

        return '{nb}';
    }

    /**
     * @return string Current page number, or the legacy alias placeholder.
     * @throws \RuntimeException
     */
    public function getAliasNumPage()
    {
        if ($this->decortotalpages > 0) {
            return (string) $this->PageNo();
        }

        return '{pnb}';
    }

    /**
     * @return string Total pages in the current group, or the legacy alias placeholder.
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getPageGroupAlias()
    {
        if ($this->decortotalpages > 0) {
            $eng = $this->engine();
            $group = (int) $eng->page->getPage()['group'];
            $total = 0;
            foreach ($eng->page->getPages() as $page) {
                if ((int) $page['group'] !== $group) {
                    continue;
                }

                ++$total;
            }

            return (string) $total;
        }

        return '{gnb}';
    }

    /**
     * @return string Page number in the current group, or the legacy alias placeholder.
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getPageNumGroupAlias()
    {
        if ($this->decortotalpages > 0) {
            return (string) $this->getGroupPageNo();
        }

        return '{gpnb}';
    }

    /**
     * @return int Current page number within its page group.
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getGroupPageNo()
    {
        // The engine computes the group-relative page 'num' lazily at output
        // time, so while the footer is being rendered it is still unset and
        // the previous fallback leaked the absolute page index (e.g. "6 / 4"
        // on the 4th page of the second group). Count this page's position
        // within its own group directly instead: groups are contiguous
        // (startPageGroup only ever advances the group id), so the position is
        // the number of same-group pages up to and including the current one.
        $eng = $this->engine();
        /** @var array<string, mixed> $current */
        $current = $eng->page->getPage();
        $group = (int) ($current['group'] ?? 0);
        $currentpid = (int) $eng->page->getPageId();
        $num = 0;
        foreach ($eng->page->getPages() as $idx => $page) {
            if ((int) $idx > $currentpid || (int) $page['group'] !== $group) {
                continue;
            }

            ++$num;
        }

        return $num > 0 ? $num : $currentpid + 1;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getGroupPageNoFormatted()
    {
        return number_format((float) $this->getGroupPageNo(), 0, '.', ',');
    }

    /**
     * @throws \RuntimeException
     */
    public function PageNoFormatted()
    {
        return number_format((float) ($this->PageNo() + $this->startingpagenumber - 1), 0, '.', ',');
    }

    // ===================================================================
    // Layers, visibility, transparency (Stage 4).
    // ===================================================================

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function startLayer($name = '', $print = true, $view = true, $lock = true)
    {
        $this->openlayers++;
        $this->emitToPage($this->engine()->newLayer((string) $name, [], (bool) $print, (bool) $view, (bool) $lock));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function endLayer()
    {
        if ($this->openlayers > 0) {
            $this->openlayers--;
            $this->emitToPage($this->engine()->closeLayer());
        }
    }

    /**
     * @throws \Exception
     */
    public function setVisibility($v)
    {
        $this->endLayer();
        switch (strtolower((string) $v)) {
            case 'print':
                $this->startLayer('Print', true, false, true);
                break;
            case 'view':
            case 'screen':
                $this->startLayer('View', false, true, true);
                break;
            case 'all':
                break;
            default:
                $this->Error('Incorrect visibility: ' . (string) $v);
        }
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setOverprint($stroking = true, $nonstroking = null, $mode = 0)
    {
        $this->overprint = [
            'OP' => (bool) $stroking,
            'op' => $nonstroking === null ? (bool) $stroking : (bool) $nonstroking,
            'OPM' => (int) $mode,
        ];
        $this->emitToPage($this->engine()->graph->getOverprint(
            (bool) $stroking,
            $nonstroking === null ? null : (bool) $nonstroking,
            (int) $mode,
        ));
    }

    public function getOverprint()
    {
        return $this->overprint;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function setAlpha($stroking = 1, $bm = 'Normal', $nonstroking = null, $ais = false)
    {
        $stroking = (float) $stroking;
        $nonstroking = $nonstroking === null ? $stroking : (float) $nonstroking;
        $this->alpha = [
            'CA' => $stroking,
            'ca' => $nonstroking,
            'BM' => '/' . (string) $bm,
            'AIS' => (bool) $ais,
        ];
        $cmd = $this->engine()->graph->getAlpha($stroking, (string) $bm, $nonstroking, (bool) $ais);
        $this->registerTemplateExtGState($cmd);
        $this->emitToPage($cmd);
    }

    /**
     * Register the ExtGState referenced by the given command with the open
     * XObject template resource dictionary.
     *
     * @throws \RuntimeException
     */
    protected function registerTemplateExtGState(string $cmd): void
    {
        $match = [];
        if ($this->xobjtid !== '' && preg_match('|/GS([0-9]+) gs|', $cmd, $match) === 1) {
            $this->engine()->addXObjectExtGStateID($this->xobjtid, (int) ($match[1]));
        }
    }

    public function getAlpha()
    {
        return $this->alpha;
    }

    public function setJPEGQuality($quality)
    {
        $quality = (int) $quality;
        $this->jpegquality = $quality < 1 || $quality > 100 ? 75 : $quality;
    }

    public function setDefaultTableColumns($cols = 4)
    {
        // Table layout is computed by the engine HTML renderer.
    }

    public function setCellHeightRatio($h)
    {
        $this->cellheightratio = (float) $h;
    }

    /**
     * @return float Cell height ratio.
     */
    public function getCellHeightRatio()
    {
        return $this->cellheightratio;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function setPDFVersion($version = '1.7')
    {
        $this->engine()->setPDFVersion((string) $version);
    }

    /**
     * @throws \RuntimeException
     */
    public function setViewerPreferences($preferences)
    {
        /** @var array{CenterWindow?: bool, Direction?: string, DisplayDocTitle?: bool, Duplex?: string, FitWindow?: bool, HideMenubar?: bool, HideToolbar?: bool, HideWindowUI?: bool, NonFullScreenPageMode?: string, NumCopies?: int, PickTrayByPDFSize?: bool, PrintArea?: string, PrintClip?: string, PrintPageRange?: array<array-key, int>, PrintScaling?: string, ViewArea?: string, ViewClip?: string} $prefs */
        $prefs = is_array($preferences) ? $preferences : [];
        $this->engine()->setViewerPreferences($prefs);
    }

    // ===================================================================
    // Prepress marks and gradients (Stage 4).
    // ===================================================================

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function colorRegistrationBar(
        $x,
        $y,
        $w,
        $h,
        $transition = true,
        $vertical = false,
        $colors = 'A,R,G,B,C,M,Y,K',
    ) {
        $white = 'rgb(255,255,255)';
        $barmap = [
            'A' => ['g(0%)', 'g(100%)'],
            'W' => ['g(100%)', 'g(0%)'],
            'R' => ['rgb(100%,0%,0%)', $white],
            'G' => ['rgb(0%,100%,0%)', $white],
            'B' => ['rgb(0%,0%,100%)', $white],
            'C' => ['cmyk(100%,0%,0%,0%)', 'cmyk(0%,0%,0%,0%)'],
            'M' => ['cmyk(0%,100%,0%,0%)', 'cmyk(0%,0%,0%,0%)'],
            'Y' => ['cmyk(0%,0%,100%,0%)', 'cmyk(0%,0%,0%,0%)'],
            'K' => ['cmyk(0%,0%,0%,100%)', 'cmyk(0%,0%,0%,0%)'],
        ];
        $bars = [];
        foreach (explode(',', strtoupper((string) $colors)) as $letter) {
            $letter = trim($letter);
            if (isset($barmap[$letter])) {
                $pair = $barmap[$letter];
                if (!(bool) $transition) {
                    $pair[1] = $pair[0];
                }

                $bars[] = $pair;
            }
        }

        if ($bars === []) {
            return;
        }

        $this->emitToPage($this->engine()->graph->getColorRegistrationBar(
            (float) $x,
            (float) $y,
            (float) $w,
            (float) $h,
            (bool) $vertical,
            $bars,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function cropMark($x, $y, $w, $h, $type = 'T,R,B,L', $color = [100, 100, 100, 100, 'All'])
    {
        $style = $this->currentLineStyle();
        $style['lineColor'] = $this->colorSpecFromLegacy($color);
        $this->emitToPage($this->engine()->graph->getCropMark(
            (float) $x,
            (float) $y,
            (float) $w,
            (float) $h,
            str_replace(',', '', strtoupper((string) $type)),
            $style,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function registrationMark(
        $x,
        $y,
        $r,
        $double = false,
        $cola = [100, 100, 100, 100, 'All'],
        $colb = [0, 0, 0, 0, 'None'],
    ) {
        $this->emitToPage($this->engine()->graph->getRegistrationMark(
            (float) $x,
            (float) $y,
            (float) $r,
            (bool) $double,
            $this->colorSpecFromLegacy($cola),
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function registrationMarkCMYK($x, $y, $r)
    {
        $this->emitToPage($this->engine()->graph->getCmykRegistrationMark((float) $x, (float) $y, (float) $r));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function LinearGradient($x, $y, $w, $h, $col1 = [], $col2 = [], $coords = [0, 0, 1, 0])
    {
        $coords = array_map(
            static fn(mixed $val): float => (float) $val,
            array_values(is_array($coords) ? $coords : [0, 0, 1, 0]),
        );
        $this->emitToPage($this->engine()->graph->getLinearGradient(
            (float) $x,
            (float) $y,
            (float) $w,
            (float) $h,
            $this->colorSpecFromLegacy($col1),
            $this->colorSpecFromLegacy($col2),
            $coords,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function RadialGradient($x, $y, $w, $h, $col1 = [], $col2 = [], $coords = [0.5, 0.5, 0.5, 0.5, 1])
    {
        $coords = array_map(
            static fn(mixed $val): float => (float) $val,
            array_values(is_array($coords) ? $coords : [0.5, 0.5, 0.5, 0.5, 1]),
        );
        $this->emitToPage($this->engine()->graph->getRadialGradient(
            (float) $x,
            (float) $y,
            (float) $w,
            (float) $h,
            $this->colorSpecFromLegacy($col1),
            $this->colorSpecFromLegacy($col2),
            $coords,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function CoonsPatchMesh(
        $x,
        $y,
        $w,
        $h,
        $col1 = [],
        $col2 = [],
        $col3 = [],
        $col4 = [],
        $coords = [
            0.00,
            0.0,
            0.33,
            0.00,
            0.67,
            0.00,
            1.00,
            0.00,
            1.00,
            0.33,
            1.00,
            0.67,
            1.00,
            1.00,
            0.67,
            1.00,
            0.33,
            1.00,
            0.00,
            1.00,
            0.00,
            0.67,
            0.00,
            0.33,
        ],
        $coords_min = 0,
        $coords_max = 1,
        $antialias = false,
    ) {
        // Multi-patch form: $coords is an array of patches, each carrying an
        // 'f' edge flag (legacy detects this with isset($coords[0]['f'])).
        if (is_array($coords) && isset($coords[0]) && is_array($coords[0]) && isset($coords[0]['f'])) {
            $this->emitToPage($this->engine()->graph->getCoonsPatchMesh(
                (float) $x,
                (float) $y,
                (float) $w,
                (float) $h,
                $this->coonsPatchArray($coords),
                (float) $coords_min,
                (float) $coords_max,
                (bool) $antialias,
            ));
            return;
        }

        $coords = array_map(
            static fn(mixed $val): float => (float) $val,
            array_values(is_array($coords) ? $coords : []),
        );
        // The engine corner names use PDF y-up coordinates, which matches
        // the legacy corner order directly (verified against the reference
        // renderer output).
        $this->emitToPage($this->engine()->graph->getCoonsPatchMeshWithCoords(
            (float) $x,
            (float) $y,
            (float) $w,
            (float) $h,
            $this->colorSpecFromLegacy($col1),
            $this->colorSpecFromLegacy($col2),
            $this->colorSpecFromLegacy($col3),
            $this->colorSpecFromLegacy($col4),
            $coords,
            (float) $coords_min,
            (float) $coords_max,
            (bool) $antialias,
        ));
    }

    /**
     * Convert a legacy Coons patch-mesh array (per-patch 'f', 'points', and
     * 'colors' with 0-255 r/g/b keys) to the engine shape (normalized
     * red/green/blue floats).
     *
     * @param array<array-key, mixed> $patches
     *
     * @return list<array{f: int, points: list<float>, colors: list<array{red: float, green: float, blue: float}>}>
     */
    protected function coonsPatchArray(array $patches): array
    {
        $out = [];
        foreach ($patches as $patch) {
            if (!is_array($patch)) {
                continue;
            }

            $points = isset($patch['points']) && is_array($patch['points'])
                ? array_map(static fn(mixed $v): float => (float) $v, array_values($patch['points']))
                : [];

            $colors = [];
            if (isset($patch['colors']) && is_array($patch['colors'])) {
                foreach ($patch['colors'] as $color) {
                    if (!is_array($color)) {
                        continue;
                    }

                    $colors[] = [
                        'red' => (isset($color['r']) ? (float) $color['r'] : 0.0) / 255,
                        'green' => (isset($color['g']) ? (float) $color['g'] : 0.0) / 255,
                        'blue' => (isset($color['b']) ? (float) $color['b'] : 0.0) / 255,
                    ];
                }
            }

            $out[] = [
                'f' => isset($patch['f']) ? (int) $patch['f'] : 0,
                'points' => $points,
                'colors' => $colors,
            ];
        }

        return $out;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function Gradient($type, $coords, $stops, $background = [], $antialias = false)
    {
        $coords = array_map(
            static fn(mixed $val): float => (float) $val,
            array_values(is_array($coords) ? $coords : []),
        );
        $inputStops = $stops;
        $stops = [];
        foreach (is_array($inputStops) ? $inputStops : [] as $stop) {
            if (!is_array($stop)) {
                continue;
            }

            $stops[] = [
                'color' => $this->colorSpecFromLegacy($stop['color'] ?? [0, 0, 0]),
                'offset' => (float) ($stop['offset'] ?? 0),
                'exponent' => (float) ($stop['exponent'] ?? 1),
                'opacity' => (float) ($stop['opacity'] ?? 1),
            ];
        }

        $background = is_array($background) && $background !== [] ? $this->colorSpecFromLegacy($background) : '';
        $this->emitToPage($this->engine()->graph->getGradient(
            (int) $type,
            $coords,
            $stops,
            $background,
            (bool) $antialias,
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function PieSector($xc, $yc, $r, $a, $b, $style = 'FD', $cw = true, $o = 90)
    {
        $angs = (float) $a;
        $angf = (float) $b;
        if ((bool) $cw) {
            $tmp = $angf;
            $angf = (float) $o - $angs;
            $angs = (float) $o - $tmp;
        } else {
            $angs += (float) $o;
            $angf += (float) $o;
        }

        $this->emitToPage($this->engine()->graph->getPieSector(
            (float) $xc,
            (float) $yc,
            (float) $r,
            $angs,
            $angf,
            $this->shapeModeFromLegacy($style, true),
            $this->shapeStyleFromLegacy([], []),
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Graph\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function PieSectorXY($xc, $yc, $rx, $ry, $a, $b, $style = 'FD', $cw = false, $o = 0, $nc = 2)
    {
        // The engine pie sector is circular: elliptical sectors are
        // approximated by scaling a circular sector of radius rx.
        $rx = (float) $rx;
        $ry = (float) $ry;
        if ($rx <= 0) {
            return;
        }

        $eng = $this->engine();
        $out = $eng->graph->getStartTransform();
        if ($ry > 0 && $ry !== $rx) {
            $out .= $eng->graph->getScaling(1.0, $ry / $rx, (float) $xc, (float) $yc);
        }

        $angs = (float) $a;
        $angf = (float) $b;
        if ((bool) $cw) {
            $tmp = $angf;
            $angf = (float) $o - $angs;
            $angs = (float) $o - $tmp;
        } else {
            $angs += (float) $o;
            $angf += (float) $o;
        }

        $out .= $eng->graph->getPieSector(
            (float) $xc,
            (float) $yc,
            $rx,
            $angs,
            $angf,
            $this->shapeModeFromLegacy($style, true),
            $this->shapeStyleFromLegacy([], []),
        );
        $out .= $eng->graph->getStopTransform();
        $this->emitToPage($out);
    }

    /**
     * [BREAKING CHANGE] EPS/AI vector import is not supported.
     *
     * The modern engine has no PostScript interpreter: convert EPS/AI
     * artwork to SVG (e.g. `inkscape file.eps --export-filename=file.svg`
     * or `mutool convert`) and use ImageSVG() instead. As a convenience,
     * SVG files passed to this method are dispatched to ImageSVG() and
     * raster files to Image(); EPS/AI input is silently skipped.
     *
     * @throws \Exception
     */
    public function ImageEps(
        $file,
        $x = null,
        $y = null,
        $w = 0,
        $h = 0,
        $link = '',
        $useBoundingBox = true,
        $align = '',
        $palign = '',
        $border = 0,
        $fitonpage = false,
        $fixoutvals = false,
    ) {
        $file = strtolower((string) $file);
        if (str_ends_with($file, '.svg')) {
            $this->ImageSVG($file, $x, $y, $w, $h, $link, $align, $palign, $border, $fitonpage);
            return;
        }

        if (preg_match('/\.(png|jpe?g|gif|webp)$/', $file) === 1) {
            $this->Image(
                $file,
                $x,
                $y,
                $w,
                $h,
                '',
                $link,
                $align,
                false,
                300,
                $palign,
                false,
                false,
                $border,
                false,
                false,
                $fitonpage,
            );
        }
    }

    // ===================================================================
    // Barcodes (Stage 4).
    // ===================================================================

    public function setBarcode($bc = '')
    {
        $this->docbarcode = (string) $bc;
    }

    /**
     * @return string Document barcode string.
     */
    public function getBarcode()
    {
        return $this->docbarcode;
    }

    /**
     * @throws \Exception
     */
    public function write1DBarcode(
        $code,
        $type,
        $x = null,
        $y = null,
        $w = null,
        $h = null,
        $xres = null,
        $style = [],
        $align = '',
    ) {
        $xres = $xres === null || (float) $xres <= 0 ? 0.4 : (float) $xres;
        $this->emitBarcode(
            (string) $code,
            (string) $type,
            $x,
            $y,
            $w,
            $h,
            $xres,
            $style,
            (string) $align,
            false,
        );
    }

    /**
     * @throws \Exception
     */
    public function write2DBarcode(
        $code,
        $type,
        $x = null,
        $y = null,
        $w = null,
        $h = null,
        $style = [],
        $align = '',
        $distort = false,
    ) {
        $this->emitBarcode(
            (string) $code,
            (string) $type,
            $x,
            $y,
            $w,
            $h,
            0.25,
            $style,
            (string) $align,
            true,
            (bool) $distort,
        );
    }

    /**
     * Render a barcode at the given position via the engine.
     *
     * @param float $xres Width (and height for 2D codes) of a single module
     *                    in user units, used when no explicit size is given.
     * @throws \Exception
     */
    protected function emitBarcode(
        string $code,
        string $type,
        mixed $posx,
        mixed $posy,
        mixed $width,
        mixed $height,
        float $xres,
        mixed $style,
        string $align,
        bool $is2d,
        bool $distort = false,
    ): void {
        if ($this->docstate !== 2 || $code === '') {
            return;
        }

        $eng = $this->engine();
        $style = is_array($style) ? $style : [];
        $type = strtoupper(trim($type));
        if ($type === 'RAW' || $type === 'RAW2') {
            // Legacy raw modes: RAW = comma-separated rows, RAW2 = [row][row].
            if ($type === 'RAW2') {
                $code = str_replace(['] [', '][', '[', ']'], [',', ',', '', ''], $code);
            }

            $type = $is2d ? 'SRAW' : 'LRAW';
        }

        $posx = $posx === null || (string) $posx === '' ? $this->posx : (float) $posx;
        $posy = $posy === null || (string) $posy === '' ? $this->posy : (float) $posy;

        // Natural module grid, used to derive missing dimensions.
        try {
            $info = $eng->barcode->getBarcodeObj($type, $code)->getArray();
        } catch (\Com\Tecnick\Barcode\Exception) {
            // Unsupported legacy barcode type: skipped.
            return;
        }

        $maxw = max(1, (int) $info['ncols']);
        $maxh = max(1, (int) $info['nrows']);

        $fgspec = isset($style['fgcolor']) && $style['fgcolor'] !== false
            ? $this->colorSpecFromLegacy($style['fgcolor'])
            : 'black';

        if ($is2d) {
            $this->drawBarcode2D(
                $type,
                $code,
                $posx,
                $posy,
                $width,
                $height,
                $style,
                $align,
                $maxw,
                $maxh,
                $fgspec,
                $distort,
            );
            return;
        }

        $this->drawBarcode1D($type, $code, $posx, $posy, $width, $height, $xres, $style, $align, $maxw, $fgspec);
    }

    /**
     * Render a 1D barcode with the legacy geometry: bars, the optional
     * human-readable label and the vertical padding all fit *inside* the
     * requested height (barh = height - text_height - 2*vpadding) instead of
     * the label being appended below it. Bar drawing is delegated to the
     * engine; the box, label and cursor advance mirror write1DBarcode().
     *
     * @param array<array-key, mixed> $style
     * @throws \Exception
     */
    protected function drawBarcode1D(
        string $type,
        string $code,
        float $posx,
        float $posy,
        mixed $width,
        mixed $height,
        float $xres,
        array $style,
        string $align,
        int $maxw,
        string $fgspec,
    ): void {
        $eng = $this->engine();
        $wparam = $width === null || (string) $width === '' ? null : (float) $width;
        $givenwidth = $wparam !== null && $wparam > 0;

        // Resolve the position/stretch/fitwidth defaults exactly like legacy.
        $position = isset($style['position']) && is_string($style['position']) ? strtoupper($style['position']) : '';
        if ($position === 'S') {
            $position = '';
            $style['stretch'] = true;
        }

        $fitwidth = isset($style['fitwidth']) ? (bool) $style['fitwidth'] : !isset($style['stretch']);
        if ($fitwidth) {
            $style['stretch'] = false;
        }

        if (!isset($style['stretch'])) {
            $style['stretch'] = $givenwidth;
        }
        $stretch = (bool) $style['stretch'];

        // The human-readable label is rendered with its own font: legacy only
        // switches font when both the text flag and a font name are present.
        $hastext = isset($style['text']) && (bool) $style['text'];
        $fontsizept = 0.0;
        $savefamily = $this->fontfamily;
        $savestyle = $this->fontstyle;
        $savesize = $this->fontsizept;
        $labelfont = '';
        if ($hastext && isset($style['font']) && is_string($style['font']) && $style['font'] !== '') {
            $labelfont = strtolower($style['font']);
            $fontsizept = isset($style['fontsize']) && is_numeric($style['fontsize'])
                ? (float) $style['fontsize']
                : 0.0;
            $this->setFont($labelfont, '', $fontsizept, '', 'default', false);
        }
        $stretchtext = isset($style['stretchtext']) ? (int) $style['stretchtext'] : 4;

        // Box width.
        if ($givenwidth) {
            $boxw = $wparam;
        } else {
            $boxw = $this->rtlmode ? max(0.0, $posx - $this->lmargin) : $this->getPageWidth() - $this->rmargin - $posx;
        }

        // Horizontal/vertical padding ('auto' = 10*(w/(maxw+20))).
        if (!isset($style['padding'])) {
            $padding = 0.0;
        } elseif ($style['padding'] === 'auto') {
            $padding = 10 * ($boxw / ($maxw + 20));
        } else {
            $padding = (float) $style['padding'];
        }

        if (!isset($style['hpadding'])) {
            $hpadding = $padding;
        } elseif ($style['hpadding'] === 'auto') {
            $hpadding = 10 * ($boxw / ($maxw + 20));
        } else {
            $hpadding = (float) $style['hpadding'];
        }

        if (!isset($style['vpadding'])) {
            $vpadding = $padding;
        } elseif ($style['vpadding'] === 'auto') {
            $vpadding = $hpadding / 2;
        } else {
            $vpadding = (float) $style['vpadding'];
        }

        // Single-bar width.
        $maxxres = ($boxw - (2 * $hpadding)) / $maxw;
        if ($stretch) {
            $xres = $maxxres;
        } else {
            if ($xres > $maxxres) {
                $xres = $maxxres;
            }

            if (
                isset($style['padding']) && $style['padding'] === 'auto'
                || isset($style['hpadding']) && $style['hpadding'] === 'auto'
            ) {
                $hpadding = 10 * $xres;
                if (isset($style['vpadding']) && $style['vpadding'] === 'auto') {
                    $vpadding = $hpadding / 2;
                }
            }
        }

        $barw = $maxw * $xres;
        if ($fitwidth) {
            $wold = $boxw;
            $boxw = $barw + (2 * $hpadding);
            $cellfit = isset($style['cellfitalign']) && is_string($style['cellfitalign']) ? $style['cellfitalign'] : '';
            if ($cellfit === 'L' && $this->rtlmode) {
                $posx -= $wold - $boxw;
            } elseif ($cellfit === 'R' && !$this->rtlmode) {
                $posx += $wold - $boxw;
            } elseif ($cellfit === 'C') {
                $posx += $this->rtlmode ? -(($wold - $boxw) / 2) : ($wold - $boxw) / 2;
            }
        }

        // The label height and the bars share the requested box height.
        $textheight = $this->getCellHeight($fontsizept / $this->kratio);
        if ($height === null || (string) $height === '' || (float) $height <= 0) {
            $boxh = ($barw / 3) + (2 * $vpadding) + $textheight;
        } else {
            $boxh = (float) $height;
        }

        $barh = $boxh - $textheight - (2 * $vpadding);
        if ($barh <= 0) {
            if ($textheight > $boxh) {
                $fontsizept = ($boxh * $this->kratio) / (4 * $this->cellheightratio);
                $this->setFont(
                    $labelfont !== '' ? $labelfont : $this->fontfamily,
                    '',
                    $fontsizept,
                    '',
                    'default',
                    false,
                );
                $textheight = $this->getCellHeight($fontsizept / $this->kratio);
            }

            if ($vpadding > 0) {
                $vpadding = ($boxh - $textheight) / 4;
            }

            $barh = $boxh - $textheight - (2 * $vpadding);
        }

        // Move the whole barcode to the next page when it would not fit
        // (legacy does this through fitBlock()).
        $drawy = $this->fitBarcodeBlock($posx, $posy, $boxh);

        // Box position then in-box bar alignment.
        $xposrect = $this->barcodeBoxX($position, $posx, $boxw);
        $alignin = isset($style['align']) && is_string($style['align']) ? strtoupper($style['align']) : 'C';
        if ($alignin === 'L') {
            $barx = $xposrect + $hpadding;
        } elseif ($alignin === 'R') {
            $barx = $xposrect + ($boxw - $barw) - $hpadding;
        } else {
            $barx = $xposrect + (($boxw - $barw) / 2);
        }

        $out = $this->barcodeBox($xposrect, $drawy, $boxw, $boxh, $style);
        try {
            $out .= $eng->getBarcode(
                $type,
                $code,
                $barx,
                $drawy + $vpadding,
                (int) round($barw),
                (int) round($barh),
                [0, 0, 0, 0],
                ['lineColor' => $fgspec, 'fillColor' => $fgspec, 'lineWidth' => 0.0],
            );
        } catch (\Com\Tecnick\Barcode\Exception) {
            $this->setFont($savefamily, $savestyle, $savesize, '', 'default', false);
            return;
        }
        $this->emitToPage($out);

        if ($hastext) {
            $label =
                isset($style['label']) && is_string($style['label']) && $style['label'] !== ''
                    ? $style['label']
                    : $code;
            if ((float) $this->GetStringWidth($label) > $barw) {
                // Force horizontal scaling so an over-long label still fits.
                $stretchtext = 2;
            }

            $savepadding = $this->cellpadding;
            $this->posx = $barx;
            $this->posy = $drawy + $vpadding + $barh;
            $this->setCellPadding(0);
            $this->Cell($barw, 0, $label, 0, 0, 'C', false, '', $stretchtext, false, 'T', 'T');
            $this->cellpadding = $savepadding;
        }

        $this->setFont($savefamily, $savestyle, $savesize, '', 'default', false);
        $this->advanceAfterBarcode($align, $position, $posx, $drawy, $boxw, $boxh);
    }

    /**
     * Render a 2D barcode with the legacy geometry: the barcode cells are
     * inset inside the requested box by the (auto) padding quiet-zone and the
     * aspect ratio is preserved unless $distort is set. Mirrors write2DBarcode().
     *
     * @param array<array-key, mixed> $style
     * @throws \Exception
     */
    protected function drawBarcode2D(
        string $type,
        string $code,
        float $posx,
        float $posy,
        mixed $width,
        mixed $height,
        array $style,
        string $align,
        int $cols,
        int $rows,
        string $fgspec,
        bool $distort,
    ): void {
        $eng = $this->engine();

        // Padding in barcode modules ('auto' = 4 modules).
        if (!isset($style['padding'])) {
            $padding = 0.0;
        } elseif ($style['padding'] === 'auto') {
            $padding = 4.0;
        } else {
            $padding = (float) $style['padding'];
        }

        if (!isset($style['hpadding'])) {
            $hpadding = $padding;
        } elseif ($style['hpadding'] === 'auto') {
            $hpadding = 4.0;
        } else {
            $hpadding = (float) $style['hpadding'];
        }

        if (!isset($style['vpadding'])) {
            $vpadding = $padding;
        } elseif ($style['vpadding'] === 'auto') {
            $vpadding = 4.0;
        } else {
            $vpadding = (float) $style['vpadding'];
        }

        $hpad = 2 * $hpadding;
        $vpad = 2 * $vpadding;

        $mw = isset($style['module_width']) && (float) $style['module_width'] > 0
            ? (float) $style['module_width']
            : 1.0;
        $mh = isset($style['module_height']) && (float) $style['module_height'] > 0
            ? (float) $style['module_height']
            : 1.0;

        $position = isset($style['position']) && is_string($style['position']) ? strtoupper($style['position']) : '';

        $maxw = $this->rtlmode ? max(0.0, $posx - $this->lmargin) : $this->getPageWidth() - $this->rmargin - $posx;
        $maxh = $this->getPageHeight() - $this->tmargin - $this->bmargin;
        $ratioHW = (($rows * $mh) + $hpad) / (($cols * $mw) + $vpad);
        $ratioWH = (($cols * $mw) + $vpad) / (($rows * $mh) + $hpad);
        if (!$distort) {
            if (($maxw * $ratioHW) > $maxh) {
                $maxw = $maxh * $ratioWH;
            }

            if (($maxh * $ratioWH) > $maxw) {
                $maxh = $maxw * $ratioHW;
            }
        }

        $boxw = $width === null || (string) $width === '' ? 0.0 : (float) $width;
        $boxh = $height === null || (string) $height === '' ? 0.0 : (float) $height;
        if ($boxw > $maxw) {
            $boxw = $maxw;
        }

        if ($boxh > $maxh) {
            $boxh = $maxh;
        }

        if ($boxw <= 0 && $boxh <= 0) {
            $boxw = ($cols + $hpad) * ($mw / $this->kratio);
            $boxh = ($rows + $vpad) * ($mh / $this->kratio);
        } elseif ($boxw <= 0) {
            $boxw = $boxh * $ratioWH;
        } elseif ($boxh <= 0) {
            $boxh = $boxw * $ratioHW;
        }

        // Barcode size excluding padding and single-cell dimensions.
        $bw = ($boxw * $cols) / ($cols + $hpad);
        $bh = ($boxh * $rows) / ($rows + $vpad);
        $cw = $bw / $cols;
        $ch = $bh / $rows;
        if (!$distort) {
            if (($cw / $ch) > ($mw / $mh)) {
                $cw = ($ch * $mw) / $mh;
                $bw = $cw * $cols;
                $hpadding = ($boxw - $bw) / (2 * $cw);
            } else {
                $ch = ($cw * $mh) / $mw;
                $bh = $ch * $rows;
                $vpadding = ($boxh - $bh) / (2 * $ch);
            }
        }

        $drawy = $this->fitBarcodeBlock($posx, $posy, $boxh);
        $xpos = $this->barcodeBoxX($position, $posx, $boxw);
        $xstart = $xpos + ($hpadding * $cw);
        $ystart = $drawy + ($vpadding * $ch);

        $out = $this->barcodeBox($xpos, $drawy, $boxw, $boxh, $style);
        try {
            $out .= $eng->getBarcode(
                $type,
                $code,
                $xstart,
                $ystart,
                (int) round($bw),
                (int) round($bh),
                [0, 0, 0, 0],
                ['lineColor' => $fgspec, 'fillColor' => $fgspec, 'lineWidth' => 0.0],
            );
        } catch (\Com\Tecnick\Barcode\Exception) {
            return;
        }
        $this->emitToPage($out);

        $this->advanceAfterBarcode($align, $position, $posx, $drawy, $boxw, $boxh);
    }

    /**
     * Move to the next page when a barcode of the given height would not fit
     * below the current Y, preserving the abscissa. Returns the Y to draw at.
     *
     * @throws \Exception
     */
    protected function fitBarcodeBlock(float $posx, float $posy, float $boxh): float
    {
        if (!$this->canBreakPage()) {
            return $posy;
        }

        if (($posy + $boxh - ($this->getPageHeight() - $this->bmargin)) <= 0.0001) {
            return $posy;
        }

        if (!$this->AcceptPageBreak()) {
            return $posy;
        }

        if ($this->advanceToNextColumn()) {
            return $this->posy;
        }

        $this->AddPage($this->curorientation);
        if ($this->getNumberOfColumns() < 2) {
            $this->posx = $posx;
        }

        return $this->posy;
    }

    /**
     * Left edge of a barcode box for the legacy position keyword (L/C/R or
     * the current abscissa for the empty/default case).
     *
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function barcodeBoxX(string $position, float $posx, float $boxw): float
    {
        if ($position === 'L') {
            return $this->lmargin;
        }

        if ($position === 'C') {
            return ($this->getPageWidth() + $this->lmargin - $this->rmargin - $boxw) / 2;
        }

        if ($position === 'R') {
            return $this->getPageWidth() - $this->rmargin - $boxw;
        }

        return $this->rtlmode ? $posx - $boxw : $posx;
    }

    /**
     * Background fill and/or border around a barcode box.
     *
     * @param array<array-key, mixed> $style
     * @throws \RuntimeException
     */
    protected function barcodeBox(float $x, float $y, float $w, float $h, array $style): string
    {
        $border = isset($style['border']) && (bool) $style['border'];
        $out = '';
        if (isset($style['bgcolor']) && is_array($style['bgcolor'])) {
            $out .= $this->engine()->graph->getBasicRect($x, $y, $w, $h, 'f', [
                'lineWidth' => 0.0,
                'fillColor' => $this->colorSpecFromLegacy($style['bgcolor']),
            ]);
        }

        if ($border) {
            $out .= $this->engine()->graph->getBasicRect($x, $y, $w, $h, 'S', $this->currentLineStyle());
        }

        return $out;
    }

    /**
     * Advance the cursor next to a drawn barcode for the legacy $align value.
     *
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function advanceAfterBarcode(
        string $align,
        string $position,
        float $posx,
        float $drawy,
        float $boxw,
        float $boxh,
    ): void {
        $xpos = $this->barcodeBoxX($position, $posx, $boxw);
        $imgrbx = $this->rtlmode ? $xpos : $xpos + $boxw;
        $imgrby = $drawy + $boxh;
        switch (strtoupper($align)) {
            case 'T':
                $this->posy = $drawy;
                $this->posx = $imgrbx;
                break;
            case 'M':
                $this->posy = $drawy + round($boxh / 2);
                $this->posx = $imgrbx;
                break;
            case 'B':
                $this->posy = $imgrby;
                $this->posx = $imgrbx;
                break;
            case 'N':
                $this->setY($imgrby);
                break;
            default:
                break;
        }
    }

    // ===================================================================
    // State accessors.
    // ===================================================================

    public function getMargins()
    {
        return [
            'left' => $this->lmargin,
            'top' => $this->tmargin,
            'right' => $this->rmargin,
            'bottom' => $this->bmargin,
            'header' => $this->headermargin,
            'footer' => $this->footermargin,
            'cell' => $this->cellpadding,
            'padding_left' => $this->cellpadding['L'],
            'padding_top' => $this->cellpadding['T'],
            'padding_right' => $this->cellpadding['R'],
            'padding_bottom' => $this->cellpadding['B'],
        ];
    }

    public function getOriginalMargins()
    {
        return [
            'left' => $this->orig_lmargin,
            'right' => $this->orig_rmargin,
        ];
    }

    /**
     * @return float Current font size in user units.
     */
    public function getFontSize()
    {
        return $this->fontsizept / $this->kratio;
    }

    /**
     * @return float Current font size in points.
     */
    public function getFontSizePt()
    {
        return $this->fontsizept;
    }

    /**
     * @return string Current font family.
     */
    public function getFontFamily()
    {
        return $this->fontfamily;
    }

    /**
     * @return string Current font style letters including decorations.
     */
    public function getFontStyle()
    {
        return $this->fontstyle . implode('', array_keys(array_filter($this->fontdecor)));
    }

    // ===================================================================
    // HTML rendering.
    // ===================================================================

    /**
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function fixHTMLCode($html, $default_css = '', $tagvs = null, $tidy_options = null)
    {
        return $this->engine()->tidyHTML((string) $html, (string) $default_css);
    }

    public function getCSSPadding($csspadding, $width = 0)
    {
        return $this->cssBoxValues((string) $csspadding, (float) $width);
    }

    public function getCSSMargin($cssmargin, $width = 0)
    {
        return $this->cssBoxValues((string) $cssmargin, (float) $width);
    }

    public function getCSSBorderMargin($cssbspace, $width = 0)
    {
        return $this->cssBoxValues((string) $cssbspace, (float) $width);
    }

    /**
     * Parse a CSS shorthand box value (top right bottom left) into user units.
     *
     * @return array{T: float, R: float, B: float, L: float}
     */
    protected function cssBoxValues(string $css, float $width): array
    {
        $parts = preg_split('/[\s]+/', trim($css));
        /** @var list<float> $values */
        $values = [];
        foreach (is_array($parts) ? $parts : [] as $part) {
            if ($part === '') {
                continue;
            }

            $values[] = (float) $this->getHTMLUnitToUnits($part, $width, 'px', false);
        }

        $top = $values[0] ?? 0.0;
        $right = $values[1] ?? $top;
        $bottom = $values[2] ?? $top;
        $left = $values[3] ?? $right;
        return ['T' => $top, 'R' => $right, 'B' => $bottom, 'L' => $left];
    }

    public function getHTMLFontUnits($val, $refsize = 12, $parent_size = 12, $defaultunit = 'pt')
    {
        $refsize = (float) $refsize;
        $parent = (float) $parent_size;
        $val = is_string($val) ? trim(strtolower($val)) : $val;
        $named = [
            'xx-small' => $refsize - 4,
            'x-small' => $refsize - 3,
            'small' => $refsize - 2,
            'medium' => $refsize,
            'large' => $refsize + 2,
            'x-large' => $refsize + 4,
            'xx-large' => $refsize + 6,
            'smaller' => $parent - 3,
            'larger' => $parent + 3,
        ];
        if (is_string($val) && isset($named[$val])) {
            return $named[$val];
        }

        return (float) $this->getHTMLUnitToUnits($val, $parent, (string) $defaultunit, true);
    }

    public function serializeTCPDFtag($method, $params = [])
    {
        $data = ['m' => (string) $method, 'p' => is_array($params) ? $params : []];
        $encoded = json_encode($data);
        return 'tcpdf://' . ($encoded === false ? '' : urlencode($encoded));
    }

    /**
     * @throws \Throwable
     */
    public function writeHTMLCell(
        $w,
        $h,
        $x,
        $y,
        $html = '',
        $border = 0,
        $ln = 0,
        $fill = false,
        $reseth = true,
        $align = '',
        $autopadding = true,
    ) {
        if ($this->docstate !== 2) {
            $this->Error('Unable to write HTML: no page has been started');
            return;
        }

        if (
            defined('K_TCPDF_CALLS_IN_HTML')
            && constant('K_TCPDF_CALLS_IN_HTML')
            && preg_match('/<tcpdf\b/i', (string) $html) === 1
        ) {
            $this->writeHtmlWithTcpdfTags(
                (string) $html,
                $w,
                $h,
                $x,
                $y,
                $border,
                $ln,
                $fill,
                $reseth,
                $align,
                $autopadding,
            );
            return;
        }

        $eng = $this->engine();
        $posx = $x === null || (string) $x === '' || (float) $x === 0.0 ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' || (float) $y === 0.0 ? $this->posy : (float) $y;

        // Legacy no-write page regions: build the engine banded writable
        // regions for the current page so the HTML hugs the obstacles band by
        // band (then continues full width on the next page). This turns the
        // page multi-region, so the inColumnMode() width branch below applies.
        if ($this->nowriteareas !== []) {
            $this->applyNoWriteRegionsForFlow($this->noWriteBandFromHtml((string) $html), $posy);
        }

        $width = (float) $w;
        if ($width <= 0) {
            if ($this->inColumnMode()) {
                // The HTML fragment flows inside the current column region
                // (and through the following columns/pages inside the call).
                $region = $eng->page->getRegion();
                $width = $this->rtlmode
                    ? max(0.0, $posx - $region['RX'])
                    : max(0.0, $region['RX'] + $region['RW'] - $posx);
            } elseif ($this->rtlmode) {
                $width = max(0.0, $posx - $this->lmargin);
            } else {
                $width = $this->getPageWidth() - $this->rmargin - $posx;
            }
        }

        // In RTL the cursor X marks the cell's right edge; the engine places
        // every cell from its left edge, so shift left by the cell width.
        $cellx = $this->rtlmode ? $posx - $width : $posx;

        $sidestyles = $this->stylesFromLegacyBorder($border, (bool) $fill);
        $styles = $this->completeSideStyles($sidestyles);

        $html = $this->normalizeHtmlMarkup((string) $html);
        $alignmap = ['L' => 'left', 'C' => 'center', 'R' => 'right', 'J' => 'justify'];
        $align = strtoupper((string) $align);
        // The legacy renderer spaces HTML lines by fontsize * cell height
        // ratio (the engine default is the font metric line height).
        $wrapstyle = 'line-height:' . $this->cellheightratio . ';';
        if (isset($alignmap[$align])) {
            // The legacy align parameter sets the default block alignment.
            $wrapstyle .= 'text-align:' . $alignmap[$align] . ';';
        }

        if ($this->textcolorspec !== 'black') {
            // The engine resolves HTML text color from the CSS cascade
            // (root default black): the legacy current text color must be
            // injected as the root color of the fragment.
            $wrapstyle .= 'color:' . $this->textcolorspec . ';';
        }

        // The div is left unclosed on purpose: the parser auto-closes
        // it at the end of the fragment, and an explicit closing tag
        // would add a trailing block advance to the cell frame.
        $html = '<div style="' . $wrapstyle . '">' . $html;

        $this->emitToPage($eng->color->getPdfFillColor($this->textcolorspec));
        $startpid = $this->getPage();
        $starty = $posy;
        $contentstart = $this->pageContentCount($startpid - 1);
        $cell = $this->htmlnopadding ? \Com\Tecnick\Pdf\Tcpdf::ZEROCELL : null;
        $bboxwindow = $this->facadeEngine()->openCellBBoxWindow();
        try {
            $eng->addHTMLCell($html, $cellx, $posy, $width, (float) $h, $cell, $styles);
        } finally {
            $bboxtally = $this->facadeEngine()->closeCellBBoxWindow($bboxwindow);
        }

        if ($this->textshadow['enabled']) {
            // First page: only the chunks appended by this call; pages
            // created by the flow shadow their full content.
            $this->applyHtmlTextShadow($startpid - 1, $contentstart);
            for ($pid = $startpid; $pid < $this->getPage(); $pid++) {
                $this->applyHtmlTextShadow($pid, 0);
            }
        }

        // The bottom of the rendered content is the lowest cell box pushed
        // during this call (the last box may belong to a higher fragment,
        // e.g. a middle table column). When the content flowed to a new
        // page, only the boxes there matter, but the simple maximum works
        // because Y restarts from the top region edge on the new page.
        $bottom = $this->getPage() > $startpid
            ? $eng->getLastCellBBox()['y'] + $eng->getLastCellBBox()['h']
            : max($posy, $bboxtally['bottom']);
        if ($this->getPage() === $startpid) {
            // Image-only fragments (e.g. images in table cells) push no
            // cell bounding boxes: account for their placement directly.
            $bottom = $this->pageContentImageBottom($startpid - 1, $contentstart, $bottom);
        }
        if ((float) $h <= 0 && $this->getPage() > $startpid) {
            // The engine skips the cell box when auto-height content flows
            // to more pages: replicate the legacy per-page frame segments
            // (a no-op when neither border nor fill is requested).
            $this->drawHtmlCellSegments(
                $cellx,
                $width,
                $starty,
                $bottom,
                $startpid,
                $contentstart,
                $sidestyles,
                (bool) $fill,
            );
        }

        if (preg_match('/<\/(table|thead)>\s*$/i', $html) === 1) {
            // The legacy renderer leaves one line of space after a closing
            // table (the engine tracks only the table cells themselves).
            $bottom += $this->getCellHeight($this->getFontSize(), false);
        }
        $ln = (int) $ln;
        if ($ln === 1) {
            $this->posy = $bottom;
            if ($this->inColumnMode()) {
                $region = $eng->page->getRegion();
                $this->posx = $this->rtlmode ? $region['RX'] + $region['RW'] : $region['RX'];
            } else {
                $this->posx = $this->rtlmode ? $this->getPageWidth() - $this->rmargin : $this->lmargin;
            }
        } elseif ($ln === 2) {
            $this->posy = $bottom;
        } elseif ($ln === 0) {
            // Legacy semantics: return to the page and vertical position
            // where the cell started, with X advanced past the cell.
            if ($this->getPage() > $startpid) {
                $this->setPage($startpid);
            }

            $this->posy = $starty;
            $this->posx = $this->rtlmode ? $cellx : $posx + $width;
        }
    }

    /**
     * Make a relative local markup resource path absolute.
     */
    protected function absolutizeMarkupPath(string $source): string
    {
        if (
            $source === ''
            || str_contains($source, '://')
            || str_starts_with($source, '@')
            || str_starts_with($source, '/')
        ) {
            return $source;
        }

        $real = realpath($source);
        return $real === false ? $source : $real;
    }

    /**
     * Flatten styled inline elements that contain nested inline children.
     *
     * The engine HTML renderer only paints inline backgrounds for fragments
     * whose element sits directly below the block element; nested children
     * (e.g. <a style="background-color:red">x<span>y</span></a>) lose the
     * background. The parent is therefore split into a sequence of sibling
     * elements of the same tag, each carrying the merged style and the
     * original attributes (links keep working, one annotation per piece).
     */
    protected function inheritHtmlBackgrounds(string $html): string
    {
        $result = preg_replace_callback(
            '/<(a|span|font)(\s[^>]*?)?\s+style="([^"]*background-color[^"]*)"([^>]*)>(.*?)<\/\1>/is',
            static function (array $match): string {
                $tag = $match[1];
                $attrs = trim(($match[2]) . ($match[4]));
                $parentstyle = rtrim($match[3], '; ');
                $inner = $match[5];
                if (stripos($inner, '<') === false) {
                    // No nested elements: the engine handles this directly.
                    return $match[0];
                }

                // Tokenize into text runs and one-level inline children.
                $tokens = preg_split(
                    '/(<(?:span|font|b|i|u|em|strong)\b[^>]*>.*?<\/(?:span|font|b|i|u|em|strong)>)/is',
                    $inner,
                    -1,
                    PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
                );
                if (!is_array($tokens)) {
                    return $match[0];
                }

                $out = '';
                foreach ($tokens as $token) {
                    $childmatch = [];
                    if (
                        preg_match('/^<(span|font|b|i|u|em|strong)\b([^>]*)>(.*)<\/\1>$/is', $token, $childmatch) === 1
                    ) {
                        $childinner = $childmatch[3];
                        if (stripos($childinner, '<') !== false) {
                            // Deeper nesting: bail out, keep the original markup.
                            return $match[0];
                        }

                        $childattrs = $childmatch[2];
                        $stylematch = [];
                        $childstyle = preg_match('/style="([^"]*)"/i', $childattrs, $stylematch) === 1
                            ? $stylematch[1]
                            : '';
                        $merged = $parentstyle . ';' . $childstyle;
                        $out .=
                            '<'
                            . $tag
                            . ($attrs !== '' ? ' ' . $attrs : '')
                            . ' style="'
                            . $merged
                            . '">'
                            . $childinner
                            . '</'
                            . $tag
                            . '>';
                        continue;
                    }

                    if (str_contains($token, '<')) {
                        // Unhandled markup (images, breaks, ...): bail out.
                        return $match[0];
                    }

                    $out .=
                        '<'
                        . $tag
                        . ($attrs !== '' ? ' ' . $attrs : '')
                        . ' style="'
                        . $parentstyle
                        . '">'
                        . $token
                        . '</'
                        . $tag
                        . '>';
                }

                return $out;
            },
            $html,
        );

        return $result === null ? $html : $result;
    }

    /**
     * Adapt legacy-tolerant HTML markup for the stricter engine renderer.
     *
     * Applied transformations:
     * - styled inline parents with nested children are flattened so the
     *   engine paints their backgrounds (see inheritHtmlBackgrounds());
     * - vertical align attributes are dropped from images inside tables
     *   (the engine would overlap them with the row borders);
     * - '@'-prefixed base64 image data is rewritten to a data URI;
     * - relative local image paths are made absolute (src attributes and
     *   CSS url() values);
     * - the legacy list-style-type:img|type|width|height|path bullet syntax
     *   becomes list-style-image:url(path);
     * - legacy page anchors (href="#N" or href="#N,y") become internal
     *   links to page N at ordinate y;
     * - malformed hexadecimal colors, silently coerced by the legacy
     *   parser, are repaired in color attributes and CSS declaration values
     *   (see repairHtmlColors()).
     * @throws \RuntimeException
     */
    protected function normalizeHtmlMarkup(string $html): string
    {
        $html = $this->inheritHtmlBackgrounds($html);

        // Make relative local image sources absolute: the engine file
        // helper only accepts paths under its allowed roots. Covers both
        // src attributes and CSS url(...) values (e.g. list-style-image).
        // The engine positions middle/bottom aligned images relative to the
        // first line box of the table cell, which makes them overlap the
        // row borders; the vertical-align attribute is dropped inside
        // tables so the rows contain their images (legacy parity for the
        // dominant image-only cell case). The markup is split on table tags
        // (odd indexes) to track the nesting depth in a single linear pass.
        $parts = preg_split('#(</?table\b[^>]*>)#i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (is_array($parts)) {
            $depth = 0;
            foreach ($parts as $idx => $part) {
                if (($idx % 2) === 1) {
                    $depth = ($part[1] ?? '') === '/' ? max(0, $depth - 1) : $depth + 1;
                    continue;
                }

                if ($depth > 0 && stripos($part, '<img') !== false) {
                    $parts[$idx] =
                        preg_replace('/(<img\b[^>]*?)\s+align="(top|middle|bottom|absmiddle|baseline)"/i', '$1', $part)
                        ?? $part;
                }
            }

            $html = implode('', $parts);
        }

        // Legacy HTML images may carry base64 data with an '@' prefix; the
        // engine expects raw bytes after '@' but decodes data URIs.
        $html = (string) preg_replace('/src="@([A-Za-z0-9+\/=]+)"/', 'src="data:image/any;base64,$1"', $html);
        $html = (string) preg_replace_callback(
            '/(src=["\'])([^"\']+)(["\'])/',
            fn(array $match): string => (
                ($match[1]) . $this->absolutizeMarkupPath($match[2]) . ($match[3])
            ),
            $html,
        );
        $html = (string) preg_replace_callback(
            '/url\(["\']?([^"\')]+)["\']?\)/',
            fn(array $match): string => "url('" . $this->absolutizeMarkupPath($match[1]) . "')",
            $html,
        );
        // Legacy custom list bullet syntax: list-style-type:img|ext|w|h|path.
        // The engine sizes list-style-image markers from the font size.
        $html = (string) preg_replace_callback(
            '/list-style-type\s*:\s*img\|[^|;"\'<>]*\|[^|;"\'<>]*\|[^|;"\'<>]*\|([^;"\'<>]+)/i',
            fn(array $match): string => (
                "list-style-image:url('" . $this->absolutizeMarkupPath(trim($match[1])) . "')"
            ),
            $html,
        );

        // Legacy page anchors: href="#N" or href="#N,y" link to page N at ordinate y.
        $html = (string) preg_replace_callback(
            '/(\bhref\s*=\s*["\'])#([0-9]+)(?:,([0-9]*\.?[0-9]*))?(["\'])/i',
            /** @throws \RuntimeException */ fn(array $match): string => (
                ($match[1])
                . $this->engine()->addInternalLink(max(0, (int) ($match[2]) - 1), (float) ($match[3]))
                . ($match[4])
            ),
            $html,
        );

        return self::repairHtmlColors($html);
    }

    /**
     * Repair malformed hexadecimal colors in the attributes whose name ends in
     * "color" (color, bgcolor, bordercolor), in style attributes and in style
     * blocks. Text content and other attributes are left unchanged.
     */
    protected static function repairHtmlColors(string $html): string
    {
        $result = preg_replace_callback(
            '/<style\b[^>]*>.*?<\/style\s*>|<[a-zA-Z][^>]*>/is',
            static function (array $match): string {
                $part = $match[0];
                if (strncasecmp($part, '<style', 6) === 0) {
                    return self::repairCssColors($part);
                }

                $part = (string) preg_replace_callback(
                    '/(\bstyle\s*=\s*)(?:"([^"]*)"|\'([^\']*)\')/i',
                    static fn(array $style): string => isset($style[3])
                        ? ($style[1]) . "'" . self::repairCssColors($style[3]) . "'"
                        : ($style[1]) . '"' . self::repairCssColors($style[2] ?? '') . '"',
                    $part,
                );

                return (string) preg_replace_callback(
                    '/(\b[a-z-]*color\s*=\s*["\']?)#([0-9a-zA-Z]+)\b/i',
                    static fn(array $attr): string => ($attr[1]) . self::repairHexColor('#' . ($attr[2])),
                    $part,
                );
            },
            $html,
        );

        return $result === null ? $html : $result;
    }

    /**
     * Repair malformed hexadecimal colors in the declaration values of a CSS
     * text. Selectors, quoted strings and numeric character references are
     * left unchanged.
     */
    protected static function repairCssColors(string $css): string
    {
        return (string) preg_replace_callback(
            '/:[^;{}]*+(?=[;}]|$)/',
            static fn(array $decl): string => (string) preg_replace_callback(
                '/"[^"]*"|\'[^\']*\'|(?<!&)#[0-9a-zA-Z]+\b/',
                static fn(array $item): string => str_starts_with($item[0], '#')
                    ? self::repairHexColor($item[0])
                    : $item[0],
                $decl[0],
            ),
            $css,
        );
    }

    /**
     * Repair a malformed hexadecimal color token ('#' followed by letters and
     * digits). Valid 3, 4, 6 or 8 digit values and tokens that cannot be a
     * mistyped color are returned unchanged; the others are padded or
     * truncated to 6 digits.
     */
    protected static function repairHexColor(string $color): string
    {
        $hex = strtolower(substr($color, 1));
        if (preg_match('/^[0-9a-f]+$/', $hex) === 1 && in_array(strlen($hex), [3, 4, 6, 8], true)) {
            return $color;
        }

        // Only repair tokens that could be a mistyped hex color: every
        // character must be a hex digit or a digit-look-alike letter
        // (l/o/i/s/g/z). This leaves CSS id selectors (#second), link
        // fragments (#chapter2) and placeholders (#TOC_...) untouched
        // instead of mangling them into bogus colors.
        if (preg_match('/^[0-9a-fgilosz]+$/', $hex) !== 1) {
            return $color;
        }

        // Map common look-alike characters, drop the rest.
        $hex = strtr($hex, ['l' => '1', 'o' => '0', 'i' => '1', 's' => '5', 'g' => '6', 'z' => '2']);
        $hex = (string) preg_replace('/[^0-9a-f]/', '', $hex);

        return '#' . substr(str_pad($hex, 6, '0'), 0, 6);
    }

    /**
     * Render HTML containing embedded <tcpdf data="..."/> method calls
     * (K_TCPDF_CALLS_IN_HTML feature): the markup is split at the tags,
     * each chunk is rendered normally and each tag invokes the serialized
     * facade method when listed in K_ALLOWED_TCPDF_TAGS.
     *
     * @throws \Throwable
     */
    protected function writeHtmlWithTcpdfTags(
        string $html,
        mixed $w,
        mixed $h,
        mixed $x,
        mixed $y,
        mixed $border,
        mixed $ln,
        mixed $fill,
        mixed $reseth,
        mixed $align,
        mixed $autopadding,
    ): void {
        $parts = preg_split('/<tcpdf\s+([^>]*?)\/?>/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return;
        }

        $allowed = defined('K_ALLOWED_TCPDF_TAGS') ? K_ALLOWED_TCPDF_TAGS : '';
        foreach ($parts as $idx => $part) {
            if (($idx % 2) === 0) {
                // HTML chunk.
                if (trim($part) !== '') {
                    $this->writeHTMLCell($w, $h, $x, $y, $part, $border, 1, $fill, $reseth, $align, $autopadding);
                    // Subsequent chunks continue from the cursor.
                    $x = '';
                    $y = '';
                }

                continue;
            }

            // Tag attributes: extract the serialized payload.
            $match = [];
            if (preg_match('/data="tcpdf:\/\/([^"]*)"/i', $part, $match) !== 1) {
                continue;
            }

            $payload = json_decode(urldecode($match[1]), true);
            if (!is_array($payload) || !isset($payload['m']) || !is_string($payload['m'])) {
                continue;
            }

            $method = $payload['m'];
            $params = isset($payload['p']) && is_array($payload['p']) ? array_values($payload['p']) : [];
            if (stripos($allowed, '|' . $method . '|') === false || !method_exists($this, $method)) {
                // Method not in the K_ALLOWED_TCPDF_TAGS whitelist.
                continue;
            }

            /** @var callable $callable */
            $callable = [$this, $method];
            call_user_func_array($callable, $params);
            $x = '';
            $y = '';
        }
    }

    /**
     * @throws \Throwable
     */
    public function writeHTML($html, $ln = true, $fill = false, $reseth = false, $cell = false, $align = '')
    {
        // Legacy renders the fragment without the current cell padding
        // unless $cell is requested (writeHTMLCell always applies it).
        $this->htmlnopadding = !(bool) $cell;
        try {
            $this->writeHTMLCell(0, 0, '', '', $html, 0, (bool) $ln ? 1 : 0, $fill, $reseth, $align, true);
        } finally {
            $this->htmlnopadding = false;
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function setLIsymbol($symbol = '!')
    {
        $this->engine()->setULLIDot((string) $symbol);
    }

    public function setBooklet($booklet = true, $inner = -1, $outer = -1)
    {
        $this->bookletmode = (bool) $booklet;
        if ((float) $inner >= 0) {
            $this->lmargin = (float) $inner;
        }

        if ((float) $outer >= 0) {
            $this->rmargin = (float) $outer;
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function setHtmlVSpace($tagvs)
    {
        $inputTagVSpace = $tagvs;
        $tagvs = [];
        foreach (is_array($inputTagVSpace) ? $inputTagVSpace : [] as $tag => $spaces) {
            if (!is_string($tag) || !is_array($spaces)) {
                continue;
            }

            foreach (array_values($spaces) as $idx => $space) {
                if (!is_array($space)) {
                    continue;
                }

                $entry = [];
                if (isset($space['h']) && is_numeric($space['h'])) {
                    $entry['h'] = (float) $space['h'];
                }

                if (isset($space['n']) && is_numeric($space['n'])) {
                    $entry['n'] = (int) $space['n'];
                }

                $tagvs[$tag][$idx] = $entry;
            }
        }

        $this->engine()->setHtmlVSpace($tagvs);
    }

    public function setListIndentWidth($width)
    {
        // List indentation is computed by the engine HTML renderer.
    }

    public function setOpenCell($isopen)
    {
        // Block continuation borders are handled by the engine HTML renderer.
    }

    public function setHtmlLinksStyle($color = [0, 0, 255], $fontstyle = 'U')
    {
        $this->htmllinkcolor = $this->legacyColorComponents($color, [0, 0, 255]);
        $this->htmllinkstyle = (string) $fontstyle;
    }

    public function getHTMLUnitToUnits($htmlval, $refsize = 1, $defaultunit = 'px', $points = false)
    {
        $supportedunits = ['%', 'em', 'ex', 'px', 'in', 'cm', 'mm', 'pc', 'pt'];
        $refsize = (float) $refsize;
        $unit = strtolower((string) $defaultunit);
        if (!in_array($unit, $supportedunits, true)) {
            $unit = 'px';
        }

        $value = 0.0;
        $htmlval = $htmlval;
        if (is_numeric($htmlval)) {
            $value = (float) $htmlval;
        } elseif (is_string($htmlval)) {
            $mat = [];
            if (preg_match('/^([0-9\.\-\+]+)[\s]*(%|em|ex|px|in|mm|cm|pc|pt)?$/', trim($htmlval), $mat) === 1) {
                $value = (float) ($mat[1]);
                if (isset($mat[2]) && in_array($mat[2], $supportedunits, true)) {
                    $unit = $mat[2];
                }
            }
        }

        $kfactor = (bool) $points ? 1.0 : $this->kratio;
        return match ($unit) {
            '%' => ($value * $refsize) / 100.0,
            'em' => $value * $refsize,
            'ex' => ($value * $refsize) / 2.0,
            'in' => ($value * 72.0) / $kfactor,
            'cm' => (($value * 72.0) / 2.54) / $kfactor,
            'mm' => (($value * 72.0) / 25.4) / $kfactor,
            'pc' => ($value * 12.0) / $kfactor,
            'pt' => $value / $kfactor,
            default => $value / ($this->imgscale * $kfactor),
        };
    }

    // ===================================================================
    // Page reordering (Stage 4).
    // ===================================================================

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function movePage($frompage, $topage)
    {
        $from = (int) $frompage;
        $target = (int) $topage;
        if ($from <= $target || $from < 1 || $from > $this->getNumPages()) {
            return false;
        }

        $this->engine()->page->move($from - 1, $target - 1);
        return true;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function deletePage($page)
    {
        $page = (int) $page;
        if ($page < 1 || $page > $this->getNumPages()) {
            return false;
        }

        $this->engine()->page->delete($page - 1);
        return true;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \Com\Tecnick\Unicode\Exception
     * @throws \RuntimeException
     */
    public function copyPage($page = 0)
    {
        $eng = $this->engine();
        $page = (int) $page;
        if ($page === 0) {
            $page = $this->getPage();
        }

        if ($page < 1 || $page > $this->getNumPages()) {
            return false;
        }

        $source = $eng->page->getPage($page - 1);
        $eng->addPage([
            'orientation' => $source['orientation'],
            'format' => $source['format'],
            'margin' => $source['margin'],
            'autobreak' => $source['autobreak'],
            'group' => (int) $source['group'],
            'content' => implode('', $source['content']),
        ]);
        return true;
    }

    /**
     * @throws \Exception
     */
    public function addTOC(
        $page = null,
        $numbersfont = '',
        $filler = '.',
        $toc_name = 'TOC',
        $style = '',
        $color = [0, 0, 0],
    ) {
        if ($this->docstate !== 2) {
            return;
        }

        $eng = $this->engine();
        $outlines = $this->engineOutlines();
        if ($outlines === []) {
            return;
        }

        $insertion = $page === null || (string) $page === '' ? 0 : (int) $page;
        $tocstart = $this->getPage();
        $filler = (string) $filler !== '' ? (string) $filler : '.';
        $numbersfont = (string) $numbersfont !== '' ? strtolower((string) $numbersfont) : $this->monospacedfont;
        $basefamily = $this->fontfamily;
        $basestyle = $this->getFontStyle();
        $basesize = $this->fontsizept;

        // Estimated number of TOC pages, used to adjust the printed page
        // numbers for the later relocation of the TOC itself.
        $rowheight = $this->getCellHeight($basesize / $this->kratio);
        $usable = $this->getPageHeight() - $this->tmargin - $this->bmargin;
        $ntoc = max(1, (int) ceil(((count($outlines) * $rowheight) + ($this->posy - $this->tmargin)) / $usable));

        $contentwidth = $this->getPageWidth() - $this->lmargin - $this->rmargin;
        $this->setTextColorArray($color);

        foreach ($outlines as $outline) {
            $level = max(0, (int) $outline['l']);
            $title = $outline['t'];
            $target = (int) $outline['p'];
            // Rows inherit the bookmark's own style and color when set.
            if (isset($outline['s']) && $outline['s'] !== '') {
                $rowstyle = $outline['s'];
            } else {
                $rowstyle = $level === 0 ? (string) $style : '';
            }
            if (isset($outline['c']) && $outline['c'] !== '') {
                $this->textcolorspec = $outline['c'];
            } else {
                $this->setTextColorArray($color);
            }
            $display = $target + 1;
            $targetadj = $target;
            if ($insertion > 0 && $display >= $insertion) {
                $display += $ntoc;
                $targetadj += $ntoc;
            }

            $indent = 4.0 * $level;
            $size = max(1.0, $basesize - $level);
            $this->setFont($basefamily, $rowstyle, $size);
            $rowy = $this->posy;
            $rowh = $this->getCellHeight($size / $this->kratio);
            $this->breakIfNeeded($rowh);
            $rowy = $this->posy;

            // Title with dot filler up to the page-number box.
            $numwidth = (4 * $size) / $this->kratio;
            $textwidth = $contentwidth - $indent - $numwidth;
            $titlewidth = (float) $this->GetStringWidth($title . ' ');
            $fillerwidth = max(0.1, (float) $this->GetStringWidth($filler));
            $padding = $this->cellpadding['L'] + $this->cellpadding['R'];
            $nfill = max(0, (int) floor(($textwidth - $padding - $titlewidth - 1) / $fillerwidth));
            $this->setX($this->lmargin + $indent);
            $this->Cell($textwidth, $rowh, $title . ' ' . str_repeat($filler, $nfill), 0, 0, 'L', false, '', 1);

            // Page number, right aligned with its own font.
            $this->setFont($numbersfont, '', $size);
            $this->Cell($numwidth, $rowh, (string) $display, 0, 1, 'R');

            // Link the whole row to the bookmark destination (the target
            // page index already accounts for the TOC relocation).
            $oid = $eng->setLink(
                $this->lmargin,
                $rowy,
                $contentwidth,
                $rowh,
                $eng->addInternalLink($targetadj, $outline['y']),
            );
            $this->attachAnnotRef($oid);
        }

        $this->setFont($basefamily, $basestyle, $basesize);

        if ($insertion > 0) {
            // Relocate the TOC pages and remap the bookmark page targets.
            $tocend = $this->getPage();
            $ntocreal = $tocend - $tocstart + 1;
            for ($idx = 0; $idx < $ntocreal; $idx++) {
                $eng->page->move($tocstart - 1 + $idx, $insertion - 1 + $idx);
            }

            foreach ($outlines as $key => $outline) {
                if ((int) $outline['p'] < ($insertion - 1)) {
                    continue;
                }

                $outlines[$key]['p'] = (int) $outline['p'] + $ntocreal;
            }

            $this->setEngineOutlines($outlines);
            $eng->page->setCurrentPage($insertion - 1 + $ntocreal - 1);
        }
    }

    /**
     * @throws \Exception
     */
    public function addHTMLTOC(
        $page = null,
        $toc_name = 'TOC',
        $templates = [],
        $correct_align = true,
        $style = '',
        $color = [0, 0, 0],
    ) {
        // Approximated with the engine bookmark-based TOC renderer
        // (HTML templates are not applied).
        $this->addTOC($page, '', '.', $toc_name, $style, $color);
    }

    public function startTransaction()
    {
        // Deep snapshot of the facade including the engine object graph.
        // The engine page context hook is a closure (not serializable):
        // detach it around the snapshot and rebind it on the copy.
        $this->transactionsnapshot = null;
        $hook = null;
        if ($this->eng instanceof CompatibilityEngine) {
            $hook = $this->eng->pagecontexthook;
            $this->eng->pagecontexthook = null;
        }

        try {
            $snapshot = unserialize(serialize($this), ['allowed_classes' => true]);
        } finally {
            if ($this->eng instanceof CompatibilityEngine) {
                $this->eng->pagecontexthook = $hook;
            }
        }

        if ($snapshot instanceof Tcpdf) {
            if ($snapshot->eng instanceof CompatibilityEngine) {
                $snapshot->eng->pagecontexthook = $snapshot->ambientPageContent(...);
            }

            $this->transactionsnapshot = $snapshot;
        }
    }

    public function commitTransaction()
    {
        $this->transactionsnapshot = null;
    }

    public function rollbackTransaction($self = false)
    {
        if (!$this->transactionsnapshot instanceof Tcpdf) {
            return $this;
        }

        $snapshot = $this->transactionsnapshot;
        if ((bool) $self) {
            foreach (get_object_vars($snapshot) as $prop => $value) {
                $prop = (string) $prop;
                if ($prop !== 'transactionsnapshot') {
                    // The snapshot restore copies every facade property back
                    // by name; the dynamic selector is inherent to the shim.
                    // @mago-expect analysis:string-member-selector
                    $this->{$prop} = $value;
                }
            }

            // The restored engine hook was bound to the snapshot facade:
            // rebind it to this (live) instance.
            if ($this->eng instanceof CompatibilityEngine) {
                $this->eng->pagecontexthook = $this->ambientPageContent(...);
            }

            return $this;
        }

        return $snapshot;
    }

    // ===================================================================
    // Columns (Stage 4).
    // ===================================================================

    /**
     * Legacy equal-column geometry: the requested width is capped to an even
     * share of the content width and the remainder becomes the inter-column
     * gutters.
     *
     * @return array<int, array{RX: float, RY: float, RW: float, RH: float}>
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function equalColumnRegions(int $numcols, float $width, float $ypos): array
    {
        $usable = $this->getPageWidth() - $this->orig_lmargin - $this->orig_rmargin;
        $maxwidth = $usable / $numcols;
        if ($width <= 0 || $width > $maxwidth) {
            $width = $maxwidth;
        }

        $space = ($usable - ($numcols * $width)) / ($numcols - 1);
        $regions = [];
        for ($idx = 0; $idx < $numcols; ++$idx) {
            $colpos = $idx * ($width + $space);
            $regions[] = [
                'RX' => $this->rtlmode
                    ? $this->getPageWidth() - $this->orig_rmargin - $colpos - $width
                    : $this->orig_lmargin + $colpos,
                'RY' => $ypos,
                'RW' => $width,
                'RH' => $this->getPageHeight() - $this->bmargin - $ypos,
            ];
        }

        return $regions;
    }

    /**
     * Replace the region (column) layout of the current engine page.
     *
     * The engine fixes the region list when a page is created and exposes no
     * mutator: the normalized region data (same normalization as the engine
     * page settings) is written directly into the page store.
     *
     * @param array<int, array{RX: float, RY: float, RW: float, RH: float}> $regions
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    protected function setEnginePageRegions(array $regions): void
    {
        if ($regions === []) {
            return;
        }

        $eng = $this->engine();
        $page = $eng->page->getPage();
        $pagewidth = $page['width'];
        $pageheight = $page['height'];
        $marginright = $page['margin']['PR'];
        $marginbottom = $page['margin']['CB'];
        $contentwidth = $pagewidth - $page['margin']['PL'] - $marginright;
        $contentheight = $pageheight - $page['margin']['CT'] - $marginbottom;

        $normalized = [];
        foreach ($regions as $region) {
            $normalized[] = [
                'RW' => min(max(0.0, $region['RW']), $contentwidth),
                'RX' => min(max(0.0, $region['RX']), $pagewidth - $marginright - $region['RW']),
                'RL' => $region['RX'] + $region['RW'],
                'RR' => $pagewidth - $region['RX'] - $region['RW'],
                'RH' => min(max(0.0, $region['RH']), $contentheight),
                'RY' => min(max(0.0, $region['RY']), $pageheight - $marginbottom - $region['RH']),
                'RT' => $region['RY'] + $region['RH'],
                'RB' => $pageheight - $region['RY'] - $region['RH'],
                'x' => $region['RX'],
                'y' => $region['RY'],
            ];
        }

        $prop = new \ReflectionProperty(\Com\Tecnick\Pdf\Page\Settings::class, 'page');
        $pages = $prop->getValue($eng->page);
        if (!is_array($pages)) {
            return;
        }

        /** @var array<int, array<string, mixed>> $pages */
        $pid = (int) $page['pid'];
        $pages[$pid]['region'] = $normalized;
        $pages[$pid]['columns'] = count($normalized);
        $pages[$pid]['currentRegion'] = 0;
        $prop->setValue($eng->page, $pages);
    }

    /**
     * True when the current page is split into multiple column regions.
     *
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function inColumnMode(): bool
    {
        return $this->docstate === 2 && count($this->engine()->page->getPage()['region']) > 1;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public function setEqualColumns($numcols = 0, $width = 0, $y = null)
    {
        $numcols = max(0, (int) $numcols);
        if ($numcols < 2) {
            $this->resetColumns();
            return;
        }

        $this->pagecolumns = $numcols;
        $this->pagecolumnwidth = (float) $width;
        $this->pageregions = [];
        if ($this->docstate !== 2) {
            return;
        }

        // Legacy: columns start at the given Y (default: the current Y) on
        // the current page and at the top margin on the following pages.
        $ypos = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        $this->setEnginePageRegions($this->equalColumnRegions($numcols, (float) $width, $ypos));
        $this->selectColumn(0);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public function resetColumns()
    {
        // Legacy: restore the original margins and disable column mode.
        $this->lmargin = $this->orig_lmargin;
        $this->rmargin = $this->orig_rmargin;
        $this->pagecolumns = 0;
        $this->pagecolumnwidth = 0.0;
        $this->pageregions = [];
        if ($this->docstate !== 2) {
            return;
        }

        $this->setEnginePageRegions([
            [
                'RX' => $this->lmargin,
                'RY' => $this->tmargin,
                'RW' => $this->getPageWidth() - $this->lmargin - $this->rmargin,
                'RH' => $this->getPageHeight() - $this->tmargin - $this->bmargin,
            ],
        ]);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public function setColumnsArray($columns)
    {
        $inputColumns = $columns;
        $columns = [];
        foreach (is_array($inputColumns) ? $inputColumns : [] as $column) {
            if (!is_array($column)) {
                continue;
            }

            $columns[] = $column;
        }

        if (count($columns) < 2) {
            $this->resetColumns();
            return;
        }

        // Legacy columns are given as width + trailing space (+ start y);
        // the X positions accumulate from the original margin.
        $this->pagecolumns = count($columns);
        $this->pagecolumnwidth = 0.0;
        $this->pageregions = [];
        $colpos = 0.0;
        foreach ($columns as $column) {
            $width = (float) ($column['w'] ?? 0);
            $ypos = isset($column['y']) && is_numeric($column['y']) ? (float) $column['y'] : $this->posy;
            if (isset($column['x']) && is_numeric($column['x'])) {
                $posx = (float) $column['x'];
            } elseif ($this->rtlmode) {
                $posx = $this->getPageWidth() - $this->orig_rmargin - $colpos - $width;
            } else {
                $posx = $this->orig_lmargin + $colpos;
            }

            $this->pageregions[] = [
                'RX' => $posx,
                'RY' => $ypos,
                'RW' => $width,
                'RH' => $this->getPageHeight() - $this->bmargin - $ypos,
            ];
            $colpos += $width + (float) ($column['s'] ?? 0);
        }

        if ($this->docstate === 2) {
            $this->setEnginePageRegions($this->pageregions);
            $this->selectColumn(0);
        }
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function selectColumn($col = null)
    {
        if ($this->docstate !== 2) {
            return;
        }

        $eng = $this->engine();
        $page = $eng->page->getPage();
        $regions = $page['region'];
        $current = (int) $page['currentRegion'];
        // Legacy: no argument re-selects the current column; out-of-range
        // wraps back to the first one.
        $col = $col === null || (string) $col === '' ? $current : (int) $col;
        if ($col < 0 || $col >= count($regions)) {
            $col = 0;
        }

        $eng->page->selectRegion($col);
        $region = $eng->page->getRegion();
        if ($col !== $current) {
            // Legacy: switching columns moves the cursor to the column top.
            $this->posy = $region['RY'];
        }

        if (count($regions) > 1) {
            // Legacy: the column edges become the current margins, so the
            // cursor-based methods wrap and return inside the column.
            $this->lmargin = $region['RX'];
            $this->rmargin = $this->getPageWidth() - $region['RX'] - $region['RW'];
        }

        $this->posx = $this->rtlmode ? $region['RX'] + $region['RW'] : $region['RX'];
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getColumn()
    {
        if ($this->docstate !== 2) {
            return 0;
        }

        return (int) $this->engine()->page->getPage()['currentRegion'];
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function getNumberOfColumns()
    {
        if ($this->docstate !== 2) {
            return $this->pagecolumns;
        }

        return count($this->engine()->page->getPage()['region']);
    }

    // ===================================================================
    // Text state.
    // ===================================================================

    public function setTextRenderingMode($stroke = 0, $fill = true, $clip = false)
    {
        $this->textrendermode = [
            'stroke' => (float) $stroke / $this->kratio,
            'fill' => (bool) $fill,
            'clip' => (bool) $clip,
        ];
    }

    public function setTextShadow($params = [
        'enabled' => false,
        'depth_w' => 0,
        'depth_h' => 0,
        'color' => false,
        'opacity' => 1,
        'blend_mode' => 'Normal',
    ])
    {
        $params = is_array($params) ? $params : [];
        $this->textshadow = [
            'enabled' => (bool) ($params['enabled'] ?? false),
            'depth_w' => (float) ($params['depth_w'] ?? 0),
            'depth_h' => (float) ($params['depth_h'] ?? 0),
            'color' => $params['color'] ?? false,
            'opacity' => (float) ($params['opacity'] ?? 1),
            'blend_mode' => (string) ($params['blend_mode'] ?? 'Normal'),
        ];
    }

    /**
     * @return array{enabled: bool, depth_w: int|float, depth_h: int|float, color: mixed, opacity: int|float, blend_mode: string} Text shadow parameters.
     */
    public function getTextShadow()
    {
        return $this->textshadow;
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \RuntimeException
     */
    public function hyphenateText(
        $text,
        $patterns,
        $dictionary = [],
        $leftmin = 1,
        $rightmin = 2,
        $charmin = 1,
        $charmax = 8,
    ) {
        // The engine hyphenates at render time from TeX patterns; configure
        // them and return the text unchanged.
        $eng = $this->engine();
        if (is_string($patterns) && $patterns !== '') {
            if (strtoupper($patterns) === 'LATIN') {
                $patterns = 'hyph-la.tex';
            }

            $file = $this->resolveLocalFile($patterns);
            if ($this->isAllowedLocalFile($file)) {
                $eng->setTexHyphenPatterns($eng->loadTexHyphenPatterns($file));
            }
        } elseif (is_array($patterns) && $patterns !== []) {
            $inputPatterns = $patterns;
            $patterns = [];
            foreach ($inputPatterns as $key => $val) {
                if (!(is_string($key) && is_string($val))) {
                    continue;
                }

                $patterns[$key] = $val;
            }

            $eng->setTexHyphenPatterns($patterns);
        }

        return (string) $text;
    }

    public function setRasterizeVectorImages($mode)
    {
        // The engine renders vector images natively.
    }

    /**
     * @throws \RuntimeException
     */
    public function setFontSubsetting($enable = true)
    {
        $this->fontsubsetting = (bool) $enable;
        // the fonts loaded afterwards without an explicit mode (e.g. by writeHTML())
        $this->engine()->font->setSubsetMode($this->fontsubsetting);
    }

    /**
     * @return bool Default font subsetting mode.
     */
    public function getFontSubsetting()
    {
        return $this->fontsubsetting;
    }

    /**
     * @throws \RuntimeException
     */
    public function stringLeftTrim($str, $replace = '')
    {
        return $this->engine()->strTrimLeft((string) $str, (string) $replace);
    }

    /**
     * @throws \RuntimeException
     */
    public function stringRightTrim($str, $replace = '')
    {
        return $this->engine()->strTrimRight((string) $str, (string) $replace);
    }

    /**
     * @throws \RuntimeException
     */
    public function stringTrim($str, $replace = '')
    {
        return $this->engine()->strTrim((string) $str, (string) $replace);
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function isUnicodeFont()
    {
        return $this->engine()->font->isCurrentUnicodeFont();
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function getFontFamilyName($fontfamily)
    {
        return $this->engine()->font->getFontFamilyName((string) $fontfamily);
    }

    // ===================================================================
    // Templates (Stage 4).
    // ===================================================================

    /**
     * @throws \Exception
     */
    public function startTemplate($w = 0, $h = 0, $group = false)
    {
        if ($this->docstate !== 2) {
            $this->Error('Unable to start a template: no page has been started');
            return false;
        }

        $width = (float) $w > 0 ? (float) $w : $this->getPageWidth();
        $height = (float) $h > 0 ? (float) $h : $this->getPageHeight();
        $this->xobjtid = $this->engine()->newXObjectTemplate($width, $height);
        $this->xobjheight = $height;
        return $this->xobjtid;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function endTemplate()
    {
        if ($this->xobjtid === '') {
            return false;
        }

        $tid = $this->xobjtid;
        $this->xobjtid = '';
        $this->engine()->exitXObjectTemplate();
        return $tid;
    }

    /**
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     * @throws \Com\Tecnick\Pdf\Image\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    public function printTemplate(
        $id,
        $x = null,
        $y = null,
        $w = 0,
        $h = 0,
        $align = '',
        $palign = '',
        $fitonpage = false,
    ) {
        if ($this->docstate !== 2) {
            return;
        }

        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        $this->emitToPage($this->engine()->getXObjectTemplate(
            (string) $id,
            $posx,
            $posy,
            (float) $w,
            (float) $h,
            'T',
            $this->halignToEngine($align),
        ));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function setFontStretching($perc = 100)
    {
        $this->fontstretching = (float) $perc;
        $this->refreshFontState();
    }

    /**
     * @return float Font stretching percentage.
     */
    public function getFontStretching()
    {
        return $this->fontstretching;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \RuntimeException
     */
    public function setFontSpacing($spacing = 0)
    {
        $this->fontspacing = (float) $spacing;
        $this->refreshFontState();
    }

    /**
     * @return float Extra font spacing in user units.
     */
    public function getFontSpacing()
    {
        return $this->fontspacing;
    }

    // ===================================================================
    // Page regions (Stage 4).
    // ===================================================================

    public function getPageRegions()
    {
        return $this->nowriteareas;
    }

    /**
     * @throws \RuntimeException
     */
    public function setPageRegions($regions = [])
    {
        // Legacy: empties the current regions, then re-adds each one. The
        // areas are kept verbatim and converted to engine banded writable
        // regions when text is flowed (applyNoWriteRegionsForFlow()).
        $this->nowriteareas = [];
        foreach (is_array($regions) ? $regions : [] as $region) {
            $this->addPageRegion($region);
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function addPageRegion($region)
    {
        if (!is_array($region)) {
            return;
        }

        // Legacy no-write region: a vertical (possibly slanted) segment plus
        // the page side it blocks. Validation mirrors the legacy library
        // (positive X, top above bottom, side L or R); an empty/zero page
        // means the current page.
        $page = isset($region['page']) && (int) $region['page'] > 0 ? (int) $region['page'] : $this->getPage();
        $xt = (float) ($region['xt'] ?? 0);
        $yt = (float) ($region['yt'] ?? 0);
        $xb = (float) ($region['xb'] ?? 0);
        $yb = (float) ($region['yb'] ?? 0);
        $side = (string) ($region['side'] ?? '');
        if ($xt > 0 && $xb > 0 && $yt >= 0 && $yt < $yb && ($side === 'L' || $side === 'R')) {
            $this->nowriteareas[] = [
                'page' => $page,
                'xt' => $xt,
                'yt' => $yt,
                'xb' => $xb,
                'yb' => $yb,
                'side' => $side,
            ];
        }
    }

    public function removePageRegion($key)
    {
        $key = (int) $key;
        if (isset($this->nowriteareas[$key])) {
            unset($this->nowriteareas[$key]);
            $this->nowriteareas = array_values($this->nowriteareas);
        }
    }

    /**
     * The legacy no-write areas registered for the current page, in the engine
     * setNoWriteRegions() input format (xt/yt/xb/yb/side).
     *
     * No-write regions only apply to flowing body content; the header/footer
     * decoration (drawn in a late pass with the page context restored, when a
     * page's regions may already be set) must keep its own layout, so report
     * none while rendering it — matching legacy, whose header sits above the
     * content area the regions live in.
     *
     * @return array<int, array{xt: float, yt: float, xb: float, yb: float, side: string}>
     * @throws \RuntimeException
     */
    protected function currentPageNoWriteAreas(): array
    {
        if ($this->nowriteareas === [] || $this->docstate !== 2 || $this->inheaderfooter) {
            return [];
        }

        $pagenum = $this->getPage();
        $areas = [];
        foreach ($this->nowriteareas as $area) {
            if ($area['page'] !== $pagenum) {
                continue;
            }

            $areas[] = [
                'xt' => $area['xt'],
                'yt' => $area['yt'],
                'xb' => $area['xb'],
                'yb' => $area['yb'],
                'side' => $area['side'],
            ];
        }

        return $areas;
    }

    /**
     * (Re)build the engine banded writable regions for the current page from
     * its legacy no-write areas, using $bandheight as the height of each
     * horizontal slice, then select the region that contains the current Y so
     * the flow starts at the cursor and hugs the obstacles band by band. The
     * text/HTML engine then advances region -> region -> fresh full-width page
     * on its own.
     *
     * @return bool True when no-write regions were applied (multi-region flow).
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function applyNoWriteRegionsForFlow(float $bandheight, float $cursory): bool
    {
        $areas = $this->currentPageNoWriteAreas();
        if ($areas === []) {
            return false;
        }

        $this->engine()->page->setNoWriteRegions($areas, max($bandheight, 0.1));
        $this->selectRegionAtY($cursory);
        return true;
    }

    /**
     * Select the (top-to-bottom ordered) writable region of the current page
     * whose vertical span contains $y, so a flow that starts at the cursor
     * begins in the right band. Falls back to the first region when $y sits
     * above them all.
     *
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws \RuntimeException
     */
    protected function selectRegionAtY(float $y): void
    {
        $eng = $this->engine();
        /** @var array<int, array{RX: float, RY: float, RW: float, RH: float}> $regions */
        $regions = $eng->page->getPage()['region'];
        $idx = 0;
        foreach ($regions as $i => $region) {
            if ($region['RY'] <= ($y + 0.01)) {
                $idx = (int) $i;
                continue;
            }

            break;
        }

        $eng->page->selectRegion($idx);
    }

    /**
     * Band height (one writable slice) for a no-write region flow of HTML: the
     * cell height of the body (outermost) font size declared in the fragment,
     * falling back to the current font. A slice should be about one body line
     * tall so the bands hug the obstacle without wasting vertical space (which
     * would push too much text onto the continuation page); larger inline runs
     * sit in the tall merged region at the top rather than at a band boundary.
     */
    protected function noWriteBandFromHtml(string $html): float
    {
        $size = $this->getFontSize();
        $match = [];
        if (preg_match('/font-size\s*:\s*([0-9.]+)\s*(px|pt|em|ex|mm|cm|in|pc|%)?/i', $html, $match) === 1) {
            $unit = $match[2] ?? 'pt';
            $size = (float) $this->getHTMLUnitToUnits($match[1], $this->getFontSize(), $unit);
        }

        return $this->getCellHeight($size, false);
    }

    /**
     * @throws \Exception
     */
    public function ImageSVG(
        $file,
        $x = null,
        $y = null,
        $w = 0,
        $h = 0,
        $link = '',
        $align = '',
        $palign = '',
        $border = 0,
        $fitonpage = false,
    ) {
        if ($this->docstate !== 2) {
            $this->Error('Unable to add an SVG image: no page has been started');
            return;
        }

        $eng = $this->engine();
        $file = (string) $file;
        if (!str_starts_with($file, '@')) {
            $file = $this->resolveLocalFile($file);
        }

        $posx = $x === null || (string) $x === '' ? $this->posx : (float) $x;
        $posy = $y === null || (string) $y === '' ? $this->posy : (float) $y;
        $page = $eng->page->getPage();
        $soid = $eng->addSVG($file, $posx, $posy, (float) $w, (float) $h, $page['height']);
        $this->emitToPage($eng->getSetSVG($soid));
        if (is_string($link) && $link !== '' && (float) $w > 0 && (float) $h > 0) {
            $this->attachLink($link, $posx, $posy, (float) $w, (float) $h);
        }
    }
}

