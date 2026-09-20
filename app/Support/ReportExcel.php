<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A dependency-free "Excel" writer for the Reports module - there is no PHP
 * zip extension guaranteed on this server and no Composer/vendor anywhere in
 * this project, so a real .xlsx (a zip of XML parts) is not an option.
 *
 * Instead this writes the SpreadsheetML 2003 XML format: a single, plain-text
 * XML file that Excel opens natively (File > Open, or a double-click) with a
 * real worksheet, a bold header row and proper numeric cells - not just a CSV
 * dump - while remaining a single file with no zip step at all.
 *
 * The sheet is laid out the same way the PDF is, so the two exports of one
 * report read alike: title, the captions saying what it covers, the figures
 * it adds up to, then the table, closed by its totals line.
 */
final class ReportExcel
{
    /** @var list<list<string>> */
    private array $rows = [];

    /** @var list<string> */
    private array $captions = [];

    /** @var list<array{label:string, value:string}> */
    private array $summary = [];

    /** @var list<string>|null */
    private ?array $totals = null;

    /**
     * @param list<array{label:string, width?:float, align?:string}> $columns
     */
    public function __construct(
        private readonly string $title,
        private readonly array $columns,
    ) {
    }

    /**
     * A line under the title saying what the report covers - who it is for,
     * who generated it when.
     */
    public function addCaption(string $text): void
    {
        $this->captions[] = $text;
    }

    /**
     * One headline figure, written as a real label/value pair of cells so it
     * can be referenced from a formula rather than re-typed.
     */
    public function addSummary(string $label, string $value): void
    {
        $this->summary[] = ['label' => $label, 'value' => $value];
    }

    /**
     * @param list<string> $cells one value per column, in column order
     */
    public function addRow(array $cells): void
    {
        $this->rows[] = array_values($cells);
    }

    /**
     * The closing totals line, in the same column order as the rows.
     *
     * @param list<string> $cells
     */
    public function setTotals(array $cells): void
    {
        $this->totals = array_values($cells);
    }

    public function output(): string
    {
        $lastColumn = max(0, count($this->columns) - 1);

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" '
              . 'xmlns:o="urn:schemas-microsoft-com:office:office" '
              . 'xmlns:x="urn:schemas-microsoft-com:office:excel" '
              . 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

        $xml .= '<Styles>'
              . '<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14"/></Style>'
              . '<Style ss:ID="Caption"><Font ss:Size="9" ss:Color="#6B7280"/></Style>'
              . '<Style ss:ID="SummaryLabel"><Font ss:Bold="1" ss:Color="#374151"/></Style>'
              . '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#111827"/><Interior ss:Color="#E5E7EB" ss:Pattern="Solid"/></Style>'
              . '<Style ss:ID="Total"><Font ss:Bold="1" ss:Color="#111827"/>'
              . '<Borders><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>'
              . '</Styles>' . "\n";

        $xml .= '<Worksheet ss:Name="Report">' . "\n<Table>\n";

        // Column widths mirror the PDF's, so a sheet opens readable instead of
        // as a row of ### until every column is dragged wider.
        foreach ($this->columns as $column) {
            $xml .= '<Column ss:AutoFitWidth="0" ss:Width="' . self::width($column) . '"/>' . "\n";
        }

        $xml .= '<Row><Cell ss:StyleID="Title" ss:MergeAcross="' . $lastColumn . '">'
              . '<Data ss:Type="String">' . self::escape($this->title) . '</Data></Cell></Row>' . "\n";

        foreach ($this->captions as $caption) {
            $xml .= '<Row><Cell ss:StyleID="Caption" ss:MergeAcross="' . $lastColumn . '">'
                  . '<Data ss:Type="String">' . self::escape($caption) . '</Data></Cell></Row>' . "\n";
        }

        if ($this->summary !== []) {
            $xml .= '<Row></Row>' . "\n";

            foreach ($this->summary as $part) {
                $xml .= '<Row>'
                      . '<Cell ss:StyleID="SummaryLabel"><Data ss:Type="String">' . self::escape($part['label']) . '</Data></Cell>'
                      . self::cell($part['value'])
                      . '</Row>' . "\n";
            }
        }

        $xml .= '<Row></Row>' . "\n";

        $xml .= '<Row>';

        foreach ($this->columns as $column) {
            $xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">' . self::escape($column['label']) . '</Data></Cell>';
        }

        $xml .= '</Row>' . "\n";

        foreach ($this->rows as $row) {
            $xml .= '<Row>';

            foreach ($row as $value) {
                $xml .= self::cell($value);
            }

            $xml .= '</Row>' . "\n";
        }

        if ($this->totals !== null) {
            $xml .= '<Row>';

            foreach ($this->totals as $value) {
                $xml .= self::cell($value, 'Total');
            }

            $xml .= '</Row>' . "\n";
        }

        $xml .= "</Table>\n</Worksheet>\n</Workbook>";

        return $xml;
    }

    /**
     * Numbers go in as numbers so Excel can sum them; everything else is text.
     */
    private static function cell(string $value, ?string $style = null): string
    {
        $value     = trim($value);
        $styleAttr = $style === null ? '' : ' ss:StyleID="' . $style . '"';
        $type      = $value !== '' && is_numeric($value) ? 'Number' : 'String';

        return '<Cell' . $styleAttr . '><Data ss:Type="' . $type . '">' . self::escape($value) . '</Data></Cell>';
    }

    /**
     * @param array{label:string, width?:float, align?:string} $column
     */
    private static function width(array $column): string
    {
        // PDF points are close enough to Excel's units for a sensible column,
        // with a floor so a narrow "ID" column still shows its header.
        return number_format(max(45.0, (float) ($column['width'] ?? 90.0)), 2, '.', '');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
