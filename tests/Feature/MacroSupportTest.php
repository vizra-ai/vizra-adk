<?php

use Illuminate\Database\Eloquent\Model;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Facades\Workflow;
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\WorkflowManager;

// Test that AgentManager supports macros
it('can register and use macros on AgentManager', function () {
    // Register a macro
    AgentManager::macro('testMethod', function () {
        return 'macro works';
    });

    // Access through the facade
    $result = Agent::testMethod();

    expect($result)->toBe('macro works');
});

// Test that AgentBuilder supports macros
it('can register and use macros on AgentBuilder', function () {
    // Register a macro that returns $this for chaining
    AgentBuilder::macro('customConfig', function ($value) {
        $this->customValue = $value;
        return $this;
    });

    // Use the macro through agent building
    $builder = Agent::define('test-agent')
        ->instructions('Test instructions')
        ->customConfig('test-value');

    expect($builder)->toBeInstanceOf(AgentBuilder::class);
});

// Test that WorkflowManager supports macros
it('can register and use macros on WorkflowManager', function () {
    // Register a macro
    WorkflowManager::macro('customWorkflow', function () {
        return 'custom workflow';
    });

    // Access through the facade
    $result = Workflow::customWorkflow();

    expect($result)->toBe('custom workflow');
});

// Test the track() macro use case for analytics
it('can register a track macro for model association', function () {
    // Create a mock model
    $model = Mockery::mock(Model::class);
    $model->shouldReceive('getKey')->andReturn(12);

    // Register the track macro on AgentBuilder
    AgentBuilder::macro('track', function (Model $model) {
        $this->trackedModel = $model;
        return $this;
    });

    // Use the macro
    $builder = Agent::define('analytics-agent')
        ->instructions('Test analytics')
        ->track($model);

    expect($builder)->toBeInstanceOf(AgentBuilder::class)
        ->and($builder->trackedModel)->toBe($model);
});

// Test that macros can access protected properties
it('allows macros to access and modify builder state', function () {
    // Register a macro that accesses builder properties
    AgentBuilder::macro('withMetadata', function (array $metadata) {
        if (!property_exists($this, 'metadata')) {
            $this->metadata = [];
        }
        $this->metadata = array_merge($this->metadata ?? [], $metadata);
        return $this;
    });

    // Use the macro
    $builder = Agent::define('meta-agent')
        ->withMetadata(['source' => 'api', 'version' => '1.0']);

    expect($builder->metadata)->toBe(['source' => 'api', 'version' => '1.0']);
});

// Test that multiple macros can be chained
it('allows chaining multiple macros together', function () {
    // Register multiple macros
    AgentBuilder::macro('withTags', function (array $tags) {
        $this->tags = $tags;
        return $this;
    });

    AgentBuilder::macro('withPriority', function (string $priority) {
        $this->priority = $priority;
        return $this;
    });

    // Chain them together
    $builder = Agent::define('tagged-agent')
        ->withTags(['important', 'customer-facing'])
        ->withPriority('high');

    expect($builder->tags)->toBe(['important', 'customer-facing'])
        ->and($builder->priority)->toBe('high');
});

// Test that macros persist across multiple instances
it('makes macros available to all instances', function () {
    // Register a macro
    AgentBuilder::macro('enableDebug', function () {
        $this->debugMode = true;
        return $this;
    });

    // Create multiple instances and use the macro
    $builder1 = Agent::define('agent-1')->enableDebug();
    $builder2 = Agent::define('agent-2')->enableDebug();

    expect($builder1->debugMode)->toBeTrue()
        ->and($builder2->debugMode)->toBeTrue();
});

// Test mixin functionality
it('can use mixins to add multiple macros at once', function () {
    // Create a mixin class
    $mixin = new class {
        public function logActivity()
        {
            return function ($activity) {
                $this->activityLog[] = $activity;
                return $this;
            };
        }

        public function getActivityLog()
        {
            return function () {
                return $this->activityLog ?? [];
            };
        }
    };

    // Register the mixin
    AgentBuilder::mixin($mixin);

    // Use the mixed-in methods
    $builder = Agent::define('logging-agent')
        ->logActivity('created')
        ->logActivity('configured');

    expect($builder->getActivityLog())->toBe(['created', 'configured']);
});

// Test that macros work with the facade
it('can call macros through the Agent facade', function () {
    // Register a macro on AgentManager
    AgentManager::macro('version', function () {
        return '1.0.0';
    });

    // Call through the facade
    $version = Agent::version();

    expect($version)->toBe('1.0.0');
});

// Test conditional macro behavior
it('can create conditional macros', function () {
    // Register a conditional macro
    AgentBuilder::macro('whenCondition', function ($condition, callable $callback) {
        if ($condition) {
            $callback($this);
        }
        return $this;
    });

    $model = Mockery::mock(Model::class);

    // Test with condition true
    $executedTrue = false;
    $builder1 = Agent::define('agent-1')
        ->whenCondition(true, function ($builder) use (&$executedTrue, $model) {
            $builder->trackedModelTrue = $model;
            $executedTrue = true;
        });

    expect($executedTrue)->toBeTrue()
        ->and($builder1->trackedModelTrue)->toBe($model);

    // Test with condition false
    $executedFalse = false;
    $builder2 = Agent::define('agent-2')
        ->whenCondition(false, function ($builder) use (&$executedFalse) {
            $builder->trackedModelFalse = 'should not execute';
            $executedFalse = true;
        });

    expect($executedFalse)->toBeFalse()
        ->and(property_exists($builder2, 'trackedModelFalse'))->toBeFalse();
});
