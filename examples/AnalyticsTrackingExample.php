<?php

namespace Vizra\VizraADK\Examples;

use Illuminate\Database\Eloquent\Model;
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Facades\Agent;

/**
 * Example: Analytics Tracking with Macros
 * 
 * This example demonstrates how to use Laravel macros to add
 * model tracking functionality to Vizra ADK agents for analytics purposes.
 * 
 * Usage:
 * 1. Register the macro in your AppServiceProvider::boot() method
 * 2. Use the track() method when building agents
 * 3. Access the tracked model data in your analytics system
 */
class AnalyticsTrackingExample
{
    /**
     * Register the analytics tracking macros
     * 
     * Call this in your AppServiceProvider::boot() method
     */
    public static function registerMacros(): void
    {
        /**
         * Track a model for analytics purposes
         * 
         * This macro stores a reference to a model instance, allowing you
         * to associate token usage, execution time, and other metrics
         * with specific models in your application.
         * 
         * @param Model $model The model to track (e.g., Unit, Tenant, User)
         * @return AgentBuilder Returns $this for method chaining
         */
        AgentBuilder::macro('track', function (Model $model) {
            // Store the model for later retrieval
            $this->trackedModel = $model;
            $this->trackedModelType = get_class($model);
            $this->trackedModelId = $model->getKey();
            
            return $this;
        });

        /**
         * Track with additional context
         * 
         * @param Model $model The model to track
         * @param array $context Additional context data
         * @return AgentBuilder Returns $this for method chaining
         */
        AgentBuilder::macro('trackWithContext', function (Model $model, array $context = []) {
            $this->trackedModel = $model;
            $this->trackedModelType = get_class($model);
            $this->trackedModelId = $model->getKey();
            $this->trackingContext = $context;
            
            return $this;
        });

        /**
         * Set a cost center for budget tracking
         * 
         * @param string $costCenter The cost center identifier
         * @return AgentBuilder Returns $this for method chaining
         */
        AgentBuilder::macro('costCenter', function (string $costCenter) {
            $this->costCenter = $costCenter;
            
            return $this;
        });
    }

    /**
     * Example 1: Basic model tracking
     */
    public static function basicTracking()
    {
        // Assuming you have a Unit model
        $unit = self::getExampleUnit();

        // First, register the macro to enable tracking
        AgentBuilder::macro('track', function ($model) {
            $this->trackedModel = $model;
            return $this;
        });

        // Register the agent with tracking
        Agent::build(\Vizra\VizraADK\Examples\agents\PersonalShoppingAssistantAgent::class)
            ->track($unit)
            ->register();

        // Now run the agent (uses the AgentExecutor API)
        $response = \Vizra\VizraADK\Examples\agents\PersonalShoppingAssistantAgent::run('Help me find a gift')
            ->forUser(auth()->user())
            ->go();

        // The tracked model can be accessed later for analytics
        // (You would typically do this in an event listener or middleware)
        return $response;
    }

    /**
     * Example 2: Track with additional context
     */
    public static function trackingWithContext()
    {
        $unit = self::getExampleUnit();

        // Register the macro if not already registered
        AgentBuilder::macro('trackWithContext', function ($model, $context) {
            $this->trackedModel = $model;
            $this->trackingContext = $context;
            return $this;
        });

        // Register the agent with context tracking
        Agent::build(\Vizra\VizraADK\Examples\agents\PersonalShoppingAssistantAgent::class)
            ->trackWithContext($unit, [
                'department' => 'Sales',
                'campaign' => 'Q4-2024',
                'feature' => 'shopping_assistant',
            ])
            ->register();

        // Run the agent
        $response = \Vizra\VizraADK\Examples\agents\PersonalShoppingAssistantAgent::run('Show me popular products')
            ->forUser(auth()->user())
            ->go();

        return $response;
    }

    /**
     * Example 3: Cost center tracking for budget monitoring
     */
    public static function costCenterTracking()
    {
        $unit = self::getExampleUnit();

        // Register macros for tracking
        AgentBuilder::macro('track', function ($model) {
            $this->trackedModel = $model;
            return $this;
        });

        AgentBuilder::macro('costCenter', function ($costCenter) {
            $this->costCenter = $costCenter;
            return $this;
        });

        // Register the agent with cost center tracking
        Agent::build(\Vizra\VizraADK\Examples\agents\PersonalShoppingAssistantAgent::class)
            ->track($unit)
            ->costCenter('DEPT-SALES-001')
            ->register();

        // Run the agent
        $response = \Vizra\VizraADK\Examples\agents\PersonalShoppingAssistantAgent::run('What can you help me with?')
            ->forUser(auth()->user())
            ->go();

        return $response;
    }

    /**
     * Example 4: Using tracked data in an event listener
     */
    public static function exampleEventListener()
    {
        // In a real application, you would register this listener in EventServiceProvider
        // This shows how you might access the tracked data

        return <<<'PHP'
<?php

namespace App\Listeners;

use Vizra\VizraADK\Events\AgentExecutionFinished;
use App\Models\AgentUsageLog;

class TrackAgentUsage
{
    public function handle(AgentExecutionFinished $event): void
    {
        $context = $event->context;
        
        // Access the tracked model from the builder
        // Note: You would need to pass this through the context
        if (isset($context->builder->trackedModel)) {
            AgentUsageLog::create([
                'trackable_type' => $context->builder->trackedModelType,
                'trackable_id' => $context->builder->trackedModelId,
                'agent_name' => $context->agentName,
                'user_id' => $context->user?->id,
                'cost_center' => $context->builder->costCenter ?? null,
                'input_tokens' => $event->inputTokens,
                'output_tokens' => $event->outputTokens,
                'total_tokens' => $event->totalTokens,
                'execution_time_ms' => $event->executionTime,
                'context' => $context->builder->trackingContext ?? null,
            ]);
        }
    }
}
PHP;
    }

    /**
     * Example migration for tracking table
     */
    public static function exampleMigration()
    {
        return <<<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('trackable_type');
            $table->unsignedBigInteger('trackable_id');
            $table->string('agent_name');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('cost_center')->nullable();
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->integer('execution_time_ms')->default(0);
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['trackable_type', 'trackable_id']);
            $table->index('cost_center');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_usage_logs');
    }
};
PHP;
    }

    /**
     * Get an example unit model (for demonstration purposes)
     */
    private static function getExampleUnit()
    {
        // In a real application, you would fetch this from your database
        // For this example, we'll create a mock object
        return new class extends Model {
            protected $table = 'units';
            protected $fillable = ['id', 'name'];
            
            public function __construct()
            {
                parent::__construct(['id' => 12, 'name' => 'Example Unit']);
            }

            public function getKey()
            {
                return 12;
            }
        };
    }
}
