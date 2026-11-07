<?php

declare(strict_types=1);

namespace JieldTest\Pdf;

use Com\Tecnick\Pdf\Tcpdf as NativePdf;
use Jield\Pdf\Pdf;
use Jield\Pdf\Tcpdf;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class CompatibilityTest extends TestCase
{
    public function testMissingGlyphsUseTheProvidedSubstitutions(): void
    {
        $pdf = $this->pdf();

        self::assertSame('A', $pdf->replaceMissingChars("\u{4e2d}", subs: [0x4e2d => ['65']]));
    }

    public function testAnnotationsPreserveTheirOptions(): void
    {
        $pdf = $this->pdf();
        $pdf->AddPage();
        $pdf->Annotation(10, 10, 10, 10, 'note', ['Subtype' => 'Text', 'Name' => 'Comment', 'Open' => true]);
        $annotations = new ReflectionProperty(NativePdf::class, 'annotation')->getValue($this->engine($pdf));
        $annotation = array_values($annotations)[0];

        self::assertSame(['subtype' => 'Text', 'name' => 'Comment', 'open' => true], $annotation['opt']);
    }

    public function testPolycurvesEmitTheProvidedBezierSegments(): void
    {
        $pdf = $this->pdf();
        $pdf->AddPage();
        $pdf->Polycurve(10, 10, [[20, 20, 30, 30, 40, 40]], 'F');
        $content = implode('', $this->engine($pdf)->page->getPage()['content']);

        self::assertMatchesRegularExpression('/(?:[\d.]+ ){6}c\nf\n/', $content);
    }

    public function testGradientsRetainTheirColorStops(): void
    {
        $pdf = $this->pdf();
        $pdf->AddPage();
        $pdf->Gradient(2, [0, 0, 1, 0], [
            ['color' => [255, 0, 0], 'offset' => 0],
            ['color' => [0, 0, 255], 'offset' => 1],
        ]);
        $gradients = array_values($this->engine($pdf)->graph->getGradientsArray());

        self::assertSame(['rgb(255,0,0)', 'rgb(0,0,255)'], array_column($gradients[0]['colors'], 'color'));
        self::assertSame([0.0, 1.0], array_column($gradients[0]['colors'], 'offset'));
    }

    public function testCustomColumnsRetainTheirWidthsAndSpacing(): void
    {
        $pdf = $this->pdf();
        $pdf->AddPage();
        $pdf->setColumnsArray([['w' => 50, 's' => 5], ['w' => 60]]);
        $regions = $this->engine($pdf)->page->getPage()['region'];

        self::assertCount(2, $regions);
        self::assertEqualsWithDelta(50, $regions[0]['RW'], 0.000001);
        self::assertEqualsWithDelta(60, $regions[1]['RW'], 0.000001);
        self::assertEqualsWithDelta(55, $regions[1]['RX'] - $regions[0]['RX'], 0.000001);
    }

    public function testHtmlSpacingAndHyphenationReachTheRenderer(): void
    {
        $pdf = $this->pdf();
        $pdf->setHtmlVSpace(['p' => [['h' => '2.5', 'n' => '1'], ['h' => 0, 'n' => 2]]]);
        $pdf->hyphenateText('example', ['exam' => 'ex1am', 42 => 3]);
        $engine = $this->engine($pdf);

        self::assertSame(
            ['p' => [['h' => 2.5, 'n' => 1], ['h' => 0.0, 'n' => 2]]],
            new ReflectionProperty(NativePdf::class, 'tagvspaces')->getValue($engine),
        );
        self::assertSame(
            ['exam' => 'ex1am'],
            new ReflectionProperty(NativePdf::class, 'hyphen_patterns')->getValue($engine),
        );
    }

    public function testImportedTemplatesCanResizeTheCurrentPage(): void
    {
        $source = $this->pdf();
        $source->AddPage('L', [120, 80]);
        $path = tempnam(sys_get_temp_dir(), 'jield-pdf-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, $source->Output('source.pdf', 'S'));
            $pdf = $this->pdf();
            $pdf->setSourceFile($path);
            $template = $pdf->importPage(1);
            $pdf->AddPage();
            $pdf->useTemplate($template, adjustPageSize: true);

            self::assertSame(1, $pdf->getNumPages());
            self::assertEqualsWithDelta(120, $pdf->getPageWidth(), 0.001);
            self::assertEqualsWithDelta(80, $pdf->getPageHeight(), 0.001);
            self::assertStringStartsWith('%PDF-', $pdf->Output('resized.pdf', 'S'));
        } finally {
            unlink($path);
        }
    }

    #[RunInSeparateProcess]
    public function testInvalidApplicationFileSettingsKeepTheDefaults(): void
    {
        define('K_ALLOWED_PATHS', false);
        define('K_ALLOWED_HOSTS', false);
        define('K_CURLOPTS', false);
        $pdf = $this->pdf();
        $pdf->AddPage();

        self::assertStringStartsWith('%PDF-', $pdf->Output('defaults.pdf', 'S'));
    }

    private function pdf(): Pdf
    {
        $pdf = new Pdf();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        return $pdf;
    }

    private function engine(Pdf $pdf): NativePdf
    {
        return new ReflectionProperty(Tcpdf::class, 'eng')->getValue($pdf);
    }
}
