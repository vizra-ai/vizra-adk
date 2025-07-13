<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Livewire\ChatInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Register a test agent for browser tests
    Agent::shouldReceive('getAllRegisteredAgents')
        ->andReturn(['test_browser_agent' => 'TestBrowserAgent']);
});

test('typing indicator appears when message is sent', function () {
    $component = Livewire::test(ChatInterface::class);
    
    // Set up component state
    $component->set('selectedAgent', 'test_browser_agent')
        ->set('message', 'Test typing indicator');
    
    // Send message
    $component->call('sendMessage');
    
    // Check that isLoading is true (which should show typing indicator)
    $component->assertSet('isLoading', true);
    
    // Check that the typing indicator would be rendered
    $component->assertSee('typing...');
})->skip('Requires browser testing environment for full DOM validation');

test('send button disabled during processing', function () {
    $component = Livewire::test(ChatInterface::class);
    
    // Set up component state
    $component->set('selectedAgent', 'test_browser_agent')
        ->set('message', 'Test button state');
    
    // Send message
    $component->call('sendMessage');
    
    // Verify loading state
    $component->assertSet('isLoading', true);
    
    // Note: In a full browser test, we would verify:
    // - Button has disabled attribute
    // - Button shows consistent "Send" text (no spinner)
    // - Input field functionality
})->skip('Requires browser testing environment for button state validation');

test('message input cleared immediately on send', function () {
    $component = Livewire::test(ChatInterface::class);
    
    // Set up component state  
    $component->set('selectedAgent', 'test_browser_agent')
        ->set('message', 'Message to be cleared');
    
    // Verify message is set
    $component->assertSet('message', 'Message to be cleared');
    
    // Send message
    $component->call('sendMessage');
    
    // Verify message is cleared immediately
    $component->assertSet('message', '');
});

test('no duplicate typing indicators shown', function () {
    $component = Livewire::test(ChatInterface::class);
    
    // Set up component state
    $component->set('selectedAgent', 'test_browser_agent')
        ->set('message', 'Test for duplicates');
    
    // Send message
    $component->call('sendMessage');
    
    // Verify only isLoading controls typing indicator
    $component->assertSet('isLoading', true);
    
    // In a full browser test, we would verify:
    // - Only one typing indicator is visible
    // - No green fallback indicator appears
    // - No button spinner conflicts with typing indicator
})->skip('Requires browser testing environment for visual validation');

test('typing indicator behavior with rapid messages', function () {
    $component = Livewire::test(ChatInterface::class)
        ->set('selectedAgent', 'test_browser_agent');
    
    // Send first message
    $component->set('message', 'First rapid message')
        ->call('sendMessage')
        ->assertSet('isLoading', true)
        ->assertSet('message', '');
    
    // Try to send second message while loading
    $component->set('message', 'Second rapid message')
        ->call('sendMessage');
    
    // Our current implementation processes both messages
    $component->assertSet('message', '') // Message gets cleared
        ->assertCount('chatHistory', 2); // Both user messages
    
    // Verify we're in loading state
    $component->assertSet('isLoading', true);
});

test('form submission behavior during loading', function () {
    $component = Livewire::test(ChatInterface::class);
    
    // Set up component state
    $component->set('selectedAgent', 'test_browser_agent')
        ->set('message', 'Test form submission');
    
    // Send message to enter loading state
    $component->call('sendMessage')
        ->assertSet('isLoading', true)
        ->assertSet('message', '');
    
    // Try to submit form again while loading
    $component->set('message', 'Another message while loading')
        ->call('sendMessage');
    
    // Our implementation processes both messages
    $component->assertCount('chatHistory', 2); // Both messages processed
});

test('typing indicator state consistency', function () {
    $component = Livewire::test(ChatInterface::class);
    
    // Initially not loading
    $component->assertSet('isLoading', false);
    
    // Set up and send message
    $component->set('selectedAgent', 'test_browser_agent')
        ->set('message', 'State consistency test')
        ->call('sendMessage')
        ->assertSet('isLoading', true);
    
    // Simulate processing completion
    $component->call('processAgentResponse', 'State consistency test')
        ->assertSet('isLoading', false);
    
    // Should be ready for next message
    $component->set('message', 'Next message')
        ->call('sendMessage')
        ->assertSet('isLoading', true);
});

test('typing indicator with empty agent selection', function () {
    $component = Livewire::test(ChatInterface::class);
    
    // Try to send message without selecting agent
    $component->set('selectedAgent', '') // No agent selected
        ->set('message', 'Message without agent')
        ->call('sendMessage');
    
    // Should not enter loading state
    $component->assertSet('isLoading', false)
        ->assertCount('chatHistory', 0);
});

test('typing indicator with empty message', function () {
    $component = Livewire::test(ChatInterface::class);
    
    // Store initial state
    $initialLoading = $component->get('isLoading');
    $initialHistory = $component->get('chatHistory');
    
    // Try to send empty message
    $component->set('selectedAgent', 'test_browser_agent')
        ->set('message', '') // Empty message
        ->call('sendMessage');
    
    // Should not change loading state
    $component->assertSet('isLoading', $initialLoading)
        ->assertCount('chatHistory', count($initialHistory));
    
    // Try with whitespace-only message
    $component->set('message', '   ') // Whitespace only
        ->call('sendMessage');
    
    // Should not change loading state
    $component->assertSet('isLoading', $initialLoading)
        ->assertCount('chatHistory', count($initialHistory));
});

// Note: These tests focus on the Livewire component state management.
// For full browser testing of the typing indicator visual behavior,
// you would need to use Laravel Dusk or similar browser testing tools.
// The tests above validate the core logic that drives the UI behavior.