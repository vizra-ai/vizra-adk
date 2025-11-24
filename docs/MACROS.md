# Laravel Macros and Mixins Support

Vizra ADK now supports Laravel's powerful macros and mixins pattern, allowing you to extend the package's functionality with custom methods without modifying the core code.

## What are Macros?

Macros allow you to add custom methods to classes at runtime. This is useful for:
- Adding custom tracking or analytics
- Integrating with third-party services
- Adding domain-specific functionality
- Creating reusable patterns across your application

## Supported Classes

The following Vizra ADK classes support macros:

- `AgentManager` - Access via `Agent` facade
- `AgentBuilder` - Fluent builder interface
- `WorkflowManager` - Access via `Workflow` facade

## Basic Usage

### Registering a Macro

Register macros in your `AppServiceProvider::boot()` method:

```php
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Facades\Agent;
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // Add a tracking macro for analytics
    AgentBuilder::macro('track', function (Model $model) {
        $this->trackedModel = $model;
        return $this; // Return $this for method chaining
    });
}
```

### Using Your Macro

```php
use App\Models\Unit;
use Vizra\VizraADK\Facades\Agent;

// Use the macro in your application
$response = Agent::build(CustomerSupportAgent::class)
    ->track(Unit::find(12))
    ->forUser($user)
    ->go();
```

## Real-World Examples

### Analytics Tracking

Track which models are using AI features:

```php
use Vizra\VizraADK\Services\AgentBuilder;
use Illuminate\Database\Eloquent\Model;

AgentBuilder::macro('track', function (Model $model) {
    $this->trackedModel = $model;
    $this->trackedModelType = get_class($model);
    $this->trackedModelId = $model->getKey();
    return $this;
});

// Usage
Agent::build(MyAgent::class)
    ->track(Unit::find(12))
    ->go();
```

### Conditional Execution

Add conditional logic to your builder:

```php
AgentBuilder::macro('whenCondition', function ($condition, callable $callback) {
    if ($condition) {
        $callback($this);
    }
    return $this;
});

// Usage
Agent::define('conditional-agent')
    ->whenCondition($user->isPremium(), function ($builder) {
        $builder->model('gpt-4o');
    })
    ->whenCondition(!$user->isPremium(), function ($builder) {
        $builder->model('gpt-4o-mini');
    })
    ->go();
```

### Metadata Tagging

Add metadata for logging or debugging:

```php
AgentBuilder::macro('withTags', function (array $tags) {
    $this->tags = $tags;
    return $this;
});

AgentBuilder::macro('withPriority', function (string $priority) {
    $this->priority = $priority;
    return $this;
});

// Usage
Agent::define('support-agent')
    ->withTags(['customer-facing', 'urgent'])
    ->withPriority('high')
    ->go();
```

### Cost Tracking

Track estimated costs for budget monitoring:

```php
AgentBuilder::macro('trackCost', function (string $costCenter) {
    $this->costCenter = $costCenter;
    $this->trackTokenUsage = true;
    return $this;
});

// Usage
Agent::build(AnalyticsAgent::class)
    ->trackCost('DEPT-001')
    ->go();
```

## Using Mixins

Mixins allow you to add multiple macros at once:

```php
use Vizra\VizraADK\Services\AgentBuilder;

class AnalyticsMixin
{
    public function track()
    {
        return function (Model $model) {
            $this->trackedModel = $model;
            return $this;
        };
    }

    public function recordActivity()
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
}

// Register the mixin in your service provider
public function boot(): void
{
    AgentBuilder::mixin(new AnalyticsMixin());
}

// Use the mixed-in methods
Agent::define('analytics-agent')
    ->track($unit)
    ->recordActivity('agent_created')
    ->recordActivity('agent_configured')
    ->go();
```

## Advanced Patterns

### Integration with Events

Combine macros with Laravel events:

```php
AgentBuilder::macro('trackWithEvent', function (Model $model) {
    $this->trackedModel = $model;
    
    // Fire an event when the agent is used
    event(new AgentTracked($model, $this));
    
    return $this;
});
```

### Dynamic Configuration

Load configuration dynamically:

```php
AgentBuilder::macro('configureFor', function (Model $model) {
    // Load model-specific configuration
    $config = $model->agentConfiguration ?? [];
    
    if (isset($config['model'])) {
        $this->model($config['model']);
    }
    
    if (isset($config['instructions'])) {
        $this->instructions($config['instructions']);
    }
    
    return $this;
});

// Usage
Agent::build(MyAgent::class)
    ->configureFor($tenant)
    ->go();
```

### Workflow Macros

Add custom workflow types:

```php
use Vizra\VizraADK\Facades\Workflow;
use Vizra\VizraADK\Services\WorkflowManager;

WorkflowManager::macro('retryable', function (string $agentClass, int $maxRetries = 3) {
    $retries = $maxRetries;
    return Workflow::loop($agentClass)
        ->until(function ($result) use (&$retries) {
            return $result->success || $retries-- <= 0;
        });
});

// Usage
$result = Workflow::retryable(UnreliableAgent::class, 5)->go();
```

## Best Practices

1. **Always return `$this`** - This maintains the fluent interface
2. **Register in service providers** - Keep macro registration in `AppServiceProvider::boot()`
3. **Use descriptive names** - Make macro purpose clear from the name
4. **Document your macros** - Help other developers understand what they do
5. **Consider scope** - Macros are global, so namespace them if needed
6. **Test your macros** - Write tests to ensure they work as expected

## Testing Macros

```php
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Facades\Agent;

it('can track models with macro', function () {
    AgentBuilder::macro('track', function (Model $model) {
        $this->trackedModel = $model;
        return $this;
    });

    $model = Unit::factory()->create();
    $builder = Agent::define('test-agent')->track($model);

    expect($builder->trackedModel)->toBe($model);
});
```

## Learn More

- [Laravel Macros Documentation](https://laravel.com/docs/11.x/helpers#method-macro)
- [Laravel Macros and Mixins Article](https://coderden.dev/posts/laravel-macros-and-mixin)
- [Vizra ADK Documentation](https://vizra.ai/docs)
