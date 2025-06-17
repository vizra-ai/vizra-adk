<?php

use Vizra\VizraAdk\Livewire\ChatInterface;
use Livewire\Livewire;

test('chat interface loads correctly', function () {
    Livewire::test(ChatInterface::class)
        ->assertStatus(200)
        ->assertSee('Chat Interface')
        ->assertSee('Interactive conversations with your AI agents');
});

test('can open and close load session modal', function () {
    Livewire::test(ChatInterface::class)
        ->call('openLoadSessionModal')
        ->assertSet('showLoadSessionModal', true)
        ->assertSet('loadSessionId', '')
        ->call('closeLoadSessionModal')
        ->assertSet('showLoadSessionModal', false)
        ->assertSet('loadSessionId', '');
});

test('can load session from modal', function () {
    $testSessionId = 'test-session-123';

    Livewire::test(ChatInterface::class)
        ->call('openLoadSessionModal')
        ->set('loadSessionId', $testSessionId)
        ->call('loadSessionFromModal')
        ->assertSet('sessionId', $testSessionId)
        ->assertSet('showLoadSessionModal', false);
});

test('load session modal validates empty session id', function () {
    $originalSessionId = 'original-session';

    Livewire::test(ChatInterface::class)
        ->set('sessionId', $originalSessionId)
        ->call('openLoadSessionModal')
        ->set('loadSessionId', '')
        ->call('loadSessionFromModal')
        ->assertSet('sessionId', $originalSessionId) // Should remain unchanged
        ->assertSet('showLoadSessionModal', false); // Modal should close
});

test('can clear chat and generate new session id', function () {
    Livewire::test(ChatInterface::class)
        ->set('chatHistory', [
            ['role' => 'user', 'content' => 'test message', 'timestamp' => '12:00:00']
        ])
        ->call('clearChat')
        ->assertSet('chatHistory', [])
        ->assertCount('chatHistory', 0);
});
