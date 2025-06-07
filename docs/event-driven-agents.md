# ⚡ Event-Driven Agents

Transform your Laravel AI ADK agents from simple chatbots into intelligent automation systems that respond to events, process data autonomously, and monitor your application continuously.

## 🎯 Beyond Conversations

While conversational agents are powerful, real business applications need agents that can:

- **React to business events** (orders, payments, user actions)
- **Process data on schedules** (daily reports, weekly analysis)
- **Monitor systems continuously** (security, performance, health)
- **Trigger workflows** (notifications, escalations, automations)
- **Make autonomous decisions** (approvals, routing, optimizations)

## 🚀 Agent Execution Modes

### **Ask Mode** (Conversational)
```php
// Traditional chat interaction
$response = CustomerSupportAgent::ask('Where is my order?')->forUser($user);
```

### **Trigger Mode** (Event-Driven)
```php
// React to Laravel events
NotificationAgent::trigger($orderCreatedEvent)
    ->forUser($order->customer)
    ->async()
    ->execute();
```

### **Analyze Mode** (Data Analysis)
```php
// Analyze data for insights
$insights = FraudDetectionAgent::analyze($paymentData)
    ->withContext(['transaction_id' => $payment->id])
    ->execute();
```

### **Process Mode** (Batch Operations)
```php
// Handle large datasets
DataProcessorAgent::process($largeDataset)
    ->async()
    ->onQueue('data-processing')
    ->timeout(600)
    ->execute();
```

### **Monitor Mode** (Continuous Monitoring)
```php
// Monitor system health
SystemMonitorAgent::monitor($metrics)
    ->withContext(['alert_threshold' => 0.95])
    ->onQueue('monitoring')
    ->execute();
```

### **Generate Mode** (Report Generation)
```php
// Create reports and summaries
ReportAgent::generate('weekly_sales')
    ->withContext(['date_range' => 'last_week'])
    ->async()
    ->execute();
```

## 🏗️ Building Event-Driven Agents

### Step 1: Create a Multi-Mode Agent

```php
<?php

namespace App\Agents;

use AaronLumsden\LaravelAiADK\Agents\BaseLlmAgent;
use AaronLumsden\LaravelAiADK\System\AgentContext;

class OrderProcessingAgent extends BaseLlmAgent
{
    protected string $name = 'order_processing';
    
    protected string $instructions = "
    You are an intelligent order processing agent that handles different aspects of order management.

    **Your Modes:**
    - **Trigger**: React to order events (created, paid, shipped, cancelled)
    - **Analyze**: Examine order patterns and detect issues
    - **Process**: Handle batch order operations and updates
    - **Monitor**: Watch for order anomalies and stuck processes
    - **Ask**: Answer questions about orders conversationally

    **Event Handling:**
    - OrderCreated: Validate order, check inventory, calculate shipping
    - PaymentReceived: Confirm payment, trigger fulfillment workflow
    - OrderShipped: Send tracking notifications, update customer
    - OrderCancelled: Process refunds, restore inventory

    **Analysis Capabilities:**
    - Identify order failure patterns
    - Detect fraud indicators
    - Analyze customer behavior
    - Monitor conversion rates

    **Processing Tasks:**
    - Batch status updates
    - Generate shipping labels
    - Send notification emails
    - Update inventory levels

    **Always provide specific, actionable responses with clear next steps.
    ";

    protected array $tools = [
        \App\Tools\OrderManagementTool::class,
        \App\Tools\InventoryTool::class,
        \App\Tools\PaymentTool::class,
        \App\Tools\ShippingTool::class,
        \App\Tools\VectorMemoryTool::class,
    ];

    public function run(mixed $input, AgentContext $context): mixed
    {
        $mode = $context->getState('execution_mode', 'ask');

        return match($mode) {
            'trigger' => $this->handleEvent($input, $context),
            'analyze' => $this->analyzeData($input, $context),
            'process' => $this->processData($input, $context),
            'monitor' => $this->monitorSystems($input, $context),
            'generate' => $this->generateReports($input, $context),
            default => $this->handleConversation($input, $context),
        };
    }

    protected function handleEvent($event, AgentContext $context): string
    {
        $eventType = get_class($event);
        
        return match($eventType) {
            'App\Events\OrderCreated' => $this->processOrderCreated($event, $context),
            'App\Events\PaymentReceived' => $this->processPaymentReceived($event, $context),
            'App\Events\OrderShipped' => $this->processOrderShipped($event, $context),
            'App\Events\OrderCancelled' => $this->processOrderCancelled($event, $context),
            default => $this->handleGenericEvent($event, $context),
        };
    }

    protected function processOrderCreated($event, AgentContext $context): string
    {
        $order = $event->order;
        
        // Use tools to validate and process the order
        $validation = $this->validateOrder($order);
        $inventory = $this->checkInventory($order);
        $shipping = $this->calculateShipping($order);
        
        if ($validation['valid'] && $inventory['available']) {
            // Auto-approve the order
            $this->approveOrder($order);
            
            // Trigger customer notification
            CustomerNotificationAgent::trigger('order_approved')
                ->forUser($order->customer)
                ->withContext([
                    'order' => $order,
                    'shipping_estimate' => $shipping['estimate']
                ])
                ->async()
                ->execute();
                
            return "Order {$order->id} processed successfully and customer notified.";
        } else {
            // Flag for manual review
            $this->flagForReview($order, $validation, $inventory);
            return "Order {$order->id} flagged for manual review.";
        }
    }

    protected function analyzeData($data, AgentContext $context): string
    {
        // Analyze order patterns, fraud detection, etc.
        $patterns = $this->identifyPatterns($data);
        $anomalies = $this->detectAnomalies($data);
        
        return "Analysis completed: {$patterns['insights']}. Anomalies detected: {$anomalies['count']}.";
    }

    protected function processData($data, AgentContext $context): string
    {
        // Handle batch operations
        $processed = $this->batchUpdateOrders($data);
        return "Processed {$processed['count']} orders in batch operation.";
    }

    protected function monitorSystems($data, AgentContext $context): string
    {
        // Monitor order processing health
        $metrics = $this->checkOrderMetrics($data);
        
        if ($metrics['issues_detected']) {
            // Alert the team
            AlertAgent::trigger('order_processing_issues')
                ->withContext(['metrics' => $metrics])
                ->async()
                ->execute();
        }
        
        return "Monitoring completed. Issues detected: {$metrics['issues_detected']}.";
    }
}
```

### Step 2: Create Event Listeners

```php
<?php

namespace App\Listeners;

use AaronLumsden\LaravelAiADK\Listeners\AgentEventListener;
use App\Events\OrderCreated;
use App\Agents\OrderProcessingAgent;

class OrderCreatedListener extends AgentEventListener
{
    protected string $agentClass = OrderProcessingAgent::class;
    protected string $mode = 'trigger';
    protected bool $async = true;
    protected string $queue = 'orders';

    protected function buildContext($event): array
    {
        return [
            'event_class' => get_class($event),
            'order_id' => $event->order->id,
            'customer_id' => $event->order->customer_id,
            'order_total' => $event->order->total,
            'order_items' => $event->order->items->count(),
            'event_time' => now()->toISOString(),
        ];
    }

    protected function extractUser($event)
    {
        return $event->order->customer;
    }

    protected function handleResult($result, $event): void
    {
        // Log the processing result
        \Log::info('Order processed by agent', [
            'order_id' => $event->order->id,
            'agent_result' => $result,
            'processing_time' => now()->toISOString(),
        ]);

        // Store result for audit trail
        $event->order->agent_processing_log()->create([
            'agent_class' => $this->agentClass,
            'mode' => $this->mode,
            'result' => $result,
            'processed_at' => now(),
        ]);
    }
}
```

Register the listener in your `EventServiceProvider`:

```php
protected $listen = [
    OrderCreated::class => [
        OrderCreatedListener::class,
    ],
    PaymentReceived::class => [
        PaymentProcessedListener::class,
    ],
    OrderCancelled::class => [
        OrderCancelledListener::class,
    ],
];
```

### Step 3: Schedule Recurring Agent Tasks

```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use AaronLumsden\LaravelAiADK\Scheduling\AgentScheduler;
use App\Agents\{BusinessIntelligenceAgent, CustomerHealthAgent, SecurityMonitoringAgent};

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $scheduler = new AgentScheduler($schedule);

        // Daily business intelligence report
        $scheduler->daily(BusinessIntelligenceAgent::class, 'daily_report')
            ->withContext([
                'report_type' => 'daily_operations',
                'include_metrics' => ['sales', 'orders', 'customers'],
                'recipients' => ['executives', 'operations'],
            ])
            ->name('daily-bi-report')
            ->description('Generate daily business intelligence report')
            ->async()
            ->onQueue('reports')
            ->register();

        // Hourly customer health monitoring
        $scheduler->hourly(CustomerHealthAgent::class, 'health_check')
            ->withContext([
                'check_type' => 'satisfaction_monitoring',
                'alert_threshold' => 0.7,
                'include_churn_prediction' => true,
            ])
            ->name('customer-health-check')
            ->async()
            ->onQueue('monitoring')
            ->register();

        // Every 15 minutes security monitoring
        $scheduler->everyMinutes(15, SecurityMonitoringAgent::class, 'security_scan')
            ->withContext([
                'scan_type' => 'threat_detection',
                'severity_threshold' => 'medium',
            ])
            ->name('security-monitoring')
            ->async()
            ->onQueue('security')
            ->register();

        // Weekly comprehensive analysis
        $scheduler->weekly(BusinessIntelligenceAgent::class, 'weekly_analysis')
            ->withContext([
                'report_type' => 'weekly_comprehensive',
                'include_trends' => true,
                'include_forecasts' => true,
                'compare_previous_period' => true,
            ])
            ->name('weekly-analysis')
            ->async()
            ->onQueue('analytics')
            ->register();

        // Monthly customer segmentation
        $scheduler->monthly(CustomerHealthAgent::class, 'segment_customers')
            ->withContext([
                'segmentation_type' => 'behavioral',
                'update_marketing_lists' => true,
            ])
            ->name('monthly-segmentation')
            ->async()
            ->onQueue('analytics')
            ->register();
    }
}
```

## 🔄 Async and Queue Processing

### Background Processing

```php
// Process large datasets asynchronously
DataProcessorAgent::process($largeDataset)
    ->async()
    ->onQueue('data-processing')
    ->timeout(600) // 10 minutes
    ->tries(3)
    ->execute();

// Returns immediately with job tracking info
// {
//     "job_dispatched": true,
//     "job_id": "uuid-here",
//     "queue": "data-processing",
//     "agent": "data_processor",
//     "mode": "process"
// }
```

### Delayed Execution

```php
// Send follow-up email after 1 hour
CustomerFollowUpAgent::trigger($orderCompleted)
    ->forUser($customer)
    ->delay(3600) // 1 hour
    ->onQueue('notifications')
    ->execute();
```

### Chain Multiple Agents

```php
// Sequential agent processing
OrderProcessingAgent::trigger($orderCreated)
    ->async()
    ->execute();

// This could then trigger:
InventoryAgent::process($inventoryUpdate)
    ->async()
    ->execute();

// Which could trigger:
CustomerNotificationAgent::trigger($orderConfirmed)
    ->forUser($customer)
    ->execute();
```

## 🌐 Webhook and API Integration

### Webhook Controllers

```php
<?php

namespace App\Http\Controllers;

use App\Agents\{SecurityMonitoringAgent, CustomerHealthAgent, OrderProcessingAgent};
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handleStripeWebhook(Request $request)
    {
        $event = $request->json();

        match($event['type']) {
            'payment_intent.succeeded' =>
                OrderProcessingAgent::trigger($event)
                    ->withContext(['webhook_source' => 'stripe', 'event_type' => 'payment_success'])
                    ->async()
                    ->execute(),

            'payment_intent.payment_failed' =>
                SecurityMonitoringAgent::analyze($event)
                    ->withContext(['security_concern' => 'payment_failure', 'risk_level' => 'medium'])
                    ->async()
                    ->execute(),

            'customer.subscription.deleted' =>
                CustomerHealthAgent::trigger($event)
                    ->withContext(['churn_event' => true, 'immediate_action' => true])
                    ->async()
                    ->execute(),

            default => \Log::info('Unhandled Stripe webhook: ' . $event['type'])
        };

        return response()->json(['status' => 'handled']);
    }

    public function handleShopifyWebhook(Request $request)
    {
        $data = $request->json();

        match($request->header('X-Shopify-Topic')) {
            'orders/create' =>
                OrderProcessingAgent::trigger($data)
                    ->withContext(['source' => 'shopify'])
                    ->async()
                    ->execute(),

            'orders/cancelled' =>
                CustomerHealthAgent::analyze($data)
                    ->withContext(['cancellation_analysis' => true])
                    ->async()
                    ->execute(),

            default => \Log::info('Unhandled Shopify webhook')
        };

        return response()->json(['status' => 'received']);
    }
}
```

### API Endpoints

```php
class AnalyticsController extends Controller
{
    public function analyzeCustomerData(Request $request)
    {
        $analysis = CustomerHealthAgent::analyze($request->input('customer_data'))
            ->withContext([
                'analysis_type' => 'ad_hoc',
                'requested_by' => auth()->id(),
                'urgency' => $request->input('urgency', 'normal'),
            ])
            ->forUser(auth()->user())
            ->execute();

        return response()->json(['analysis' => $analysis]);
    }

    public function generateReport(Request $request)
    {
        $reportType = $request->input('type');
        
        $report = BusinessIntelligenceAgent::generate($reportType)
            ->withContext([
                'date_range' => $request->input('date_range'),
                'format' => $request->input('format', 'json'),
                'recipients' => $request->input('recipients', []),
            ])
            ->forUser(auth()->user())
            ->async()
            ->execute();

        return response()->json([
            'message' => 'Report generation started',
            'job_info' => $report,
        ]);
    }
}
```

## 📊 Real-World Use Cases

### E-commerce Automation Pipeline

```php
// Order lifecycle automation
class EcommerceOrderPipeline
{
    public static function setupEventListeners()
    {
        // Order created → Validate and process
        Event::listen(OrderCreated::class, function ($event) {
            OrderProcessingAgent::trigger($event)
                ->forUser($event->order->customer)
                ->async()
                ->execute();
        });

        // Payment received → Fulfill order
        Event::listen(PaymentReceived::class, function ($event) {
            FulfillmentAgent::trigger($event)
                ->withContext(['priority' => $event->payment->amount > 500 ? 'high' : 'normal'])
                ->async()
                ->execute();
        });

        // Order shipped → Customer notification
        Event::listen(OrderShipped::class, function ($event) {
            CustomerNotificationAgent::trigger($event)
                ->forUser($event->order->customer)
                ->withContext(['tracking_number' => $event->tracking_number])
                ->execute();
        });

        // Support ticket → Automatic triage
        Event::listen(SupportTicketCreated::class, function ($event) {
            SupportTriageAgent::analyze($event->ticket)
                ->withContext(['urgency_detection' => true])
                ->async()
                ->execute();
        });
    }
}
```

### Business Intelligence Automation

```php
// Scheduled business monitoring
class BusinessIntelligenceSchedule
{
    public static function setupSchedule(Schedule $schedule)
    {
        $scheduler = new AgentScheduler($schedule);

        // Real-time metrics monitoring (every 5 minutes)
        $scheduler->everyMinutes(5, MetricsMonitorAgent::class, 'real_time_check')
            ->withContext(['alert_on_anomalies' => true])
            ->async()
            ->onQueue('monitoring')
            ->register();

        // Hourly sales analysis
        $scheduler->hourly(SalesAnalysisAgent::class, 'hourly_analysis')
            ->withContext(['include_forecasting' => true])
            ->async()
            ->register();

        // Daily executive summary
        $scheduler->at('08:00', ExecutiveSummaryAgent::class, 'daily_summary')
            ->withContext([
                'recipients' => ['ceo@company.com', 'coo@company.com'],
                'include_yesterday_comparison' => true,
            ])
            ->async()
            ->register();

        // Weekly trend analysis
        $scheduler->weekly(TrendAnalysisAgent::class, 'weekly_trends')
            ->withContext(['deep_analysis' => true, 'prediction_horizon' => '4_weeks'])
            ->async()
            ->register();
    }
}
```

### Security Monitoring System

```php
// Continuous security monitoring
class SecurityMonitoringSystem
{
    public static function setupMonitoring()
    {
        // Real-time threat detection
        Event::listen(SuspiciousActivity::class, function ($event) {
            SecurityAgent::analyze($event)
                ->withContext([
                    'threat_level' => $event->severity,
                    'immediate_response' => true,
                ])
                ->async()
                ->onQueue('security-high')
                ->execute();
        });

        // Failed login monitoring
        Event::listen(LoginFailed::class, function ($event) {
            SecurityAgent::monitor($event)
                ->withContext([
                    'track_ip' => $event->ip_address,
                    'user_id' => $event->user_id,
                ])
                ->async()
                ->execute();
        });

        // API abuse detection
        Event::listen(ApiRateLimitExceeded::class, function ($event) {
            SecurityAgent::analyze($event)
                ->withContext(['auto_block' => true])
                ->execute(); // Synchronous for immediate action
        });
    }
}
```

## 🎯 Best Practices

### 1. **Choose the Right Mode**

```php
// Use trigger for immediate event responses
NotificationAgent::trigger($userRegistered)->execute();

// Use analyze for data insights
FraudAgent::analyze($suspiciousTransaction)->execute();

// Use process for batch operations
DataCleanupAgent::process($oldRecords)->async()->execute();

// Use monitor for continuous watching
HealthAgent::monitor($systemMetrics)->execute();

// Use generate for reports and summaries
ReportAgent::generate('monthly_summary')->async()->execute();
```

### 2. **Optimize for Performance**

```php
// Use async for non-blocking operations
LongRunningAgent::process($bigDataset)
    ->async()
    ->timeout(1800) // 30 minutes
    ->tries(3)
    ->onQueue('heavy-processing')
    ->execute();

// Use appropriate queues
CriticalAgent::trigger($urgentEvent)->onQueue('critical')->execute();
ReportAgent::generate($report)->onQueue('reports')->execute();
AnalysisAgent::analyze($data)->onQueue('analytics')->execute();
```

### 3. **Handle Failures Gracefully**

```php
class RobustEventListener extends AgentEventListener
{
    protected function handleFailure(\Exception $exception, $event): void
    {
        // Log the failure
        \Log::error('Agent execution failed', [
            'agent' => $this->agentClass,
            'event' => get_class($event),
            'error' => $exception->getMessage(),
        ]);

        // Send alert to team
        AlertAgent::trigger('agent_failure')
            ->withContext([
                'failed_agent' => $this->agentClass,
                'error_details' => $exception->getMessage(),
                'event_data' => $event,
            ])
            ->execute();

        // Try fallback processing
        FallbackAgent::process($event)
            ->withContext(['fallback_reason' => 'primary_agent_failed'])
            ->execute();
    }
}
```

### 4. **Monitor and Debug**

```php
// Use job tagging for monitoring
OrderAgent::process($orders)
    ->async()
    ->onQueue('orders')
    ->execute();

// Monitor job progress
$jobInfo = CustomerAgent::analyze($data)->async()->execute();
$jobId = $jobInfo['job_id'];

// Check job status later
$result = cache("agent_job_result:{$jobId}");
$meta = cache("agent_job_meta:{$jobId}");
```

---

<p align="center">
<strong>Ready to build autonomous AI systems?</strong><br>
Your agents can now respond to events, process data autonomously, and monitor systems continuously!
</p>