<?php

namespace Vizra\VizraADK\Tests\Fixtures;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;

class TestAgent extends BaseLlmAgent
{
    protected string $name = 'test_agent';

    protected string $description = 'A test agent for testing';

    protected string $model = 'gemini-1.5-flash';

    public function run(mixed $input, AgentContext $context): mixed
    {
        return 'Test response: '.$input;
    }
}
