<?php

use Vizra\VizraSdk\Events\MemoryUpdated;
use Vizra\VizraSdk\Models\AgentMemory;
use Vizra\VizraSdk\Models\AgentSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
});

it('can create memory updated event', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'memory_summary' => 'Test summary',
        'memory_data' => ['key' => 'value'],
        'key_learnings' => ['learning 1'],
        'total_sessions' => 1
    ]);

    $event = new MemoryUpdated($memory, null, 'learning_added');

    expect($event)->toBeInstanceOf(MemoryUpdated::class);
    expect($event->memory)->toBe($memory);
    expect($event->session)->toBeNull();
    expect($event->updateType)->toBe('learning_added');
});

it('has public properties', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent'
    ]);

    $session = AgentSession::create([
        'agent_name' => 'test-agent',
        'agent_memory_id' => $memory->id
    ]);

    $event = new MemoryUpdated($memory, $session, 'fact_added');

    // Properties should be public for event listeners
    expect(property_exists($event, 'memory'))->toBeTrue();
    expect(property_exists($event, 'session'))->toBeTrue();
    expect(property_exists($event, 'updateType'))->toBeTrue();

    expect($event->memory)->toBeInstanceOf(AgentMemory::class);
    expect($event->session)->toBeInstanceOf(AgentSession::class);
    expect($event->updateType)->toBe('fact_added');
});

it('accepts different update types', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent'
    ]);

    $updateTypes = [
        'learning_added',
        'fact_added',
        'summary_updated',
        'session_incremented',
        'session_summarized'
    ];

    foreach ($updateTypes as $updateType) {
        $event = new MemoryUpdated($memory, null, $updateType);
        expect($event->updateType)->toBe($updateType);
    }
});

it('can be serialized and unserialized', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'memory_summary' => 'Serialization test',
        'memory_data' => ['serialized' => true],
        'key_learnings' => ['Can be serialized'],
        'total_sessions' => 5
    ]);

    $event = new MemoryUpdated($memory, null, 'testing');

    // Serialize the event
    $serialized = serialize($event);
    expect($serialized)->toBeString();

    // Unserialize the event
    $unserialized = unserialize($serialized);
    expect($unserialized)->toBeInstanceOf(MemoryUpdated::class);
    expect($unserialized->memory->agent_name)->toBe('test-agent');
    expect($unserialized->updateType)->toBe('testing');
});

it('provides access to memory data through event', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'event-test-agent',
        'memory_summary' => 'Event access test',
        'memory_data' => ['accessible' => 'through_event'],
        'key_learnings' => ['Event provides memory access'],
        'total_sessions' => 3
    ]);

    $event = new MemoryUpdated($memory, null, 'access_test');

    // Should be able to access all memory properties through the event
    expect($event->memory->agent_name)->toBe('event-test-agent');
    expect($event->memory->memory_summary)->toBe('Event access test');
    expect($event->memory->memory_data['accessible'])->toBe('through_event');
    expect($event->memory->key_learnings)->toContain('Event provides memory access');
    expect($event->memory->total_sessions)->toBe(3);
});

it('can handle memory with null values', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'null-test-agent',
        'memory_summary' => null,
        'memory_data' => [],
        'key_learnings' => [],
        'total_sessions' => 0
    ]);

    $event = new MemoryUpdated($memory, null, 'null_handling');

    expect($event->memory->memory_summary)->toBeNull();
    expect($event->memory->memory_data)->toBe([]);
    expect($event->memory->key_learnings)->toBe([]);
    expect($event->memory->total_sessions)->toBe(0);
    expect($event->updateType)->toBe('null_handling');
});

it('maintains memory relationships through event', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'relationship-test-agent'
    ]);

    // Create a session associated with the memory
    $session = AgentSession::create([
        'agent_name' => 'relationship-test-agent',
        'agent_memory_id' => $memory->id
    ]);

    $event = new MemoryUpdated($memory, $session, 'relationship_test');

    // Should be able to access relationships through the event
    expect($event->memory->sessions)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($event->memory->sessions)->toHaveCount(1);
    expect($event->memory->sessions->first()->id)->toBe($session->id);
});

it('can be used with event listeners', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'listener-test-agent'
    ]);

    $eventFired = false;
    $capturedMemory = null;
    $capturedUpdateType = null;

    // Mock event listener
    \Illuminate\Support\Facades\Event::listen(MemoryUpdated::class, function ($event) use (&$eventFired, &$capturedMemory, &$capturedUpdateType) {
        $eventFired = true;
        $capturedMemory = $event->memory;
        $capturedUpdateType = $event->updateType;
    });

    // Fire the event
    event(new MemoryUpdated($memory, null, 'listener_test'));

    expect($eventFired)->toBeTrue();
    expect($capturedMemory)->toBeInstanceOf(AgentMemory::class);
    expect($capturedMemory->agent_name)->toBe('listener-test-agent');
    expect($capturedUpdateType)->toBe('listener_test');
});
