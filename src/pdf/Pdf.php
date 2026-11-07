<?php

declare(strict_types=1);

namespace Jield\Pdf;

use Com\Tecnick\Pdf\Import\PageTemplateInterface;
use InvalidArgumentException;
use RuntimeException;

/** Legacy cursor-based PDF API with import handled by the modern engine. */
class Pdf extends Tcpdf
{
    private ?string $importSource = null;
    /** @var array<string, PageTemplateInterface> */
    private array $importedPages = [];

    /**
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws RuntimeException
     */
    public function setSourceFile(string $file): int
    {
        $this->importSource = $this->engine()->setImportSourceFile($file);
        return $this->engine()->getSourcePageCount($this->importSource);
    }

    /**
     * @throws RuntimeException
     * @throws \Com\Tecnick\Pdf\Encrypt\Exception
     * @throws \Com\Tecnick\Pdf\Exception
     */
    public function importPage(int $pageNumber, string $box = 'CropBox', bool $groupXObject = true, bool $importExternalLinks = false): string
    {
        if ($this->importSource === null) {
            throw new RuntimeException('Set a PDF source file before importing a page.');
        }
        $key = 'page-' . (count($this->importedPages) + 1);
        $this->importedPages[$key] = $this->engine()->importPage($this->importSource, $pageNumber, [
            'box' => $box, 'groupXObject' => $groupXObject,
        ]);
        return $key;
    }

    /**
     * Measure an imported page, preserving its aspect ratio when one dimension is omitted.
     *
     * @return array{0: float, 1: float, width: float, height: float, orientation: 'L'|'P'}
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function getTemplateSize(string $tpl, ?float $width = null, ?float $height = null): array
    {
        $template = $this->importedPages[$tpl] ?? throw new InvalidArgumentException('Unknown imported page: ' . $tpl);
        $originalWidth = $this->engine()->toUnit($template->getWidth());
        $originalHeight = $this->engine()->toUnit($template->getHeight());
        if ($width) {
            $height = $height ?: $originalHeight * $width / $originalWidth;
        } elseif ($height) {
            $width = $originalWidth * $height / $originalHeight;
        } else {
            $width = $originalWidth;
            $height = $originalHeight;
        }
        return [0 => $width, 1 => $height, 'width' => $width, 'height' => $height, 'orientation' => $width > $height ? 'L' : 'P'];
    }

    /**
     * Render an imported page and return its dimensions.
     *
     * @return array{0: float, 1: float, width: float, height: float, orientation: 'L'|'P'}
     * @throws \Com\Tecnick\Pdf\Page\Exception
     * @throws RuntimeException
     * @throws InvalidArgumentException If the template identifier has not been imported.
     */
    public function useTemplate(string $tpl, ?float $x = null, ?float $y = null, ?float $width = null, ?float $height = null, bool $adjustPageSize = false): array
    {
        $size = $this->getTemplateSize($tpl, $width, $height);
        if ($adjustPageSize) {
            $this->curformat = [$size['width'], $size['height']];
            $this->curorientation = $size['orientation'];
            $this->engine()->page->setPagePWidth($size['width'] * $this->kratio);
            $this->engine()->page->setPagePHeight($size['height'] * $this->kratio);
        }
        $this->engine()->useImportedPage($this->importedPages[$tpl], $x ?? 0, $y ?? 0, $size['width'], $size['height']);
        return $size;
    }

    /**
     * @throws RuntimeException
     * @throws \Com\Tecnick\File\Exception
     * @throws \Com\Tecnick\Pdf\Font\Exception
     * @throws \Com\Tecnick\Pdf\Page\Exception
     */
    public function setFontFromFile(string $file, string $style = '', float $size = 12): void
    {
        $directory = sys_get_temp_dir() . '/jield-pdf-font-' . hash('sha256', $file . ':' . filemtime($file));
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create the PDF font cache.');
        }
        $directory = realpath($directory);
        $definitions = glob($directory . '/*.json');
        if (!$definitions) {
            $font = new \Com\Tecnick\Pdf\Font\Import($file, $directory . '/', flags: 96);
            $definition = $directory . '/' . $font->getFontName() . '.json';
        } else {
            $definition = $definitions[0];
        }
        $this->setFont(pathinfo($definition, PATHINFO_FILENAME), $style, $size, $definition, false);
    }
}
