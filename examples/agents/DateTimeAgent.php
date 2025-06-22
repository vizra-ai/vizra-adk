<?php

namespace Vizra\VizraADK\Examples\agents;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Examples\tools\DateCalculatorTool;

/**
 * Example agent that helps with date and time related questions.
 *
 * This agent demonstrates:
 * - Basic agent setup with a practical use case
 * - Using a custom tool for calculations
 * - Handling various types of date/time queries
 */
class DateTimeAgent extends BaseLlmAgent
{
    protected string $name = 'datetime';

    protected string $description = 'Helps with date, time, and timezone related questions';

    protected string $instructions = 'You are a helpful assistant that specializes in date and time queries.
You can:
- Tell the current time in any timezone
- Calculate the number of days between dates
- Convert times between timezones
- Format dates in different ways
- Tell what day of the week a specific date falls on

When users ask about dates or times, use the date_calculator tool to get accurate information.
Always be clear about timezones when discussing times. If a timezone is not specified, ask for clarification.';

    protected string $model = 'gemini-2.0-flash';

    protected ?float $temperature = 0.3; // Lower temperature for more consistent date/time responses

    /**
     * Tools this agent can use.
     *
     * @var array<class-string<ToolInterface>>
     */
    protected array $tools = [
        DateCalculatorTool::class,
    ];
}
