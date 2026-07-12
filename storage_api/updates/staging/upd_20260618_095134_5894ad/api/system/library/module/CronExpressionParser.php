<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class CronExpressionParser
{
    /** @var array<string, string> */
    private array $presets = [
        '@hourly' => '0 * * * *',
        '@daily' => '0 0 * * *',
        '@weekly' => '0 0 * * 0',
        '@monthly' => '0 0 1 * *',
        '@yearly' => '0 0 1 1 *',
        '@every_5min' => '*/5 * * * *',
        '@every_15min' => '*/15 * * * *',
        '@every_30min' => '*/30 * * * *',
    ];

    public function parse(string $expression): array
    {
        $expanded = $this->presets[$expression] ?? $expression;
        $parts = explode(' ', $expanded);

        if (count($parts) !== 5) {
            return ['minute' => '*', 'hour' => '*', 'dom' => '*', 'month' => '*', 'dow' => '*'];
        }

        return [
            'minute' => $parts[0],
            'hour' => $parts[1],
            'dom' => $parts[2],
            'month' => $parts[3],
            'dow' => $parts[4],
        ];
    }

    public function isDue(string $expression, ?\DateTime $now = null): bool
    {
        $now = $now ?? new \DateTime();
        $parts = $this->parse($expression);

        return $this->matchesField($parts['minute'], (int)$now->format('i'))
            && $this->matchesField($parts['hour'], (int)$now->format('G'))
            && $this->matchesField($parts['dom'], (int)$now->format('j'))
            && $this->matchesField($parts['month'], (int)$now->format('n'))
            && $this->matchesField($parts['dow'], (int)$now->format('w'));
    }

    public function getNextRunDate(string $expression, ?\DateTime $from = null): \DateTime
    {
        $from = $from ?? new \DateTime();
        $from = clone $from;
        $from->modify('+1 minute');

        $maxIterations = 525600;
        $iterations = 0;

        while (!$this->isDue($expression, $from) && $iterations < $maxIterations) {
            $from->modify('+1 minute');
            $iterations++;
        }

        return $from;
    }

    public function getHumanReadable(string $expression): string
    {
        $presetNames = array_flip($this->presets);
        return $presetNames[$expression] ?? $expression;
    }

    private function matchesField(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        foreach (explode(',', $field) as $part) {
            $step = 1;
            if (str_contains($part, '/')) {
                [$range, $step] = explode('/', $part);
                $step = (int)$step;
                if ($range === '*') {
                    return $value % $step === 0;
                }
                $part = $range;
            }

            if (str_contains($part, '-')) {
                [$low, $high] = explode('-', $part);
                $rangeValues = range((int)$low, (int)$high);
            } else {
                $rangeValues = [(int)$part];
            }

            foreach ($rangeValues as $v) {
                if ($step > 1) {
                    if ($v <= $value && ($value - $v) % $step === 0) {
                        return true;
                    }
                } elseif ($v === $value) {
                    return true;
                }
            }
        }

        return false;
    }
}
