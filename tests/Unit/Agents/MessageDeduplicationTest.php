<?php

namespace Tests\Unit\Agents;

use Prism\Prism\ValueObjects\Messages\UserMessage;
use Tests\TestCase;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;

class TestableAgentForMessageDedup extends BaseLlmAgent
{
    protected string $name = 'test_message_dedup_agent';
    protected string $description = 'Test agent for message deduplication';
    protected string $instructions = 'Test instructions';
    protected string $model = 'gpt-4o-mini';
    protected ?string $provider = 'openai';
    public bool $includeConversationHistory = true; // Made public for testing

    // Make this method public for testing
    public function prepareMessagesForPrism(AgentContext $context): array
    {
        return parent::prepareMessagesForPrism($context);
    }

    // Public method to test message preparation during execute
    public function getMessagesForTesting(AgentContext $context, string $input, array $images = [], array $documents = []): array
    {
        // This simulates what happens in the execute method

        // Step 1: Prepare messages from context (like line 747)
        $messages = $this->prepareMessagesForPrism($context);

        // Step 2: Add current user input (like lines 749-761)
        $additionalContent = [];
        if (!empty($images)) {
            $additionalContent = array_merge($additionalContent, $images);
        }
        if (!empty($documents)) {
            $additionalContent = array_merge($additionalContent, $documents);
        }

        if (!empty($input) || !empty($additionalContent)) {
            $currentMessage = new UserMessage($input ?: '', $additionalContent);
            $messages[] = $currentMessage;
        }

        return $messages;
    }
}

test('user message is not duplicated during execution', function () {
    $agent = new TestableAgentForMessageDedup();

    // Create a fresh context
    $context = new AgentContext('test-session-1', 'Test input message');

    // Get the messages that would be sent to the LLM
    $messages = $agent->getMessagesForTesting($context, 'Test input message');

    // Count how many times the exact input appears
    $inputCount = 0;
    foreach ($messages as $message) {
        if ($message instanceof UserMessage && $message->content === 'Test input message') {
            $inputCount++;
        }
    }

    // The input should appear exactly once
    expect($inputCount)->toBe(1);
});

test('conversation history plus current message are handled correctly', function () {
    $agent = new TestableAgentForMessageDedup();

    // Create context with history
    $context = new AgentContext('test-session-2', null);

    // Enable history for this context
    $context->setState('include_history', true);

    // Add some conversation history with proper non-empty content
    $context->addMessage(['role' => 'user', 'content' => 'First question']);
    $context->addMessage(['role' => 'assistant', 'content' => 'First answer']);
    $context->addMessage(['role' => 'user', 'content' => 'Second question']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Second answer']);

    // Now simulate a new user input
    $messages = $agent->getMessagesForTesting($context, 'Current question');

    // Count all user messages
    $userMessages = [];
    foreach ($messages as $message) {
        if ($message instanceof UserMessage) {
            $userMessages[] = $message->content;
        }
    }

    // Debug: Check what messages we actually have
    // dump('Total messages: ' . count($messages));
    // dump('User messages: ', $userMessages);

    // The main test: current message should appear exactly once
    $currentMessageCount = count(array_filter($userMessages, fn($m) => $m === 'Current question'));
    expect($currentMessageCount)->toBe(1);

    // If history is included, it should also not be duplicated
    if (count($userMessages) > 1) {
        expect($userMessages)->toContain('First question');
        expect($userMessages)->toContain('Second question');
    }

    // Each message should appear exactly once
    $uniqueMessages = array_unique($userMessages);
    expect(count($uniqueMessages))->toBe(count($userMessages));
});

test('empty input does not create duplicate empty messages', function () {
    $agent = new TestableAgentForMessageDedup();

    // Create context with empty input
    $context = new AgentContext('test-session-3', '');

    // Get messages
    $messages = $agent->getMessagesForTesting($context, '');

    // Count empty user messages
    $emptyUserMessages = 0;
    foreach ($messages as $message) {
        if ($message instanceof UserMessage && $message->content === '') {
            $emptyUserMessages++;
        }
    }

    // Should have at most one empty message (or none)
    expect($emptyUserMessages)->toBeLessThanOrEqual(1);
});

test('context without history works correctly', function () {
    $agent = new TestableAgentForMessageDedup();

    // Disable conversation history
    $agent->includeConversationHistory = false;

    // Create context
    $context = new AgentContext('test-session-4', null);
    $context->setState('include_history', false);

    // Add some history that should be ignored
    $context->addMessage(['role' => 'user', 'content' => 'Old message']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Old response']);

    // Get messages with new input
    $messages = $agent->getMessagesForTesting($context, 'New message');

    // Should only have the new message, not the history
    $userMessages = [];
    foreach ($messages as $message) {
        if ($message instanceof UserMessage) {
            $userMessages[] = $message->content;
        }
    }

    expect($userMessages)->not->toContain('Old message');
    expect($userMessages)->toContain('New message');
    expect(count(array_filter($userMessages, fn($m) => $m === 'New message')))->toBe(1);
});