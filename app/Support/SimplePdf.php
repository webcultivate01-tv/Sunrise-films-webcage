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

    /** Helvetica advance widths for ASCII 32-126, in 1/1000 em. */
    private const REGULAR_WIDTHS = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

    /** Helvetica-Bold advance widths for ASCII 32-126, in 1/1000 em. */
    private const BOLD_WIDTHS = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    /** @var list<string> raw content-stream operators, one entry per call */
    private array $ops = [];

    /** @var list<array{width: int, height: int, data: string}> greyscale images, drawn as /Im1, /Im2, ... */
    private array $images = [];

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
        return $this->text($right - $this->textWidth($text, $size, $bold), $y, $text, $size, $bold, $color);
    }

    /**
     * Text centred on $centre.
     *
     * @param array{0: float, 1: float, 2: float} $color RGB, each 0-1 (default black)
     */
    public function textCentered(float $centre, float $y, string $text, float $size = 11, bool $bold = false, array $color = [0, 0, 0]): self
    {
        return $this->text($centre - $this->textWidth($text, $size, $bold) / 2, $y, $text, $size, $bold, $color);
    }

    /**
     * The width of $text in points, from the Helvetica / Helvetica-Bold
     * advance widths (1000 units per em) for the printable ASCII range.
     */
    public function textWidth(string $text, float $size, bool $bold = false): float
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;
        $table = $bold ? self::BOLD_WIDTHS : self::REGULAR_WIDTHS;
        $units = 0;

        foreach (str_split($ascii) as $char) {
            $units += $table[ord($char) - 32];
        }

        return $units * $size / 1000;
    }

    /**
     * Break $text into lines no wider than $maxWidth, honouring hard newlines
     * and splitting a single over-long word rather than letting it overflow.
     *
     * @return list<string>
     */
    public function wrap(string $text, float $maxWidth, float $size, bool $bold = false): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $text) ?: [''] as $paragraph) {
            $current = '';

            foreach (preg_split('/\s+/', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                while ($this->textWidth($word, $size, $bold) > $maxWidth && mb_strlen($word) > 1) {
                    $cut = mb_strlen($word) - 1;

                    while ($cut > 1 && $this->textWidth(mb_substr($word, 0, $cut), $size, $bold) > $maxWidth) {
                        $cut--;
                    }

                    if ($current !== '') {
                        $lines[]  = $current;
                        $current = '';
                    }

                    $lines[] = mb_substr($word, 0, $cut);
                    $word    = mb_substr($word, $cut);
                }

                $candidate = $current === '' ? $word : $current . ' ' . $word;

                if ($current !== '' && $this->textWidth($candidate, $size, $bold) > $maxWidth) {
                    $lines[]  = $current;
                    $current = $word;
                } else {
                    $current = $candidate;
                }
            }

            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * @param array{0: float, 1: float, 2: float} $color RGB, each 0-1 (default black)
     */
    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.75, array $color = [0, 0, 0]): self
    {
        $this->ops[] = sprintf(
            '%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S',
            $color[0],
            $color[1],
            $color[2],
            $width,
            $x1,
            $y1,
            $x2,
            $y2,
        );

        return $this;
    }

    /**
     * A rounded rectangle, filled and/or stroked. $y is the bottom edge.
     *
     * @param array{0: float, 1: float, 2: float}|null $fill   RGB fill, or null for none
     * @param array{0: float, 1: float, 2: float}|null $stroke RGB outline, or null for none
     */
    public function roundedRect(float $x, float $y, float $width, float $height, float $radius, ?array $fill, ?array $stroke = null, float $strokeWidth = 1.0): self
    {
        if ($fill === null && $stroke === null) {
            return $this;
        }

        $r = min($radius, $width / 2, $height / 2);
        $k = $r * 0.5522847498; // bezier handle length for a quarter circle
        $x2 = $x + $width;
        $y2 = $y + $height;

        $path = sprintf(
            '%.2F %.2F m %.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c '
            . '%.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F l %.2F %.2F %.2F %.2F %.2F %.2F c h',
            $x + $r, $y,
            $x2 - $r, $y,
            $x2 - $r + $k, $y, $x2, $y + $r - $k, $x2, $y + $r,
            $x2, $y2 - $r,
            $x2, $y2 - $r + $k, $x2 - $r + $k, $y2, $x2 - $r, $y2,
            $x + $r, $y2,
            $x + $r - $k, $y2, $x, $y2 - $r + $k, $x, $y2 - $r,
            $x, $y + $r,
            $x, $y + $r - $k, $x + $r - $k, $y, $x + $r, $y,
        );

        $prefix = '';
        $paint  = '';

        if ($fill !== null) {
            $prefix .= sprintf('%.3F %.3F %.3F rg ', $fill[0], $fill[1], $fill[2]);
        }

        if ($stroke !== null) {
            $prefix .= sprintf('%.3F %.3F %.3F RG %.2F w ', $stroke[0], $stroke[1], $stroke[2], $strokeWidth);
        }

        $paint = $fill !== null ? ($stroke !== null ? 'B' : 'f') : 'S';

        $this->ops[] = $prefix . $path . ' ' . $paint;

        return $this;
    }

    /**
     * The rupee sign, drawn as strokes because Helvetica has no glyph for it.
     * $y is the text baseline; returns the horizontal advance in points so the
     * digits can follow it.
     *
     * @param array{0: float, 1: float, 2: float} $color RGB, each 0-1 (default black)
     */
    public function rupee(float $x, float $y, float $size, bool $bold = false, array $color = [0, 0, 0]): float
    {
        $u = $size / 100; // work in 1/100 em
        $p = static fn (float $ax, float $ay): string => sprintf('%.2F %.2F', $x + $ax * $u, $y + $ay * $u);

        $this->ops[] = sprintf('%.3F %.3F %.3F RG %.2F w 1 J 1 j ', $color[0], $color[1], $color[2], ($bold ? 8.5 : 6.0) * $u)
            // the two horizontal bars
            . $p(7, 70) . ' m ' . $p(52, 70) . ' l S '
            . $p(7, 53) . ' m ' . $p(52, 53) . ' l S '
            // the bowl, closing into the diagonal leg down to the baseline
            . $p(13, 70) . ' m ' . $p(26, 70) . ' l '
            . $p(46, 70) . ' ' . $p(46, 36) . ' ' . $p(26, 36) . ' c '
            . $p(13, 36) . ' l ' . $p(40, 0) . ' l S';

        return $size * 0.56;
    }

    /**
     * Money with a drawn rupee sign, right-aligned to $right. $digits is the
     * formatted figure without the sign (e.g. "10,000.00").
     *
     * @param array{0: float, 1: float, 2: float} $color RGB, each 0-1 (default black)
     */
    public function rupeeRight(float $right, float $y, string $digits, float $size, bool $bold = false, array $color = [0, 0, 0]): self
    {
        $left = $right - $this->textWidth($digits, $size, $bold) - $size * 0.56;
        $this->rupee($left, $y, $size, $bold, $color);

        return $this->text($left + $size * 0.56, $y, $digits, $size, $bold, $color);
    }

    /**
     * Register an 8-bit greyscale image and draw it into the box whose bottom
     * left corner is ($x, $y). The pixels are stored flate-compressed.
     */
    public function grayImage(string $pixels, int $pixelWidth, int $pixelHeight, float $x, float $y, float $width, float $height): self
    {
        $this->images[] = [
            'width'  => $pixelWidth,
            'height' => $pixelHeight,
            'data'   => (string) gzcompress($pixels, 6),
        ];

        $this->ops[] = sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q', $width, $height, $x, $y, count($this->images));

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

        // Images are objects 7, 8, ... after the content stream (object 6).
        $xObjects = '';

        foreach (array_keys($this->images) as $index) {
            $xObjects .= sprintf('/Im%d %d 0 R ', $index + 1, 7 + $index);
        }

        $objects   = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
                . '/Resources << /Font << /F1 4 0 R /F2 5 0 R >>%s >> /Contents 6 0 R >>',
            self::PAGE_WIDTH,
            self::PAGE_HEIGHT,
            $xObjects !== '' ? ' /XObject << ' . $xObjects . '>>' : '',
        );
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content);

        foreach ($this->images as $image) {
            $objects[] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray "
                . "/BitsPerComponent 8 /Filter /FlateDecode /Length %d >>\nstream\n%s\nendstream",
                $image['width'],
                $image['height'],
                strlen($image['data']),
                $image['data'],
            );
        }

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
