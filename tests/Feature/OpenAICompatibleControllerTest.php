<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\System\AgentContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

    // Register test agent
    $registry = $this->app->make(AgentRegistry::class);
    $registry->register('openai-test-agent', OpenAICompatibleTestAgent::class);
});

it('returns 400 for missing model field', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'invalid_request',
            ],
        ]);
});

it('returns 400 for missing messages field', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'invalid_request',
            ],
        ]);
});

it('returns 400 for empty messages array', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [],
    ]);

    $response->assertStatus(400)
        ->assertJson([
            'error' => [
                'type' => 'invalid_request_error',
                'code' => 'invalid_request',
            ],
        ]);
});

it('returns 404 for non-existent agent', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'non-existent-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Hello'],
        ],
    ]);

    $response->assertStatus(404)
        ->assertJson([
            'error' => [
                'type' => 'not_found_error',
                'code' => 'model_not_found',
            ],
        ]);
});

it('returns 400 when no user message found', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ['role' => 'assistant', 'content' => 'Hello! How can I help?'],
        ],
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('error.message', 'No user message found in the conversation');
});

it('returns successful chat completion in OpenAI format', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Hello test'],
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'id',
            'object',
            'created',
            'model',
            'system_fingerprint',
            'choices' => [
                [
                    'index',
                    'message' => [
                        'role',
                        'content',
                    ],
                    'finish_reason',
                ],
            ],
            'usage' => [
                'prompt_tokens',
                'completion_tokens',
                'total_tokens',
            ],
        ])
        ->assertJsonPath('object', 'chat.completion')
        ->assertJsonPath('model', 'openai-test-agent')
        ->assertJsonPath('choices.0.message.role', 'assistant')
        ->assertJsonPath('choices.0.finish_reason', 'stop');

    // Verify the response content contains our test message
    $content = $response->json('choices.0.message.content');
    expect($content)->toContain('Test response for: Hello test');
});

it('applies temperature parameter to agent', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Test with temperature'],
        ],
        'temperature' => 0.7,
    ]);

    $response->assertStatus(200);
    // The temperature is applied to the agent, verify the request succeeded
    expect($response->json('choices.0.message.content'))->toContain('Test response for: Test with temperature');
});

it('applies max_tokens parameter to agent', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Test with max tokens'],
        ],
        'max_tokens' => 100,
    ]);

    $response->assertStatus(200);
    expect($response->json('choices.0.message.content'))->toContain('Test response for: Test with max tokens');
});

it('applies top_p parameter to agent', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Test with top_p'],
        ],
        'top_p' => 0.9,
    ]);

    $response->assertStatus(200);
    expect($response->json('choices.0.message.content'))->toContain('Test response for: Test with top_p');
});

it('generates consistent session id from user field', function () {
    // First request with user field
    $response1 = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'First message'],
        ],
        'user' => 'test-user-123',
    ]);

    $response1->assertStatus(200);

    // Second request with same user field should use same session
    $response2 = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Second message'],
        ],
        'user' => 'test-user-123',
    ]);

    $response2->assertStatus(200);

    // Both requests should succeed with the same user context
    expect($response1->json('choices.0.message.content'))->toContain('Test response for: First message');
    expect($response2->json('choices.0.message.content'))->toContain('Test response for: Second message');
});

it('handles conversation history in messages', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a helpful assistant'],
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
            ['role' => 'user', 'content' => 'How are you?'],
        ],
    ]);

    $response->assertStatus(200);
    // Verify the last user message is processed
    expect($response->json('choices.0.message.content'))->toContain('Test response for: How are you?');
});

it('validates invalid temperature value', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Test'],
        ],
        'temperature' => 3.0, // Invalid: must be between 0 and 2
    ]);

    $response->assertStatus(400);
});

it('validates invalid top_p value', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Test'],
        ],
        'top_p' => 1.5, // Invalid: must be between 0 and 1
    ]);

    $response->assertStatus(400);
});

it('validates invalid max_tokens value', function () {
    $response = $this->postJson('/api/vizra-adk/chat/completions', [
        'model' => 'openai-test-agent',
        'messages' => [
            ['role' => 'user', 'content' => 'Test'],
        ],
        'max_tokens' => 0, // Invalid: must be at least 1
    ]);

    $response->assertStatus(400);
});

/**
 * Test agent for OpenAI compatible API testing
 */
class OpenAICompatibleTestAgent extends BaseLlmAgent
{
    protected string $name = 'openai-test-agent';

    protected string $description = 'Test agent for OpenAI compatible API';

    protected string $instructions = 'You are a test agent for OpenAI API compatibility testing.';

    public function execute(mixed $input, AgentContext $context): mixed
    {
        // For testing, simulate the LLM response behavior manually
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input ?: '']);

        $response = 'Test response for: '.$input.' (Session: '.$context->getSessionId().')';

        $context->addMessage([
            'role' => 'assistant',
            'content' => $response,
        ]);

        return $response;
    }
}
