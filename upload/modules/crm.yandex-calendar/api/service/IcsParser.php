<?php
declare(strict_types=1);

namespace Module\Crm\YandexCalendar\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class IcsParser
{
    /** @return array<int,array<string,mixed>> */
    public static function events(string $ics): array
    {
        $normalized = str_replace([chr(13) . chr(10), chr(13)], chr(10), $ics);
        $lines = explode(chr(10), $normalized);
        $unfolded = [];
        foreach ($lines as $line) {
            if ($line !== '' && (str_starts_with($line, ' ') || str_starts_with($line, "\t")) && $unfolded !== []) {
                $unfolded[count($unfolded) - 1] .= substr($line, 1);
            } else {
                $unfolded[] = $line;
            }
        }
        $result = [];
        $current = null;
        foreach ($unfolded as $line) {
            $upper = strtoupper($line);
            if ($upper === 'BEGIN:VEVENT') { $current = []; continue; }
            if ($upper === 'END:VEVENT') {
                if (is_array($current) && !empty($current['UID'])) {
                    try { $result[] = self::normalize($current); } catch (\Throwable) { /* skip malformed VEVENT, keep the source import alive */ }
                }
                $current = null;
                continue;
            }
            if (!is_array($current) || !str_contains($line, ':')) continue;
            [$name, $value] = explode(':', $line, 2);
            $parts = explode(';', $name);
            $key = strtoupper((string)array_shift($parts));
            $params = [];
            foreach ($parts as $part) {
                if (str_contains($part, '=')) { [$pk, $pv] = explode('=', $part, 2); $params[strtoupper($pk)] = trim($pv, '"'); }
            }
            $current[$key] = ['value' => $value, 'params' => $params];
        }
        return $result;
    }

    /** @param array<string,array{value:string,params:array<string,string>}> $event */
    private static function normalize(array $event): array
    {
        $start = self::dateValue($event['DTSTART'] ?? null);
        $end = self::dateValue($event['DTEND'] ?? null);
        if (($start['value'] ?? '') === '' || ($end['value'] ?? '') === '') throw new RuntimeException('YANDEX_EVENT_DATE_INVALID');
        $allDay = (bool)($start['all_day'] ?? false);
        $recurrence = self::dateValue($event['RECURRENCE-ID'] ?? null);
        return [
            'uid' => self::text($event['UID'] ?? null),
            'recurrence_id' => $recurrence['value'] ?? null,
            'summary' => self::text($event['SUMMARY'] ?? null) ?: '(Без названия)',
            'description' => self::text($event['DESCRIPTION'] ?? null),
            'status' => strtolower(self::text($event['STATUS'] ?? null) ?: 'confirmed'),
            'starts_at' => $start['value'] ?? gmdate('Y-m-d H:i:s'),
            'ends_at' => $end['value'] ?? ($start['value'] ?? gmdate('Y-m-d H:i:s')),
            'is_all_day' => $allDay ? 1 : 0,
            'all_day_start' => $allDay ? ($start['date'] ?? null) : null,
            'all_day_end' => $allDay ? ($end['date'] ?? null) : null,
            'recurrence_rule' => self::text($event['RRULE'] ?? null),
            'last_modified' => self::dateValue($event['LAST-MODIFIED'] ?? null)['value'] ?? null,
        ];
    }

    /** @param array{value:string,params:array<string,string>}|null $field */
    private static function dateValue(?array $field): array
    {
        if (!$field || ($field['value'] ?? '') === '') return [];
        $raw = trim($field['value']);
        if (($field['params']['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $raw) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Ymd', $raw, new DateTimeZone('UTC'));
            if (!$date) return [];
            return ['value' => $date->format('Y-m-d H:i:s'), 'date' => $date->format('Y-m-d'), 'all_day' => true];
        }
        $tz = (string)($field['params']['TZID'] ?? 'UTC');
        if ($raw !== '' && str_ends_with($raw, 'Z')) { $tz = 'UTC'; $raw = substr($raw, 0, -1); }
        try { $date = new DateTimeImmutable($raw, new DateTimeZone($tz ?: 'UTC')); }
        catch (\Throwable) { $date = new DateTimeImmutable($raw, new DateTimeZone('UTC')); }
        return ['value' => $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'all_day' => false];
    }

    public static function toIcs(array $event, string $uid, ?string $rrule = null): string
    {
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TropaTT//Yandex Calendar//EN', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT'];
        $lines[] = 'UID:' . self::escape($uid);
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        if (!empty($event['is_all_day']) && !empty($event['all_day_start']) && !empty($event['all_day_end'])) {
            $lines[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', (string)$event['all_day_start']);
            $lines[] = 'DTEND;VALUE=DATE:' . str_replace('-', '', (string)$event['all_day_end']);
        } else {
            $lines[] = 'DTSTART:' . self::utc((string)$event['starts_at']);
            $lines[] = 'DTEND:' . self::utc((string)$event['ends_at']);
        }
        $lines[] = 'SUMMARY:' . self::escape((string)($event['title'] ?? ''));
        if (trim((string)($event['description'] ?? '')) !== '') $lines[] = 'DESCRIPTION:' . self::escape(strip_tags((string)$event['description']));
        if ($rrule !== null && trim($rrule) !== '') $lines[] = 'RRULE:' . preg_replace('/[^A-Za-z0-9=;,\-]/', '', $rrule);
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';
        return implode(chr(13) . chr(10), $lines) . chr(13) . chr(10);
    }

    private static function text(?array $field): string
    {
        if (!$field) return '';
        return strtr((string)($field['value'] ?? ''), ['\\n' => chr(10), '\\N' => chr(10), '\\,' => ',', '\\;' => ';', '\\\\' => '\\']);
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', chr(13) . chr(10), chr(10), chr(13)], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $value);
    }

    private static function utc(string $value): string
    {
        try { return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'); }
        catch (\Throwable) { throw new RuntimeException('YANDEX_EVENT_DATE_INVALID'); }
    }
}
