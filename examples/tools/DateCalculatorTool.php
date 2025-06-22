<?php

namespace Vizra\VizraADK\Examples\tools;

use DateTime;
use DateTimeZone;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * Example tool for date and time calculations.
 *
 * This tool demonstrates:
 * - Implementing the ToolInterface
 * - Handling multiple operations in a single tool
 * - Returning structured JSON responses
 */
class DateCalculatorTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'date_calculator',
            'description' => 'Performs date and time calculations including timezone conversions, date differences, and formatting',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'operation' => [
                        'type' => 'string',
                        'enum' => ['current_time', 'days_between', 'format_date', 'day_of_week', 'timezone_convert'],
                        'description' => 'The operation to perform',
                    ],
                    'timezone' => [
                        'type' => 'string',
                        'description' => 'Timezone for current_time operation (e.g., America/New_York, Europe/London, Asia/Tokyo)',
                    ],
                    'from_date' => [
                        'type' => 'string',
                        'description' => 'Start date for days_between operation (YYYY-MM-DD format)',
                    ],
                    'to_date' => [
                        'type' => 'string',
                        'description' => 'End date for days_between operation (YYYY-MM-DD format)',
                    ],
                    'date' => [
                        'type' => 'string',
                        'description' => 'Date to format or get day of week (YYYY-MM-DD format)',
                    ],
                    'format' => [
                        'type' => 'string',
                        'description' => 'Date format for format_date operation (e.g., "F j, Y", "m/d/Y")',
                        'default' => 'F j, Y',
                    ],
                    'time' => [
                        'type' => 'string',
                        'description' => 'Time for timezone conversion (HH:MM format)',
                    ],
                    'from_timezone' => [
                        'type' => 'string',
                        'description' => 'Source timezone for conversion',
                    ],
                    'to_timezone' => [
                        'type' => 'string',
                        'description' => 'Target timezone for conversion',
                    ],
                ],
                'required' => ['operation'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $operation = $arguments['operation'] ?? '';

        try {
            switch ($operation) {
                case 'current_time':
                    return $this->getCurrentTime($arguments['timezone'] ?? 'UTC');

                case 'days_between':
                    return $this->getDaysBetween(
                        $arguments['from_date'] ?? '',
                        $arguments['to_date'] ?? ''
                    );

                case 'format_date':
                    return $this->formatDate(
                        $arguments['date'] ?? '',
                        $arguments['format'] ?? 'F j, Y'
                    );

                case 'day_of_week':
                    return $this->getDayOfWeek($arguments['date'] ?? '');

                case 'timezone_convert':
                    return $this->convertTimezone(
                        $arguments['time'] ?? '',
                        $arguments['from_timezone'] ?? 'UTC',
                        $arguments['to_timezone'] ?? 'UTC'
                    );

                default:
                    return json_encode(['error' => 'Invalid operation']);
            }
        } catch (\Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    private function getCurrentTime(string $timezone): string
    {
        try {
            $dt = new DateTime('now', new DateTimeZone($timezone));

            return json_encode([
                'timezone' => $timezone,
                'time' => $dt->format('H:i'),
                'date' => $dt->format('Y-m-d'),
                'datetime' => $dt->format('Y-m-d H:i:s'),
                'day' => $dt->format('l'),
                'formatted' => $dt->format('F j, Y g:i A'),
            ]);
        } catch (\Exception $e) {
            return json_encode(['error' => 'Invalid timezone: '.$timezone]);
        }
    }

    private function getDaysBetween(string $fromDate, string $toDate): string
    {
        try {
            $from = new DateTime($fromDate);
            $to = new DateTime($toDate);
            $diff = $from->diff($to);

            return json_encode([
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'days' => $diff->days,
                'is_future' => $diff->invert === 0,
                'formatted' => $diff->format('%a days'),
            ]);
        } catch (\Exception $e) {
            return json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
        }
    }

    private function formatDate(string $date, string $format): string
    {
        try {
            $dt = new DateTime($date);

            return json_encode([
                'original' => $date,
                'formatted' => $dt->format($format),
                'format_used' => $format,
            ]);
        } catch (\Exception $e) {
            return json_encode(['error' => 'Invalid date: '.$date]);
        }
    }

    private function getDayOfWeek(string $date): string
    {
        try {
            $dt = new DateTime($date);

            return json_encode([
                'date' => $date,
                'day_name' => $dt->format('l'),
                'day_number' => $dt->format('w'), // 0 = Sunday, 6 = Saturday
                'is_weekend' => in_array($dt->format('w'), ['0', '6']),
            ]);
        } catch (\Exception $e) {
            return json_encode(['error' => 'Invalid date: '.$date]);
        }
    }

    private function convertTimezone(string $time, string $fromTz, string $toTz): string
    {
        try {
            // Create datetime with today's date and the given time
            $dateStr = date('Y-m-d').' '.$time;
            $dt = new DateTime($dateStr, new DateTimeZone($fromTz));
            $dt->setTimezone(new DateTimeZone($toTz));

            return json_encode([
                'original_time' => $time,
                'original_timezone' => $fromTz,
                'converted_time' => $dt->format('H:i'),
                'converted_timezone' => $toTz,
                'converted_datetime' => $dt->format('Y-m-d H:i:s'),
                'time_difference' => $this->getTimeDifference($fromTz, $toTz),
            ]);
        } catch (\Exception $e) {
            return json_encode(['error' => 'Invalid timezone or time format']);
        }
    }

    private function getTimeDifference(string $tz1, string $tz2): string
    {
        $dt1 = new DateTime('now', new DateTimeZone($tz1));
        $dt2 = new DateTime('now', new DateTimeZone($tz2));

        $offset1 = $dt1->getOffset();
        $offset2 = $dt2->getOffset();
        $diff = ($offset2 - $offset1) / 3600;

        if ($diff > 0) {
            return '+'.$diff.' hours';
        } elseif ($diff < 0) {
            return $diff.' hours';
        } else {
            return 'Same time';
        }
    }
}
