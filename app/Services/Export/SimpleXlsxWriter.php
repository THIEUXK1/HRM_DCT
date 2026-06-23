<?php

namespace App\Services\Export;

/**
 * Minimal XLSX writer using PHP's built-in ZipArchive.
 *
 * Supports: string, number, date (ISO 8601), formula cells.
 * No external dependencies — works out of the box on any PHP 8.2 install.
 *
 * Usage:
 *   $xlsx = new SimpleXlsxWriter();
 *   $xlsx->addSheet('Sheet1', [
 *       ['Name', 'Age', 'Start Date'],
 *       ['Nguyen Van A', 30, '2023-01-15'],
 *   ]);
 *   return $xlsx->download('report.xlsx');
 */
class SimpleXlsxWriter
{
    private array $sheets = [];
    private array $sharedStrings = [];
    private array $sharedStringIndex = [];

    public function addSheet(string $name, array $rows, array $colWidths = []): self
    {
        $this->sheets[] = compact('name', 'rows', 'colWidths');
        return $this;
    }

    public function download(string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $content = $this->build();

        return response()->streamDownload(
            fn () => print $content,
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }

    public function build(): string
    {
        // Pre-pass: collect all strings for shared string table
        $this->sharedStrings = [];
        $this->sharedStringIndex = [];
        foreach ($this->sheets as $sheet) {
            foreach ($sheet['rows'] as $row) {
                foreach ($row as $cell) {
                    if (is_string($cell) && $cell !== '' && !isset($this->sharedStringIndex[$cell])) {
                        $this->sharedStringIndex[$cell] = count($this->sharedStrings);
                        $this->sharedStrings[] = $cell;
                    }
                }
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStringsXml());

        foreach ($this->sheets as $i => $sheet) {
            $zip->addFromString("xl/worksheets/sheet{$i}.xml", $this->worksheet($sheet));
        }

        $zip->close();
        $content = file_get_contents($tmp);
        unlink($tmp);

        return $content;
    }

    // ── XML parts ────────────────────────────────────────────────────────────

    private function contentTypes(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $_) {
            $sheets .= "<Override PartName=\"/xl/worksheets/sheet{$i}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
  {$sheets}
</Types>
XML;
    }

    private function rootRels(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function workbook(): string
    {
        $sheetsXml = '';
        foreach ($this->sheets as $i => $sheet) {
            $name = htmlspecialchars($sheet['name'], ENT_XML1);
            $sheetsXml .= "<sheet name=\"{$name}\" sheetId=\"" . ($i + 1) . "\" r:id=\"rId{$i}\"/>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
          xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>{$sheetsXml}</sheets>
</workbook>
XML;
    }

    private function workbookRels(): string
    {
        $rels = '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $rels .= '<Relationship Id="rIdSS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';
        foreach ($this->sheets as $i => $_) {
            $rels .= "<Relationship Id=\"rId{$i}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$i}.xml\"/>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  {$rels}
</Relationships>
XML;
    }

    private function styles(): string
    {
        // Style index 0 = default, 1 = header (bold), 2 = date
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/></font>
  </fonts>
  <fills count="2">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
  </fills>
  <borders count="1">
    <border><left/><right/><top/><bottom/><diagonal/></border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="14" fontId="0" fillId="0" borderId="0" xfId="0"/>
  </cellXfs>
</styleSheet>
XML;
    }

    private function sharedStringsXml(): string
    {
        $count = count($this->sharedStrings);
        $items = '';
        foreach ($this->sharedStrings as $s) {
            $escaped = htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $items .= "<si><t xml:space=\"preserve\">{$escaped}</t></si>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="{$count}" uniqueCount="{$count}">
  {$items}
</sst>
XML;
    }

    private function worksheet(array $sheet): string
    {
        $colWidthsXml = '';
        foreach ($sheet['colWidths'] as $col => $width) {
            $colNum = $col + 1;
            $colWidthsXml .= "<col min=\"{$colNum}\" max=\"{$colNum}\" width=\"{$width}\" customWidth=\"1\"/>";
        }
        if ($colWidthsXml) {
            $colWidthsXml = "<cols>{$colWidthsXml}</cols>";
        }

        $rowsXml = '';
        foreach ($sheet['rows'] as $rowIdx => $row) {
            $rowNum = $rowIdx + 1;
            $isHeader = $rowIdx === 0;
            $cellsXml = '';
            foreach ($row as $colIdx => $value) {
                $colLetter = $this->colLetter($colIdx);
                $cellRef   = "{$colLetter}{$rowNum}";
                $styleIdx  = $isHeader ? '1' : '0';
                $cellsXml .= $this->cell($cellRef, $value, $styleIdx);
            }
            $rowsXml .= "<row r=\"{$rowNum}\">{$cellsXml}</row>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  {$colWidthsXml}
  <sheetData>{$rowsXml}</sheetData>
</worksheet>
XML;
    }

    private function cell(string $ref, mixed $value, string $styleIdx = '0'): string
    {
        if ($value === null || $value === '') {
            return "<c r=\"{$ref}\" s=\"{$styleIdx}\"/>";
        }

        // Detect ISO date string yyyy-mm-dd
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $serial = $this->dateToSerial($value);
            return "<c r=\"{$ref}\" s=\"2\" t=\"n\"><v>{$serial}</v></c>";
        }

        // Numbers
        if (is_int($value) || is_float($value)) {
            return "<c r=\"{$ref}\" s=\"{$styleIdx}\" t=\"n\"><v>{$value}</v></c>";
        }

        // Strings via shared string table
        $str = (string) $value;
        if (!isset($this->sharedStringIndex[$str])) {
            $this->sharedStringIndex[$str] = count($this->sharedStrings);
            $this->sharedStrings[] = $str;
        }
        $idx = $this->sharedStringIndex[$str];

        return "<c r=\"{$ref}\" s=\"{$styleIdx}\" t=\"s\"><v>{$idx}</v></c>";
    }

    private function colLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod    = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index  = (int) (($index - $mod) / 26);
        }
        return $letter;
    }

    private function dateToSerial(string $date): int
    {
        // Excel serial: days since 1900-01-01 (with leap-year bug offset)
        $ts = strtotime($date);
        return (int) (($ts / 86400) + 25569);
    }
}
