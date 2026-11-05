<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * Generador nativo de archivos Excel (.xlsx OpenXML) y CSV UTF-8 con BOM.
 * Utiliza `ZipArchive` + XML estándar sin requerir `ext-gd`, garantizando funcionamiento 100% nativo en XAMPP.
 */
class SpreadsheetWriter
{
    /**
     * Descarga un archivo `.xlsx` real compatible con Microsoft Excel, LibreOffice y Google Sheets.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    public function downloadXlsx(string $filename, array $headers, array $rows, string $sheetTitle = 'Datos'): Response
    {
        $binary = $this->buildXlsxBinary($headers, $rows, $sheetTitle);

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->safeFilename($filename, 'xlsx').'"',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Descarga un archivo `.csv` separado por `;` con BOM UTF-8 (listo para Excel en configuración regional es-AR).
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    public function downloadCsv(string $filename, array $headers, array $rows): Response
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");

        fputcsv($stream, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($stream, array_map(fn ($v) => $v === null ? '' : (string) $v, $row), ';');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->safeFilename($filename, 'csv').'"',
            'Cache-Control' => 'max-age=0, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Construye el binario `.xlsx` en memoria temporal usando `ZipArchive`.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    public function buildXlsxBinary(array $headers, array $rows, string $sheetTitle = 'Datos'): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'eet2_xlsx_');
        $zip = new ZipArchive();
        $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $allRows = array_merge([$headers], $rows);
        $sharedStrings = [];
        $sharedIndex = [];
        $sheetRowsXml = '';

        foreach (array_values($allRows) as $rIdx => $row) {
            $rowNumber = $rIdx + 1;
            $isHeader = $rIdx === 0;
            $cellsXml = '';

            foreach (array_values($row) as $cIdx => $rawVal) {
                $colRef = $this->columnLetter($cIdx).$rowNumber;
                $styleAttr = $isHeader ? ' s="1"' : '';
                $val = $rawVal === null ? '' : (string) $rawVal;

                if ($val === '' && ! $isHeader) {
                    continue;
                }

                // Los DNI/CUIL/legajos se guardan como texto compartido para no perder ceros ni formato.
                if (! isset($sharedIndex[$val])) {
                    $sharedIndex[$val] = count($sharedStrings);
                    $sharedStrings[] = $this->escapeXml($val);
                }
                $idx = $sharedIndex[$val];
                $cellsXml .= "<c r=\"{$colRef}\" t=\"s\"{$styleAttr}><v>{$idx}</v></c>";
            }

            $sheetRowsXml .= "<row r=\"{$rowNumber}\">{$cellsXml}</row>";
        }

        $cleanTitle = mb_substr(preg_replace('/[\\\\\\/?*\\[\\]:]+/', ' ', $sheetTitle) ?: 'Datos', 0, 31);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escapeXml($cleanTitle).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="10"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'</cellXfs>'
            .'</styleSheet>');

        $count = count($sharedStrings);
        $sstItems = implode('', array_map(fn ($s) => "<si><t xml:space=\"preserve\">{$s}</t></si>", $sharedStrings));
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            ."<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"{$count}\" uniqueCount=\"{$count}\">{$sstItems}</sst>");

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            ."<sheetData>{$sheetRowsXml}</sheetData>"
            .'</worksheet>');

        $zip->close();

        $binary = (string) file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $binary;
    }

    private function columnLetter(int $index): string
    {
        $n = $index + 1;
        $letters = '';
        while ($n > 0) {
            $mod = ($n - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $n = intdiv($n - $mod, 26);
        }

        return $letters;
    }

    private function escapeXml(string $value): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function safeFilename(string $filename, string $ext): string
    {
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($filename, PATHINFO_FILENAME)) ?: 'exportacion';

        return "{$base}.{$ext}";
    }
}
