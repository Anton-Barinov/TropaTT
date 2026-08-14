<?php
declare(strict_types=1);

namespace Module\Crm\ShtabMigration\Service;

use RuntimeException;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

final class ShtabExportParser
{
    private const MAX_XML_ENTRY_BYTES = 16777216;
    private const MAX_XML_TOTAL_BYTES = 25165824;
    private const MAX_XML_ROW_BYTES = 1048576;
    private const MAX_SHARED_STRINGS = 200000;

    /** @return array<int,array<string,mixed>> */
    public function parse(string $path, string $name, int $maxRows = 100000): array
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!is_file($path)) {
            throw new RuntimeException('SHTAB_SOURCE_FILE_NOT_FOUND');
        }

        return match ($extension) {
            'csv', 'txt' => $this->parseCsv($path, $maxRows),
            'xlsx' => $this->parseXlsx($path, $maxRows),
            default => throw new RuntimeException('SHTAB_UNSUPPORTED_FILE_FORMAT'),
        };
    }

    /** @return array<int,array<string,mixed>> */
    private function parseCsv(string $path, int $maxRows): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('SHTAB_SOURCE_FILE_READ_FAILED');
        }

        try {
            $first = fgets($handle);
            if ($first === false) {
                return [];
            }
            $delimiter = $this->delimiter($this->stripBom($first));
            rewind($handle);
            $header = fgetcsv($handle, 0, $delimiter, '"', '\\');
            if (!is_array($header) || $header === []) {
                throw new RuntimeException('SHTAB_HEADER_REQUIRED');
            }
            $keys = $this->uniqueKeys($header);
            $rows = [];

            while (($values = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                if (count($rows) >= $maxRows) {
                    throw new RuntimeException('SHTAB_MAX_ROWS_EXCEEDED');
                }
                if (count($values) === 1 && trim((string)$values[0]) === '') {
                    continue;
                }
                $row = [];
                foreach ($keys as $index => $key) {
                    $row[$key] = $this->clean((string)($values[$index] ?? ''));
                }
                if ($this->hasValues($row)) {
                    $row['_row_number'] = count($rows) + 2;
                    $rows[] = $row;
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function parseXlsx(string $path, int $maxRows): array
    {
        if (!class_exists(ZipArchive::class) || !class_exists(XMLReader::class)) {
            throw new RuntimeException('SHTAB_XLSX_REQUIRES_XMLREADER');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('SHTAB_XLSX_READ_FAILED');
        }

        try {
            $sharedSize = $this->zipEntrySize($zip, 'xl/sharedStrings.xml', true);
            $sheetSize = $this->zipEntrySize($zip, 'xl/worksheets/sheet1.xml', false);
            if ($sharedSize + $sheetSize > self::MAX_XML_TOTAL_BYTES) {
                throw new RuntimeException('SHTAB_XLSX_ENTRIES_TOO_LARGE');
            }

            // Read and release one XML entry at a time. XMLReader then parses
            // individual <si>/<row> fragments instead of building a worksheet DOM.
            $this->ensureMemoryBudget($sharedSize * 2 + 4194304);
            $sharedXml = $this->zipEntry($zip, 'xl/sharedStrings.xml', true);
            $shared = $this->sharedStrings($sharedXml);
            unset($sharedXml);

            $this->ensureMemoryBudget($sheetSize * 2 + 8388608);
            $sheet = $this->zipEntry($zip, 'xl/worksheets/sheet1.xml');
        } finally {
            $zip->close();
        }

        if ($sheet === '') {
            throw new RuntimeException('SHTAB_XLSX_SHEET_MISSING');
        }

        $previousLibxmlErrors = libxml_use_internal_errors(true);
        $reader = new XMLReader();
        if (!$reader->XML($sheet, null, LIBXML_NONET | LIBXML_NOCDATA)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlErrors);
            unset($sheet, $shared);
            throw new RuntimeException('SHTAB_XLSX_XML_INVALID');
        }

        $header = null;
        $keys = [];
        $rows = [];
        $seenDataRows = 0;
        $fallbackRowNumber = 1;
        $parseError = null;

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                    continue;
                }
                $rawRow = $reader->readOuterXml();
                if (!is_string($rawRow) || $rawRow === '' || strlen($rawRow) > self::MAX_XML_ROW_BYTES) {
                    throw new RuntimeException('SHTAB_XLSX_ROW_TOO_LARGE');
                }
                $parsed = $this->parseXlsxRow($rawRow, $shared, $fallbackRowNumber++);
                $rowNumber = $parsed['row_number'];
                $cells = $parsed['cells'];

                if ($header === null) {
                    $header = $cells;
                    $keys = $this->uniqueKeys($header);
                    continue;
                }
                ++$seenDataRows;
                if ($seenDataRows > $maxRows) {
                    throw new RuntimeException('SHTAB_MAX_ROWS_EXCEEDED');
                }

                $row = [];
                foreach ($keys as $index => $key) {
                    $row[$key] = $cells[$index] ?? '';
                }
                if ($this->hasValues($row)) {
                    $row['_row_number'] = $rowNumber;
                    $rows[] = $row;
                }
                if ($seenDataRows % 128 === 0) {
                    // The returned API is row-array based, so retain a hard
                    // memory guard even though XML itself is streamed.
                    $this->ensureMemoryBudget(4194304);
                }
            }
        } catch (\Throwable $error) {
            $parseError = $error;
        }
        $xmlErrors = libxml_get_errors();
        $reader->close();
        unset($sheet, $shared);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlErrors);
        if ($parseError instanceof \Throwable) {
            throw $parseError;
        }
        if ($xmlErrors !== []) {
            throw new RuntimeException('SHTAB_XLSX_XML_INVALID');
        }

        if ($header === null) {
            return [];
        }
        return $rows;
    }

    /** @return array{row_number:int,cells:array<int,string>} */
    private function parseXlsxRow(string $xml, array $shared, int $fallbackNumber): array
    {
        $root = $this->loadXml($xml);
        if (!$root instanceof SimpleXMLElement) {
            throw new RuntimeException('SHTAB_XLSX_XML_INVALID');
        }
        $root->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = [];
        foreach ($root->xpath('./x:c') ?: [] as $cell) {
            $reference = (string)$cell['r'];
            $column = preg_replace('/\d+/', '', $reference) ?: 'A';
            $index = $this->columnIndex($column);
            $type = (string)$cell['t'];
            $valueNodes = $cell->xpath('./x:v') ?: [];
            $value = isset($valueNodes[0]) ? (string)$valueNodes[0] : '';
            if ($type === 's') {
                $value = $shared[(int)$value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = $this->xmlTextNodes($cell->xpath('./x:is//x:t') ?: []);
            }
            $cells[$index] = $this->clean($value);
        }

        $rowNumber = (int)($root['r'] ?? 0);
        return ['row_number' => $rowNumber > 0 ? $rowNumber : $fallbackNumber, 'cells' => $cells];
    }

    /** @return array<int,string> */
    private function sharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }
        $previousLibxmlErrors = libxml_use_internal_errors(true);
        $reader = new XMLReader();
        if (!$reader->XML($xml, null, LIBXML_NONET | LIBXML_NOCDATA)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousLibxmlErrors);
            throw new RuntimeException('SHTAB_XLSX_XML_INVALID');
        }
        $result = [];
        $parseError = null;
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si') {
                    continue;
                }
                $fragment = $reader->readOuterXml();
                if (!is_string($fragment) || strlen($fragment) > self::MAX_XML_ROW_BYTES) {
                    throw new RuntimeException('SHTAB_XLSX_SHARED_STRING_TOO_LARGE');
                }
                $item = $this->loadXml($fragment);
                if (!$item instanceof SimpleXMLElement) {
                    throw new RuntimeException('SHTAB_XLSX_XML_INVALID');
                }
                $item->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $result[] = $this->clean($this->xmlTextNodes($item->xpath('.//x:t') ?: []));
                if (count($result) > self::MAX_SHARED_STRINGS) {
                    throw new RuntimeException('SHTAB_XLSX_SHARED_STRINGS_LIMIT');
                }
                if (count($result) % 256 === 0) {
                    $this->ensureMemoryBudget(4194304);
                }
            }
        } catch (\Throwable $error) {
            $parseError = $error;
        }
        $xmlErrors = libxml_get_errors();
        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlErrors);
        if ($parseError instanceof \Throwable) {
            throw $parseError;
        }
        if ($xmlErrors !== []) {
            throw new RuntimeException('SHTAB_XLSX_XML_INVALID');
        }
        return $result;
    }

    private function loadXml(string $xml): ?SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $result = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);
        return $result instanceof SimpleXMLElement ? $result : null;
    }

    /** @param array<int,SimpleXMLElement> $nodes */
    private function xmlTextNodes(array $nodes): string
    {
        return implode('', array_map(static fn(SimpleXMLElement $node): string => (string)$node, $nodes));
    }

    private function ensureMemoryBudget(int $reserve): void
    {
        $raw = trim((string)ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return;
        }
        $unit = strtolower(substr($raw, -1));
        $number = (float)$raw;
        $multiplier = $unit === 'g' ? 1073741824 : ($unit === 'm' ? 1048576 : ($unit === 'k' ? 1024 : 1));
        $limit = (int)($number * $multiplier);
        if ($limit > 0 && memory_get_usage(true) + max(0, $reserve) > (int)($limit * 0.75)) {
            throw new RuntimeException('SHTAB_XLSX_MEMORY_LIMIT');
        }
    }

    private function zipEntrySize(ZipArchive $zip, string $name, bool $optional): int
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            if ($optional) {
                return 0;
            }
            throw new RuntimeException('SHTAB_XLSX_ENTRY_MISSING');
        }
        $size = (int)($stat['size'] ?? 0);
        if ($size > self::MAX_XML_ENTRY_BYTES) {
            throw new RuntimeException('SHTAB_XLSX_ENTRY_TOO_LARGE');
        }
        return $size;
    }

    private function zipEntry(ZipArchive $zip, string $name, bool $optional = false): string
    {
        $this->zipEntrySize($zip, $name, $optional);
        $data = $zip->getFromName($name);
        if (!is_string($data)) {
            if ($optional) {
                return '';
            }
            throw new RuntimeException('SHTAB_XLSX_READ_FAILED');
        }
        if (strlen($data) > self::MAX_XML_ENTRY_BYTES) {
            throw new RuntimeException('SHTAB_XLSX_ENTRY_TOO_LARGE');
        }
        return $data;
    }

    /** @param array<int,mixed> $header @return array<int,string> */
    private function uniqueKeys(array $header): array
    {
        $used = [];
        $keys = [];
        foreach ($header as $value) {
            $base = $this->normalizeKey($this->stripBom((string)$value));
            $base = $base !== '' ? $base : 'column';
            $key = $base;
            $number = 2;
            while (isset($used[$key])) {
                $key = $base . '_' . $number++;
            }
            $used[$key] = true;
            $keys[] = $key;
        }
        return $keys;
    }

    private function normalizeKey(string $key): string
    {
        $key = mb_strtolower(trim($key));
        $key = strtr($key, ['ё' => 'е', ' ' => '_', '-' => '_', '.' => '_', '/' => '_']);
        $key = str_replace(['№', '#'], '', $key);
        $aliases = [
            'идентификатор' => 'id', 'ид' => 'id', 'номер' => 'id',
            'наименование' => 'name', 'название' => 'name', 'описание' => 'description',
            'проект' => 'project_id', 'проекты' => 'project_id', 'родитель' => 'parent_id',
            'подзадача' => 'parent_id', 'исполнитель' => 'assignee_id', 'ответственный' => 'assignee_id',
            'срок' => 'due_at', 'дедлайн' => 'due_at', 'статус' => 'status', 'приоритет' => 'priority',
            'метки' => 'tags', 'теги' => 'tags', 'комментарий' => 'text', 'почта' => 'email',
            'электронная_почта' => 'email', 'пользователь' => 'user_id', 'организация' => 'organization_id',
            'компания' => 'organization_id', 'дата_изменения' => 'updated_at', 'изменено' => 'updated_at',
        ];
        $key = $aliases[$key] ?? $key;
        $normalized = preg_replace('/[^a-z0-9_]+/u', '_', $key);
        return $normalized === null ? '' : trim($normalized, '_');
    }

    private function stripBom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    private function clean(string $value): string
    {
        $value = trim($this->stripBom($value));
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1251, ISO-8859-1');
    }

    private function delimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);
        $key = (string)array_key_first($counts);
        return $counts[$key] > 0 ? $key : ',';
    }

    private function hasValues(array $row): bool
    {
        foreach ($row as $key => $value) {
            if ($key !== '_row_number' && trim((string)$value) !== '') {
                return true;
            }
        }
        return false;
    }

    private function columnIndex(string $column): int
    {
        $index = 0;
        foreach (str_split(strtoupper($column)) as $character) {
            $index = $index * 26 + (ord($character) - 64);
        }
        return max(0, $index - 1);
    }
}
