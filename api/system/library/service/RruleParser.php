<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class RruleParser
{
    private string $freq = 'DAILY';
    private int $interval = 1;
    private array $byDay = [];
    private array $byMonthDay = [];
    private ?string $until = null;
    private ?int $count = null;

    public function __construct(string $rrule)
    {
        $parts = explode(';', $rrule);
        foreach ($parts as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) !== 2) {
                continue;
            }
            $key = strtoupper(trim($kv[0]));
            $value = trim($kv[1]);

            switch ($key) {
                case 'FREQ':
                    $this->freq = strtoupper($value);
                    break;
                case 'INTERVAL':
                    $this->interval = max(1, (int)$value);
                    break;
                case 'BYDAY':
                    $this->byDay = array_map('strtoupper', array_map('trim', explode(',', $value)));
                    break;
                case 'BYMONTHDAY':
                    $this->byMonthDay = array_map('intval', explode(',', $value));
                    break;
                case 'UNTIL':
                    $this->until = $value;
                    break;
                case 'COUNT':
                    $this->count = max(1, (int)$value);
                    break;
            }
        }
    }

    public function isDue(\DateTimeImmutable $lastProcessedAt, \DateTimeImmutable $now): bool
    {
        if ($this->until !== null) {
            $untilDate = $this->parseDateTime($this->until);
            if ($untilDate !== null && $now > $untilDate) {
                return false;
            }
        }

        $nextOccurrence = $this->computeNextOccurrence($lastProcessedAt);
        return $nextOccurrence !== null && $nextOccurrence <= $now;
    }

    public function computeNextOccurrence(\DateTimeImmutable $from): ?\DateTimeImmutable
    {
        switch ($this->freq) {
            case 'DAILY':
                return $from->add(new \DateInterval("P{$this->interval}D"));

            case 'WEEKLY':
                return $this->computeNextWeekly($from);

            case 'MONTHLY':
                return $this->computeNextMonthly($from);

            case 'YEARLY':
                return $from->add(new \DateInterval("P{$this->interval}Y"));

            default:
                return $from->add(new \DateInterval("P{$this->interval}D"));
        }
    }

    public function getNextDueDate(\DateTimeImmutable $from): ?\DateTimeImmutable
    {
        if ($this->until !== null) {
            $untilDate = $this->parseDateTime($this->until);
            if ($untilDate !== null && $from >= $untilDate) {
                return null;
            }
        }

        switch ($this->freq) {
            case 'DAILY':
                $next = $from->add(new \DateInterval("P{$this->interval}D"));
                break;
            case 'WEEKLY':
                $next = $this->getNextWeeklyDate($from);
                break;
            case 'MONTHLY':
                $next = $this->getNextMonthlyDate($from);
                break;
            case 'YEARLY':
                $next = $from->add(new \DateInterval("P{$this->interval}Y"));
                break;
            default:
                return null;
        }

        if ($this->until !== null && $next !== null) {
            $untilDate = $this->parseDateTime($this->until);
            if ($untilDate !== null && $next > $untilDate) {
                return null;
            }
        }

        return $next;
    }

    public function getFrequency(): string
    {
        return $this->freq;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function getByDay(): array
    {
        return $this->byDay;
    }

    public function getByMonthDay(): array
    {
        return $this->byMonthDay;
    }

    private function computeNextWeekly(\DateTimeImmutable $from): \DateTimeImmutable
    {
        if ($this->byDay === []) {
            return $from->add(new \DateInterval("P{$this->interval}W"));
        }

        $dayMap = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
        $targetDays = [];
        foreach ($this->byDay as $day) {
            $normalized = strtoupper(substr($day, 0, 2));
            if (isset($dayMap[$normalized])) {
                $targetDays[] = $dayMap[$normalized];
            }
        }
        sort($targetDays);

        $currentDow = (int)$from->format('N');
        $currentDate = $from;

        for ($weekOffset = 0; $weekOffset <= $this->interval; $weekOffset++) {
            foreach ($targetDays as $targetDow) {
                if ($weekOffset === 0 && $targetDow <= $currentDow) {
                    continue;
                }
                $diff = $targetDow - $currentDow + ($weekOffset * 7);
                $candidate = $from->add(new \DateInterval("P{$diff}D"));
                if ($candidate > $from) {
                    return $candidate;
                }
            }
        }

        return $from->add(new \DateInterval("P" . ($this->interval * 7) . "D"));
    }

    private function getNextWeeklyDate(\DateTimeImmutable $from): \DateTimeImmutable
    {
        if ($this->byDay === []) {
            return $from->add(new \DateInterval("P{$this->interval}W"));
        }

        $dayMap = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
        $targetDays = [];
        foreach ($this->byDay as $day) {
            $normalized = strtoupper(substr($day, 0, 2));
            if (isset($dayMap[$normalized])) {
                $targetDays[] = $dayMap[$normalized];
            }
        }
        sort($targetDays);

        $currentDow = (int)$from->format('N');
        $currentDate = $from;

        foreach ($targetDays as $targetDow) {
            if ($targetDow > $currentDow) {
                return $from->add(new \DateInterval("P" . ($targetDow - $currentDow) . "D"));
            }
        }

        $nextWeekStart = $from->add(new \DateInterval("P" . (8 - $currentDow + $targetDays[0] - 1) . "D"));
        return $nextWeekStart;
    }

    private function computeNextMonthly(\DateTimeImmutable $from): \DateTimeImmutable
    {
        $monthsToAdd = $this->interval;
        if ($this->byMonthDay !== []) {
            $currentMonth = (int)$from->format('n');
            $currentYear = (int)$from->format('Y');
            $currentDay = (int)$from->format('j');

            $targetDay = min($this->byMonthDay[0], (int)$from->format('t'));

            if ($targetDay > $currentDay) {
                try {
                    return new \DateTimeImmutable("{$currentYear}-{$currentMonth}-{$targetDay} {$from->format('H:i:s')}");
                } catch (\Throwable $e) {
                    error_log('[RruleParser::computeNextMonthly] date parse failed (targetDay): ' . $e->getMessage());
                }
            }

            $nextMonth = $currentMonth + $monthsToAdd;
            $nextYear = $currentYear;
            if ($nextMonth > 12) {
                $nextYear += intdiv($nextMonth - 1, 12);
                $nextMonth = (($nextMonth - 1) % 12) + 1;
            }
            $maxDay = (int)(new \DateTimeImmutable("{$nextYear}-{$nextMonth}-01"))->format('t');
            $targetDay = min($targetDay, $maxDay);
            try {
                return new \DateTimeImmutable("{$nextYear}-{$nextMonth}-{$targetDay} {$from->format('H:i:s')}");
            } catch (\Throwable $e) {
                error_log('[RruleParser::computeNextMonthly] date parse failed (nextMonth): ' . $e->getMessage());
            }
        }

        return $from->add(new \DateInterval("P{$monthsToAdd}M"));
    }

    private function getNextMonthlyDate(\DateTimeImmutable $from): \DateTimeImmutable
    {
        return $this->computeNextMonthly($from);
    }

    private function parseDateTime(string $value): ?\DateTimeImmutable
    {
        $formats = ['Ymd\THis', 'Ymd', 'Y-m-d\TH:i:s', 'Y-m-d'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt !== false) {
                return $dt;
            }
        }
        return null;
    }
}
