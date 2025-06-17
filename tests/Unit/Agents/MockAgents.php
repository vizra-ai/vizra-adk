<?php

namespace Vizra\VizraADK\Tests\Unit\Agents;

use Vizra\VizraADK\Agents\BaseLlmAgent;

// Mock agent classes for testing workflows

class SingleAgent extends BaseLlmAgent {
    protected string $name = 'single_agent';
}

class FirstAgent extends BaseLlmAgent {
    protected string $name = 'first_agent';
}

class SecondAgent extends BaseLlmAgent {
    protected string $name = 'second_agent';
}

class ThirdAgent extends BaseLlmAgent {
    protected string $name = 'third_agent';
}

class PremiumAgent extends BaseLlmAgent {
    protected string $name = 'premium_agent';
}

class HighScoreAgent extends BaseLlmAgent {
    protected string $name = 'high_score_agent';
}

class DefaultAgent extends BaseLlmAgent {
    protected string $name = 'default_agent';
}

class TestAgent extends BaseLlmAgent {
    protected string $name = 'test_agent';
}

class ActiveAgent extends BaseLlmAgent {
    protected string $name = 'active_agent';
}

class InactiveAgent extends BaseLlmAgent {
    protected string $name = 'inactive_agent';
}

class GoldAgent extends BaseLlmAgent {
    protected string $name = 'gold_agent';
}

class MinorAgent extends BaseLlmAgent {
    protected string $name = 'minor_agent';
}

class AdultAgent extends BaseLlmAgent {
    protected string $name = 'adult_agent';
}

class EmailAgent extends BaseLlmAgent {
    protected string $name = 'email_agent';
}

class NoEmailAgent extends BaseLlmAgent {
    protected string $name = 'no_email_agent';
}

class NoDescriptionAgent extends BaseLlmAgent {
    protected string $name = 'no_description_agent';
}

class HasDescriptionAgent extends BaseLlmAgent {
    protected string $name = 'has_description_agent';
}

class ValidEmailAgent extends BaseLlmAgent {
    protected string $name = 'valid_email_agent';
}

class InvalidEmailAgent extends BaseLlmAgent {
    protected string $name = 'invalid_email_agent';
}

class LowScoreAgent extends BaseLlmAgent {
    protected string $name = 'low_score_agent';
}

class StringAgent extends BaseLlmAgent {
    protected string $name = 'string_agent';
}

class CounterAgent extends BaseLlmAgent {
    protected string $name = 'counter_agent';
}

class ProcessItemAgent extends BaseLlmAgent {
    protected string $name = 'process_item_agent';
}

class InfiniteAgent extends BaseLlmAgent {
    protected string $name = 'infinite_agent';
}

class FailingAgent extends BaseLlmAgent {
    protected string $name = 'failing_agent';
}

class SometimesFailingAgent extends BaseLlmAgent {
    protected string $name = 'sometimes_failing_agent';
}

class CleanupAgent extends BaseLlmAgent {
    protected string $name = 'cleanup_agent';
}