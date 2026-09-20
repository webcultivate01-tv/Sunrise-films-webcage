<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A multi-page, tabular PDF writer for the Reports module - the same
 * dependency-free approach as SimplePdf (no Composer, no vendor/ directory
 * anywhere in this project), but built for a title + header captions + a
 * column-based table that can run to any number of rows instead of a single
 * fixed-layout page.
 *
 * Pages are landscape A4, since a report table typically carries more columns
 * than a portrait bill can hold. A new page starts automatically once the
 * current one runs out of room, repeating the title and column headers.
 */
final class ReportPdf
{
    private const PAGE_WIDTH  = 841.89; // A4 landscape, in points
    private const PAGE_HEIGHT = 595.28;
    private const MARGIN      = 36.0;
    private const ROW_HEIGHT  = 16.0;
    private const FONT_SIZE   = 8.0;

    /** @var list<array{label:string, width:float, align:string}> */
    private array $columns;

    /** @var list<string> */
    private array $metaLines;

    /** @var list<string> */
    private array $summaryLines;

    /** @var list<string> finished pages' content streams */
    private array $pages = [];

    /** @var list<string> raw content-stream operators for the page in progress */
    private array $ops = [];

    private float $y = 0.0;
    private bool $started = false;

    /**
     * @param list<array{label:string, width:float, align:string}> $columns
     * @param list<string>                                         $metaLines    who/what/when, repeated on every page
     * @param list<string>                                         $summaryLines the figures the report adds up to, page 1 only
     */
    public function __construct(
        private readonly string $title,
        array $columns,
        array $metaLines = [],
        array $summaryLines = [],
    ) {
        $this->columns      = $columns;
        $this->metaLines    = $metaLines;
        $this->summaryLines = $summaryLines;
    }

    /**
     * @param list<string> $cells one value per column, in column order
     */
    public function addRow(array $cells): void
    {
        $this->ensureStarted();

        if ($this->y < self::MARGIN + self::ROW_HEIGHT) {
            $this->newPage();
        }

        $x = self::MARGIN;

        foreach ($this->columns as $i => $column) {
            $this->cell($x, $column['width'], (string) ($cells[$i] ?? ''), self::FONT_SIZE, false, $column['align']);
            $x += $column['width'];
        }

        $this->y -= self::ROW_HEIGHT;
    }

    /**
     * The closing line that adds up every column the report marked as
     * summable - ruled off above so it cannot be mistaken for one more record.
     *
     * @param list<string> $cells one value per column, blank where nothing sums
     */
    public function addTotalsRow(array $cells): void
    {
        $this->ensureStarted();

        // The rule and the line it belongs to must not be split across pages.
        if ($this->y < self::MARGIN + self::ROW_HEIGHT + 8) {
            $this->newPage();
        }

        $this->y += 4;
        $this->line(self::MARGIN, $this->y, self::PAGE_WIDTH - self::MARGIN, $this->y);
        $this->y -= 8;

        $x = self::MARGIN;

        foreach ($this->columns as $i => $column) {
            $this->cell($x, $column['width'], (string) ($cells[$i] ?? ''), self::FONT_SIZE, true, $column['align']);
            $x += $column['width'];
        }

        $this->y -= self::ROW_HEIGHT;
    }

    /**
     * A plain full-width line of text, e.g. "No records match your filters."
     */
    public function addNote(string $text): void
    {
        $this->ensureStarted();

        if ($this->y < self::MARGIN + self::ROW_HEIGHT) {
            $this->newPage();
        }

        $this->text(self::MARGIN, $this->y, $text, self::FONT_SIZE + 1, false);
        $this->y -= self::ROW_HEIGHT;
    }

    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->started = true;
            $this->newPage();
        }
    }

    private function newPage(): void
    {
        if ($this->ops !== []) {
            $this->pages[] = implode("\n", $this->ops);
        }

        $this->ops = [];
        $this->y   = self::PAGE_HEIGHT - self::MARGIN;

        $this->text(self::MARGIN, $this->y, $this->title, 14, true);
        $this->y -= 18;

        foreach ($this->metaLines as $line) {
            $this->text(self::MARGIN, $this->y, $line, 8, false, [0.4, 0.4, 0.4]);
            $this->y -= 11;
        }

        // The summary belongs to the report as a whole, not to each page, so
        // it is printed once, under the header of the first page only.
        if ($this->pages === [] && $this->summaryLines !== []) {
            $this->y -= 4;

            foreach ($this->summaryLines as $line) {
                $this->text(self::MARGIN, $this->y, $line, 9, true, [0.12, 0.12, 0.12]);
                $this->y -= 12;
            }
        }

        $this->y -= 6;

        $x = self::MARGIN;

        foreach ($this->columns as $column) {
            $this->cell($x, $column['width'], $column['label'], self::FONT_SIZE, true, $column['align']);
            $x += $column['width'];
        }

        $this->y -= 4;
        $this->line(self::MARGIN, $this->y, self::PAGE_WIDTH - self::MARGIN, $this->y);
        $this->y -= self::ROW_HEIGHT;
    }

    private function cell(float $x, float $width, string $text, float $size, bool $bold, string $align): void
    {
        $text = self::truncate($text, $width - 6, $size, $bold);

        if ($align === 'right') {
            $this->text($x + $width - 4 - self::textWidth($text, $size, $bold), $this->y, $text, $size, $bold);
        } else {
            $this->text($x + 3, $this->y, $text, $size, $bold);
        }
    }

    /**
     * @param array{0: float, 1: float, 2: float} $color RGB, each 0-1
     */
    private function text(float $x, float $y, string $text, float $size, bool $bold, array $color = [0, 0, 0]): void
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
    }

    private function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->ops[] = sprintf('0.7 0.7 0.7 RG 0.75 w %.2F %.2F m %.2F %.2F l S', $x1, $y1, $x2, $y2);
    }

    /**
     * Rough per-character width for the base-14 Helvetica metrics - just
     * enough to right-align a cell and to know when to truncate one.
     */
    private static function textWidth(string $text, float $size, bool $bold): float
    {
        return mb_strlen($text) * $size * ($bold ? 0.56 : 0.5);
    }

    private static function truncate(string $text, float $maxWidth, float $size, bool $bold): string
    {
        if (self::textWidth($text, $size, $bold) <= $maxWidth) {
            return $text;
        }

        $charWidth = $size * ($bold ? 0.56 : 0.5);
        $maxChars  = max(1, (int) floor($maxWidth / $charWidth) - 2);

        return mb_substr($text, 0, $maxChars) . '..';
    }

    /**
     * Standard fonts only cover WinAnsi/ASCII glyphs - anything outside that
     * is stripped rather than left to render as a missing-glyph box.
     */
    private static function escape(string $text): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);
    }

    /**
     * "Page 2 of 7" in the footer of every page.
     *
     * It can only be written once the last page exists, so it is appended to
     * each finished content stream here rather than while the page was being
     * laid out - a reader who is handed a printed report needs to know a page
     * is missing.
     *
     * @param  list<string> $pages
     * @return list<string>
     */
    private function stampPageNumbers(array $pages): array
    {
        $total = count($pages);

        foreach ($pages as $index => $content) {
            $label = sprintf('Page %d of %d', $index + 1, $total);

            $pages[$index] = $content . "\n" . sprintf(
                '0.45 0.45 0.45 rg BT /F1 7.50 Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
                self::PAGE_WIDTH - self::MARGIN - self::textWidth($label, 7.5, false),
                self::MARGIN - 14,
                self::escape($label),
            );
        }

        return $pages;
    }

    /**
     * Build the complete multi-page PDF byte stream: a Catalog, a Pages tree
     * whose Kids list every page built above, two shared Type1 base-14 fonts,
     * one Contents stream per page, then a plain xref table and trailer.
     */
    public function output(): string
    {
        $this->ensureStarted();

        if ($this->ops !== []) {
            $this->pages[] = implode("\n", $this->ops);
        }

        /** @var array<int, string> $objects object number => body, filled in order */
        $objects    = [];
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pageObjectNumbers = [];
        $nextObject        = 5;

        foreach ($this->stampPageNumbers($this->pages) as $content) {
            $pageNumber    = $nextObject++;
            $contentNumber = $nextObject++;

            $pageObjectNumbers[] = $pageNumber;

            $objects[$pageNumber] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] '
                    . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>',
                self::PAGE_WIDTH,
                self::PAGE_HEIGHT,
                $contentNumber,
            );
            $objects[$contentNumber] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($content), $content);
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = sprintf(
            '<< /Type /Pages /Kids [%s] /Count %d >>',
            implode(' ', array_map(static fn (int $n): string => $n . ' 0 R', $pageObjectNumbers)),
            count($pageObjectNumbers),
        );

        ksort($objects);

        $pdf     = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf             .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefStart = strlen($pdf);
        $maxNumber = max(array_keys($objects));

        $pdf .= "xref\n0 " . ($maxNumber + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= $maxNumber; $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i] ?? 0) . "\n";
        }

        $pdf .= "trailer\n";
        $pdf .= sprintf('<< /Size %d /Root 1 0 R >>', $maxNumber + 1) . "\n";
        $pdf .= 'startxref' . "\n" . $xrefStart . "\n%%EOF";

        return $pdf;
    }
}
