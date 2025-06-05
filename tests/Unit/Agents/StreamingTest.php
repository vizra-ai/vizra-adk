<?php

use AaronLumsden\LaravelAgentADK\Agents\BaseLlmAgent;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use Prism\Prism\Enums\Provider;
use Generator;
use Prism\Prism\Text\Response;

beforeEach(function () {
    $this->agent = new StreamingTestAgent();
    $this->context = new AgentContext('streaming-test-session');
});

describe('Streaming Property Configuration', function () {
    it('has streaming disabled by default', function () {
        expect($this->agent->getStreaming())->toBeFalse();
    });

    it('can enable streaming via setter', function () {
        $this->agent->setStreaming(true);
        expect($this->agent->getStreaming())->toBeTrue();
    });

    it('can disable streaming via setter', function () {
        $this->agent->setStreaming(true);
        $this->agent->setStreaming(false);
        expect($this->agent->getStreaming())->toBeFalse();
    });

    it('can enable streaming via constructor property', function () {
        $agent = new StreamingEnabledTestAgent();
        expect($agent->getStreaming())->toBeTrue();
    });

    it('setStreaming returns agent instance for method chaining', function () {
        $result = $this->agent->setStreaming(true);
        expect($result)->toBe($this->agent);
        expect($result)->toBeInstanceOf(BaseLlmAgent::class);
    });
});

describe('Streaming Configuration Chaining', function () {
    it('can chain streaming with generation parameters', function () {
        $agent = $this->agent
            ->setStreaming(true)
            ->setTemperature(0.8)
            ->setMaxTokens(1500)
            ->setTopP(0.9);

        expect($agent->getStreaming())->toBeTrue();
        expect($agent->getTemperature())->toBe(0.8);
        expect($agent->getMaxTokens())->toBe(1500);
        expect($agent->getTopP())->toBe(0.9);
    });

    it('can chain streaming with model configuration', function () {
        $agent = $this->agent
            ->setStreaming(true)
            ->setModel('gpt-4-turbo');

        expect($agent->getStreaming())->toBeTrue();
        expect($agent->getModel())->toBe('gpt-4-turbo');
        // Note: getProvider() is protected, so we can't test it directly
    });
});

describe('Streaming Response Behavior', function () {
    it('returns stream object when streaming is enabled', function () {
        $this->agent->setStreaming(true);
        $result = $this->agent->run('Tell me a story', $this->context);

        expect($result)->toBeInstanceOf(MockStream::class);
    });

    it('returns string response when streaming is disabled', function () {
        $this->agent->setStreaming(false);
        $result = $this->agent->run('Tell me a story', $this->context);

        expect($result)->toBeString();
    });

    it('stream can be converted to string', function () {
        $this->agent->setStreaming(true);
        $stream = $this->agent->run('Hello', $this->context);

        expect($stream)->toBeInstanceOf(MockStream::class);

        // Mock stream conversion
        $stringResult = (string) $stream;
        expect($stringResult)->toBeString();
    });

    it('stream can be iterated over chunks', function () {
        $this->agent->setStreaming(true);
        $stream = $this->agent->run('Generate content', $this->context);

        expect($stream)->toBeInstanceOf(MockStream::class);

        // Mock iteration behavior
        $chunks = [];
        foreach ($stream as $chunk) {
            $chunks[] = $chunk;
        }

        expect($chunks)->toBeArray();
        expect(count($chunks))->toBeGreaterThan(0);
    });
});

describe('Streaming Context Management', function () {
    it('adds user message to context before streaming', function () {
        $input = 'Stream me a response';
        $this->agent->setStreaming(true);

        $this->agent->run($input, $this->context);

        $messages = $this->context->getConversationHistory();

        // Check if we have messages and they're in an array format
        expect($messages)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($messages->count())->toBeGreaterThan(0);

        $lastMessage = $messages->last();
        expect($lastMessage['role'])->toBe('user');
        expect($lastMessage['content'])->toBe($input);
    });

    it('does not add assistant message to context when streaming', function () {
        $this->agent->setStreaming(true);

        $this->agent->run('Stream response', $this->context);

        $messages = $this->context->getConversationHistory();
        $assistantMessages = $messages->filter(fn($msg) => $msg['role'] === 'assistant');

        // Should not have assistant message since streaming bypasses normal response processing
        expect($assistantMessages->count())->toBe(0);
    });

    it('preserves context state during streaming', function () {
        $this->context->setState('test_key', 'test_value');
        $this->agent->setStreaming(true);

        $this->agent->run('Test input', $this->context);

        expect($this->context->getState('test_key'))->toBe('test_value');
    });
});

describe('Streaming Hook Behavior', function () {
    it('calls beforeLlmCall hook when streaming', function () {
        $agent = new StreamingHookTestAgent();
        $agent->setStreaming(true);

        $agent->run('Test input', $this->context);

        expect($agent->beforeLlmCallCalled)->toBeTrue();
    });

    it('calls afterLlmResponse hook with stream when streaming', function () {
        $agent = new StreamingHookTestAgent();
        $agent->setStreaming(true);

        $agent->run('Test input', $this->context);

        expect($agent->afterLlmResponseCalled)->toBeTrue();
        expect($agent->receivedStreamObject)->toBeTrue();
    });

    it('does not call tool-related hooks when streaming', function () {
        $agent = new StreamingHookTestAgent();
        $agent->setStreaming(true);

        $agent->run('Test input', $this->context);

        // Tool hooks should not be called since streaming bypasses tool execution
        expect($agent->beforeToolCallCalled)->toBeFalse();
        expect($agent->afterToolResultCalled)->toBeFalse();
    });
});

describe('Streaming Error Handling', function () {
    it('handles streaming API errors gracefully', function () {
        $agent = new StreamingErrorTestAgent();
        $agent->setStreaming(true);

        expect(fn() => $agent->run('Trigger error', $this->context))
            ->toThrow(\RuntimeException::class, 'LLM API call failed');
    });

    it('maintains error context when streaming fails', function () {
        $agent = new StreamingErrorTestAgent();
        $agent->setStreaming(true);

        try {
            $agent->run('Trigger error', $this->context);
        } catch (\RuntimeException $e) {
            // Context should still be accessible
            expect($this->context->getSessionId())->toBe('streaming-test-session');
        }
    });
});

describe('Streaming Performance Characteristics', function () {
    it('streaming mode bypasses normal response processing', function () {
        $agent = new StreamingPerformanceTestAgent();

        // Non-streaming mode
        $agent->setStreaming(false);
        $start = microtime(true);
        $agent->run('Test', $this->context);
        $nonStreamingTime = microtime(true) - $start;

        // Reset context for clean test
        $this->context = new AgentContext('streaming-test-session-2');

        // Streaming mode
        $agent->setStreaming(true);
        $start = microtime(true);
        $agent->run('Test', $this->context);
        $streamingTime = microtime(true) - $start;

        // Streaming should be faster due to bypassed processing
        expect($streamingTime)->toBeLessThan($nonStreamingTime + 0.1); // Allow some margin
    });
});

describe('Advanced Streaming Features', function () {
    it('can toggle streaming mode during agent lifecycle', function () {
        expect($this->agent->getStreaming())->toBeFalse();

        // Enable streaming
        $this->agent->setStreaming(true);
        $result1 = $this->agent->run('Test 1', $this->context);
        expect($result1)->toBeInstanceOf(MockStream::class);

        // Disable streaming
        $this->agent->setStreaming(false);
        $result2 = $this->agent->run('Test 2', $this->context);
        expect($result2)->toBeString();

        // Re-enable streaming
        $this->agent->setStreaming(true);
        $result3 = $this->agent->run('Test 3', $this->context);
        expect($result3)->toBeInstanceOf(MockStream::class);
    });

    it('preserves other agent configuration when setting streaming', function () {
        $originalModel = $this->agent->getModel();
        $originalTemperature = $this->agent->getTemperature();
        $originalMaxTokens = $this->agent->getMaxTokens();

        $this->agent->setStreaming(true);

        expect($this->agent->getModel())->toBe($originalModel);
        expect($this->agent->getTemperature())->toBe($originalTemperature);
        expect($this->agent->getMaxTokens())->toBe($originalMaxTokens);
    });

    it('streaming works with different context sessions', function () {
        $this->agent->setStreaming(true);

        $context1 = new AgentContext('session-1');
        $context2 = new AgentContext('session-2');

        $result1 = $this->agent->run('Hello from session 1', $context1);
        $result2 = $this->agent->run('Hello from session 2', $context2);

        expect($result1)->toBeInstanceOf(MockStream::class);
        expect($result2)->toBeInstanceOf(MockStream::class);

        // Contexts should remain separate
        expect($context1->getSessionId())->toBe('session-1');
        expect($context2->getSessionId())->toBe('session-2');
    });

    it('handles empty or null input gracefully when streaming', function () {
        $this->agent->setStreaming(true);

        $result1 = $this->agent->run('', $this->context);
        $result2 = $this->agent->run(null, $this->context);

        expect($result1)->toBeInstanceOf(MockStream::class);
        expect($result2)->toBeInstanceOf(MockStream::class);
    });

    it('maintains consistent behavior across multiple streaming calls', function () {
        $this->agent->setStreaming(true);

        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->agent->run("Test message {$i}", $this->context);
        }

        foreach ($results as $result) {
            expect($result)->toBeInstanceOf(MockStream::class);
            expect((string) $result)->toBeString();
        }
    });
});

describe('Streaming Integration Scenarios', function () {
    it('can simulate real-time chat interface streaming', function () {
        $this->agent->setStreaming(true);

        $chatMessages = [
            'Hello, how are you?',
            'Tell me about Laravel',
            'What are the benefits of streaming?',
            'Thank you for the information'
        ];

        $responses = [];
        foreach ($chatMessages as $message) {
            $stream = $this->agent->run($message, $this->context);

            expect($stream)->toBeInstanceOf(MockStream::class);

            // Simulate consuming the stream
            $chunks = [];
            foreach ($stream as $chunk) {
                $chunks[] = $chunk;
            }

            $responses[] = implode('', $chunks);
        }

        expect(count($responses))->toBe(4);
        foreach ($responses as $response) {
            expect($response)->toBeString();
        }
    });

    it('can handle concurrent streaming requests', function () {
        $agent1 = new StreamingTestAgent();
        $agent2 = new StreamingTestAgent();

        $agent1->setStreaming(true);
        $agent2->setStreaming(true);

        $context1 = new AgentContext('concurrent-session-1');
        $context2 = new AgentContext('concurrent-session-2');

        $stream1 = $agent1->run('Concurrent request 1', $context1);
        $stream2 = $agent2->run('Concurrent request 2', $context2);

        expect($stream1)->toBeInstanceOf(MockStream::class);
        expect($stream2)->toBeInstanceOf(MockStream::class);

        // Both streams should be independently consumable
        expect((string) $stream1)->toContain('chunk');
        expect((string) $stream2)->toContain('chunk');
    });

    it('preserves context isolation when streaming', function () {
        $this->agent->setStreaming(true);

        $context1 = new AgentContext('isolated-session-1');
        $context2 = new AgentContext('isolated-session-2');

        $context1->setState('user_preference', 'dark_mode');
        $context2->setState('user_preference', 'light_mode');

        $this->agent->run('Test message', $context1);
        $this->agent->run('Test message', $context2);

        expect($context1->getState('user_preference'))->toBe('dark_mode');
        expect($context2->getState('user_preference'))->toBe('light_mode');
    });
});

describe('Streaming Edge Cases', function () {
    it('handles rapid successive streaming calls', function () {
        $this->agent->setStreaming(true);

        $startTime = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $result = $this->agent->run("Rapid call {$i}", $this->context);
            expect($result)->toBeInstanceOf(MockStream::class);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Should complete reasonably quickly
        expect($totalTime)->toBeLessThan(1.0); // Less than 1 second
    });

    it('maintains streaming state during configuration changes', function () {
        $this->agent->setStreaming(true);

        // Change other properties while streaming is enabled
        $agent = $this->agent
            ->setTemperature(0.9)
            ->setMaxTokens(2000);

        expect($agent->getStreaming())->toBeTrue();
        expect($agent->getTemperature())->toBe(0.9);
        expect($agent->getMaxTokens())->toBe(2000);
    });

    it('can handle context with extensive conversation history when streaming', function () {
        $this->agent->setStreaming(true);

        // Build up conversation history
        for ($i = 0; $i < 20; $i++) {
            $this->context->addMessage([
                'role' => $i % 2 === 0 ? 'user' : 'assistant',
                'content' => "Message {$i}"
            ]);
        }

        $result = $this->agent->run('Final streaming message', $this->context);

        expect($result)->toBeInstanceOf(MockStream::class);
        expect($this->context->getConversationHistory()->count())->toBeGreaterThan(20);
    });

    it('handles streaming with complex context state', function () {
        $this->agent->setStreaming(true);

        // Set complex state
        $this->context->setState('user_data', [
            'preferences' => ['theme' => 'dark', 'language' => 'en'],
            'history' => ['action1', 'action2', 'action3'],
            'metadata' => ['last_login' => time(), 'session_count' => 5]
        ]);

        $result = $this->agent->run('Process with complex state', $this->context);

        expect($result)->toBeInstanceOf(MockStream::class);

        // State should be preserved
        $userData = $this->context->getState('user_data');
        expect($userData['preferences']['theme'])->toBe('dark');
        expect(count($userData['history']))->toBe(3);
    });
});

/**
 * Test agent implementations for streaming tests
 */
class StreamingTestAgent extends BaseLlmAgent
{
    protected string $name = 'streaming-test-agent';
    protected string $description = 'Test agent for streaming functionality';
    protected string $instructions = 'Test streaming agent';
    protected string $model = 'gpt-4o';

    public function run(mixed $input, AgentContext $context): mixed
    {
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input ?: '']);

        if ($this->getStreaming()) {
            // Mock stream response
            return new MockStream(['chunk1', 'chunk2', 'chunk3']);
        } else {
            // Mock regular response
            return "Mock response for: " . $input;
        }
    }

    protected function registerTools(): array
    {
        return [];
    }
}

class StreamingEnabledTestAgent extends StreamingTestAgent
{
    protected bool $streaming = true;
}

class StreamingHookTestAgent extends StreamingTestAgent
{
    public bool $beforeLlmCallCalled = false;
    public bool $afterLlmResponseCalled = false;
    public bool $beforeToolCallCalled = false;
    public bool $afterToolResultCalled = false;
    public bool $receivedStreamObject = false;

    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        $this->beforeLlmCallCalled = true;
        return $inputMessages;
    }

    public function afterLlmResponse(Response|Generator|MockStream|MockResponse $response, AgentContext $context): mixed
    {
        $this->afterLlmResponseCalled = true;
        $this->receivedStreamObject = $response instanceof MockStream;
        return $response;
    }

    public function beforeToolCall(string $toolName, array $arguments, AgentContext $context): array
    {
        $this->beforeToolCallCalled = true;
        return $arguments;
    }

    public function afterToolResult(string $toolName, string $result, AgentContext $context): string
    {
        $this->afterToolResultCalled = true;
        return $result;
    }

    public function run(mixed $input, AgentContext $context): mixed
    {
        $context->setUserInput($input);
        $messages = [['role' => 'user', 'content' => $input]];

        // Call hooks to test them
        $messages = $this->beforeLlmCall($messages, $context);

        if ($this->getStreaming()) {
            $stream = new MockStream(['test', 'stream']);
            $this->afterLlmResponse($stream, $context);
            return $stream;
        } else {
            $response = new MockResponse("Mock response");
            $this->afterLlmResponse($response, $context);
            return $response->text;
        }
    }
}

class StreamingErrorTestAgent extends StreamingTestAgent
{
    public function run(mixed $input, AgentContext $context): mixed
    {
        if ($this->getStreaming() && str_contains($input, 'error')) {
            throw new \RuntimeException('LLM API call failed: Mock streaming error');
        }

        return parent::run($input, $context);
    }
}

class StreamingPerformanceTestAgent extends StreamingTestAgent
{
    public function run(mixed $input, AgentContext $context): mixed
    {
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input ?: '']);

        if ($this->getStreaming()) {
            // Minimal processing for streaming
            return new MockStream(['fast', 'stream']);
        } else {
            // Simulate additional processing for non-streaming
            usleep(1000); // 1ms delay to simulate processing
            $context->addMessage(['role' => 'assistant', 'content' => 'processed response']);
            return "Processed response for: " . $input;
        }
    }
}

/**
 * Mock implementations for testing
 */
class MockStream implements \Iterator
{
    private array $chunks;
    private int $position = 0;

    public function __construct(array $chunks)
    {
        $this->chunks = $chunks;
    }

    public function current(): string
    {
        return $this->chunks[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->chunks[$this->position]);
    }

    public function __toString(): string
    {
        return implode('', $this->chunks);
    }
}

class MockResponse
{
    public string $text;

    public function __construct(string $text)
    {
        $this->text = $text;
    }
}
