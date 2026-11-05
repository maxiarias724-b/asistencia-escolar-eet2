<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lector mínimo de CSV y XLSX (solo lectura de la primera hoja).
 * Se implementa con ext-zip + SimpleXML para no depender de librerías que exigen ext-gd.
 * Devuelve una matriz de filas; cada fila es un arreglo de strings indexado desde 0.
 */
class SpreadsheetReader
{
    public const MAX_ROWS = 2000;

    /** @return array<int, array<int, string>> */
    public function read(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        $rows = match ($ext) {
            'xlsx' => $this->readXlsx($file->getRealPath()),
            'csv', 'txt' => $this->readCsv($file->getRealPath()),
            default => throw new RuntimeException('Formato no soportado. Usá CSV o Excel (.xlsx).'),
        };

        if (count($rows) > self::MAX_ROWS + 1) {
            throw new RuntimeException('El archivo supera el máximo de '.self::MAX_ROWS.' filas.');
        }

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content); // BOM UTF-8

        // Los CSV exportados desde Excel en Windows suelen venir en Windows-1252.
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = collect([';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")])
            ->sortDesc()->keys()->first();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null] || count(array_filter($row, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $rows[] = array_map(fn ($c) => trim((string) $c), $row);
        }
        fclose($stream);

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo Excel (¿está dañado?).');
        }

        try {
            $sheetName = $this->firstSheetPath($zip);
            $sheetXml = $zip->getFromName($sheetName);
            if ($sheetXml === false) {
                throw new RuntimeException('El Excel no contiene hojas legibles.');
            }

            $shared = $this->sharedStrings($zip);
            $sheet = $this->xml($sheetXml);
        } finally {
            $zip->close();
        }

        $rows = [];
        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];
            $position = 0;

            foreach ($row->c as $c) {
                $index = isset($c['r']) ? $this->columnIndex((string) $c['r']) : $position;
                $position = $index + 1;

                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => $this->richText($c->is),
                    default => (string) $c->v,
                };

                $cells[$index] = trim($value);
            }

            if ($cells === [] || count(array_filter($cells, fn ($c) => $c !== '')) === 0) {
                continue;
            }

            $max = max(array_keys($cells));
            $normalized = [];
            for ($i = 0; $i <= $max; $i++) {
                $normalized[$i] = $cells[$i] ?? '';
            }
            $rows[] = $normalized;
        }

        return $rows;
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $paths = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $paths[] = $name;
            }
        }
        natsort($paths);

        return reset($paths) ?: 'xl/worksheets/sheet1.xml';
    }

    /** @return array<int,string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $strings = [];
        foreach ($this->xml($xml)->si as $si) {
            $strings[] = $this->richText($si);
        }

        return $strings;
    }

    private function richText(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }

        if (isset($node->t)) {
            return (string) $node->t;
        }

        $text = '';
        foreach ($node->r ?? [] as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    private function xml(string $content): SimpleXMLElement
    {
        // Sin carga de entidades externas (XXE).
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            throw new RuntimeException('El archivo Excel tiene un XML inválido.');
        }

        return $xml;
    }

    /** "C12" -> 2 */
    private function columnIndex(string $reference): int
    {
        preg_match('/^([A-Z]+)/i', $reference, $m);
        $letters = strtoupper($m[1] ?? 'A');
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index - 1;
    }
}
