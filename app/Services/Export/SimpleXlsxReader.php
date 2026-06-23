<?php

namespace App\Services\Export;

/**
 * Minimal XLSX reader using ZipArchive + SimpleXML (PHP built-in).
 *
 * Reads the first worksheet of an XLSX file and returns rows as arrays.
 * Handles shared strings, inline strings, and numeric values.
 * Date cells are returned as 'yyyy-mm-dd' strings.
 */
class SimpleXlsxReader
{
    /**
     * Read the first sheet of an XLSX file.
     *
     * @param  string  $filepath  Absolute path to the .xlsx file
     * @param  int     $skipRows  Number of header rows to skip
     * @return array<int, array<int, string|int|float|null>>
     */
    public function readSheet(string $filepath, int $skipRows = 1): array
    {
        $sheets = $this->listSheets($filepath);
        if ($sheets === []) {
            return [];
        }

        return $this->readSheetByPath($filepath, $sheets[0]['path'], $skipRows);
    }

    /**
     * @return list<array{name: string, path: string, index: int}>
     */
    public function listSheets(string $filepath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new \RuntimeException("Cannot open XLSX file: {$filepath}");
        }

        $wbXml = $zip->getFromName('xl/workbook.xml');
        $wbRelsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $zip->close();

        if (! $wbXml || ! $wbRelsXml) {
            return [];
        }

        $wb = simplexml_load_string($wbXml);
        $rels = simplexml_load_string($wbRelsXml);
        $relMap = [];
        foreach ($rels->Relationship as $rel) {
            $relMap[(string) $rel['Id']] = (string) $rel['Target'];
        }

        $sheets = [];
        $index = 0;
        foreach ($wb->sheets->sheet as $sheet) {
            $rid = (string) ($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '');
            $target = $relMap[$rid] ?? '';
            if ($target === '') {
                $index++;
                continue;
            }

            $path = str_starts_with($target, 'xl/') ? $target : "xl/{$target}";
            $sheets[] = [
                'name' => (string) $sheet['name'],
                'path' => $path,
                'index' => $index,
            ];
            $index++;
        }

        return $sheets;
    }

    public function findSheetPath(string $filepath, array $matchNames): ?string
    {
        $normalized = array_map(fn ($name) => mb_strtolower(trim($name)), $matchNames);

        foreach ($this->listSheets($filepath) as $sheet) {
            if (in_array(mb_strtolower(trim($sheet['name'])), $normalized, true)) {
                return $sheet['path'];
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readSheetKeyedRows(string $filepath, string $sheetPath, int $dataStartRow = 1): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new \RuntimeException("Cannot open XLSX file: {$filepath}");
        }

        [$sharedStrings, $dateStyleIndices] = $this->loadWorkbookMeta($zip);
        $wsXml = $zip->getFromName($sheetPath);
        $zip->close();

        if (! $wsXml) {
            return [];
        }

        $ws = simplexml_load_string($wsXml);
        $rows = [];

        foreach ($ws->sheetData->row as $row) {
            $rowNumber = (int) ($row['r'] ?? 0);
            if ($rowNumber < $dataStartRow) {
                continue;
            }

            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/^([A-Z]+)/', $ref, $m);
                $col = $m[1] ?? 'A';
                $cells[$col] = $this->parseCellValue($cell, $sharedStrings, $dateStyleIndices);
            }

            if ($cells !== []) {
                $cells['_row'] = $rowNumber;
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    public function readSheetByPath(string $filepath, string $sheetPath, int $skipRows = 0): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new \RuntimeException("Cannot open XLSX file: {$filepath}");
        }

        [$sharedStrings, $dateStyleIndices] = $this->loadWorkbookMeta($zip);
        $wsXml = $zip->getFromName($sheetPath);
        $zip->close();

        if (! $wsXml) {
            return [];
        }

        $ws = simplexml_load_string($wsXml);
        $rows = [];

        foreach ($ws->sheetData->row as $row) {
            $rowArr = [];
            $lastCol = -1;

            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $colIdx = $this->colIndex($ref);

                while ($lastCol < $colIdx - 1) {
                    $rowArr[] = null;
                    $lastCol++;
                }

                $rowArr[] = $this->parseCellValue($cell, $sharedStrings, $dateStyleIndices);
                $lastCol = $colIdx;
            }

            $rows[] = $rowArr;
        }

        return array_slice($rows, $skipRows);
    }

    /**
     * @return array{0: list<string>, 1: array<int, bool>}
     */
    private function loadWorkbookMeta(\ZipArchive $zip): array
    {
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                $text = '';
                foreach ($si->r ?? [$si] as $r) {
                    $text .= (string) ($r->t ?? '');
                }
                if (! isset($si->r)) {
                    $text = (string) ($si->t ?? '');
                }
                $sharedStrings[] = $text;
            }
        }

        $dateStyleIndices = [];
        $stylesXml = $zip->getFromName('xl/styles.xml');
        if ($stylesXml) {
            $styles = simplexml_load_string($stylesXml);
            $dateNumFmtIds = [14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47];
            $customDateFmtIds = [];
            if (isset($styles->numFmts)) {
                foreach ($styles->numFmts->numFmt as $fmt) {
                    $id = (int) $fmt['numFmtId'];
                    $code = strtolower((string) $fmt['formatCode']);
                    if (str_contains($code, 'yy') || str_contains($code, 'dd') || str_contains($code, 'mm')) {
                        $customDateFmtIds[] = $id;
                    }
                }
            }
            $allDateIds = array_merge($dateNumFmtIds, $customDateFmtIds);
            if (isset($styles->cellXfs)) {
                foreach ($styles->cellXfs->xf as $i => $xf) {
                    if (in_array((int) $xf['numFmtId'], $allDateIds)) {
                        $dateStyleIndices[(int) $i] = true;
                    }
                }
            }
        }

        return [$sharedStrings, $dateStyleIndices];
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<int, bool>  $dateStyleIndices
     */
    private function parseCellValue(\SimpleXMLElement $cell, array $sharedStrings, array $dateStyleIndices): mixed
    {
        $type = (string) $cell['t'];
        $styleIdx = isset($cell['s']) ? (int) $cell['s'] : 0;
        $rawVal = isset($cell->v) ? (string) $cell->v : null;

        if ($type === 's') {
            return $sharedStrings[(int) $rawVal] ?? '';
        }

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        if ($type === 'b') {
            return $rawVal === '1';
        }

        if ($rawVal === null || $rawVal === '') {
            return null;
        }

        if (is_numeric($rawVal)) {
            if (isset($dateStyleIndices[$styleIdx])) {
                return $this->serialToDate((float) $rawVal);
            }

            return str_contains($rawVal, '.') ? (float) $rawVal : (int) $rawVal;
        }

        return $rawVal;
    }

    private function colIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', $ref, $m);
        $col   = $m[1] ?? 'A';
        $index = 0;
        foreach (str_split($col) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }
        return $index - 1;
    }

    private function serialToDate(float $serial): string
    {
        // Excel epoch: 1899-12-30 (accounting for the 1900 leap-year bug)
        $ts   = ($serial - 25569) * 86400;
        return gmdate('Y-m-d', (int) $ts);
    }
}
