<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A minimal single-page PDF writer good enough for a plain business document:
 * lines of Helvetica/Helvetica-Bold text and straight ruling lines, placed
 * with an absolute x/y cursor (PDF's origin is bottom-left, y grows upward).
 *
 * There is no PDF library anywhere in this project - no Composer, no vendor/
 * directory at all - so salary settlement bills are built directly against
 * the PDF file format rather than adding a dependency (Monthly Salary spec s9).
 */
final class SimplePdf
{
    private const PAGE_WIDTH  = 595.28; // A4, in points
    private const PAGE_HEIGHT = 841.89;

    /** @var list<string> raw content-stream operators, one entry per call */
    private array $ops = [];

    public function pageWidth(): float
    {
        return self::PAGE_WIDTH;
    }

    public function pageHeight(): float
    {
        return self::PAGE_HEIGHT;
    }

    /**
     * @param array{0: float, 1: float, 2: float} $color RGB, each 0-1 (default black)
     */
    public function text(float $x, float $y, string $text, float $size = 11, bool $bold = false, array $color = [0, 0, 0]): self
    {
        $font = $bold ? 'F2' : 'F1';

        $this->ops[] = sprintf(
            '%.2F %.2F %.2F rg BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $color[0],
            $color[1],
            $color[2],
            $font,
            $size,
            $x,
            $y,
            self::escape($text),
        );

        return $this;
    }

    /**
     * Right-aligned text: places the string so it ends at $right, using a
     * rough per-character width estimate for Helvetica (no real font metrics
     * table here, just enough to keep a letterhead block flush right).
     *
     * @param array{0: float, 1: float, 2: float} $color RGB, each 0-1 (default black)
     */
    public function textRight(float $right, float $y, string $text, float $size = 11, bool $bold = false, array $color = [0, 0, 0]): self
    {
        return $this->text($right - mb_strlen($text) * $size * ($bold ? 0.56 : 0.5), $y, $text, $size, $bold, $color);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.75): self
    {
        $this->ops[] = sprintf('0 0 0 RG %.2F w %.2F %.2F m %.2F %.2F l S', $width, $x1, $y1, $x2, $y2);

        return $this;
    }

    /**
     * A filled rectangle - used for the solid banner behind the letterhead.
     *
     * @param array{0: float, 1: float, 2: float} $color RGB, each 0-1
     */
    public function rect(float $x, float $y, float $width, float $height, array $color): self
    {
        $this->ops[] = sprintf(
            '%.2F %.2F %.2F rg %.2F %.2F %.2F %.2F re f',
            $color[0],
            $color[1],
            $color[2],
            $x,
            $y,
            $width,
            $height,
        );

        return $this;
    }

    /**
     * Standard fonts only cover WinAnsi/ASCII glyphs - anything outside that
     * (e.g. the rupee sign) is stripped rather than left to render as a
     * missing-glyph box, so callers should pre-format money without it.
     */
    private static function escape(string $text): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }

    /**
     * Build the complete single-page PDF byte stream: a Catalog, a Pages tree
     * with one Page, two Type1 base-14 fonts (no embedding needed), and the
     * content stream of everything drawn above - followed by a plain xref
     * table and trailer so any PDF reader can open it.
     */
    public function output(): string
    {
        $content = implode("\n", $this->ops);

        $objects   = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
                . '/Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            self::PAGE_WIDTH,
            self::PAGE_HEIGHT,
        );
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content);

        $pdf     = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $index => $body) {
            $number            = $index + 1;
            $offsets[$number]  = strlen($pdf);
            $pdf              .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $count     = count($objects) + 1;

        $pdf .= "xref\n0 " . $count . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        }

        $pdf .= "trailer\n";
        $pdf .= sprintf('<< /Size %d /Root 1 0 R >>', $count) . "\n";
        $pdf .= 'startxref' . "\n" . $xrefStart . "\n%%EOF";

        return $pdf;
    }
}
