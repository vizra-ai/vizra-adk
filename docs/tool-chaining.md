# Tool Chaining

Tool chaining allows you to compose multiple tools into a sequential pipeline where the output of one tool flows into the next. This eliminates the need for the LLM to orchestrate multi-step tool operations manually.

## Basic Usage

```php
use Vizra\VizraADK\Tools\Chaining\ToolChain;

$result = ToolChain::create()
    ->pipe(FetchUserTool::class)
    ->transform(fn($result) => json_decode($result, true))
    ->pipe(EnrichUserTool::class, fn($data) => ['user_id' => $data['id']])
    ->execute($arguments, $context, $memory);

$finalValue = $result->value();
```

## Chain Steps

### `pipe()` - Add a Tool

Add a tool to execute in the chain. Optionally provide an argument mapper to transform the previous result into arguments for this tool.

```php
// Simple - uses previous result as arguments
->pipe(MyTool::class)

// With argument mapper - transform previous output for this tool
->pipe(MyTool::class, fn($previousResult, $initialArgs) => [
    'user_id' => $previousResult['id'],
    'name' => $previousResult['name'],
])

// With tool instance
->pipe(new MyTool())
```

### `transform()` - Transform Data

Add a transformation step to modify data between tools.

```php
->pipe(FetchUserTool::class)
->transform(fn($jsonString) => json_decode($jsonString, true))
->transform(fn($data) => $data['email'])
->transform(fn($email) => strtoupper($email))
```

### `when()` - Conditional Execution

Skip remaining steps if condition is false. Optionally provide an alternative value.

```php
// Skip remaining if user is not active
->when(fn($data) => $data['status'] === 'active')

// With alternative value if condition fails
->when(
    fn($data) => $data['status'] === 'active',
    fn($data) => ['error' => 'User is inactive']
)
```

### `tap()` - Side Effects

Execute a callback without modifying the value. Useful for logging or debugging.

```php
->tap(fn($value, $stepIndex) => logger()->info('Step result', [
    'step' => $stepIndex,
    'value' => $value,
]))
```

## Error Handling

By default, chains stop on the first error.

```php
// Continue executing even if a step fails
$chain = ToolChain::create()
    ->continueOnError()
    ->pipe(MayFailTool::class)
    ->pipe(NextTool::class);

$result = $chain->execute($args, $context, $memory);

if ($result->failed()) {
    $error = $result->getFirstError();
    // Handle error
}

// Or throw the error
$result->throw();

// Or get value, throwing if failed
$value = $result->valueOrThrow();
```

## Lifecycle Callbacks

Monitor chain execution with callbacks.

```php
ToolChain::create()
    ->beforeEachStep(function ($step, $index, $currentValue) {
        logger()->info("Starting step {$index}: {$step->describe()}");
    })
    ->afterEachStep(function ($step, $index, $result) {
        logger()->info("Completed step {$index}");
    })
    ->pipe(MyTool::class)
    ->execute($args, $context, $memory);
```

## Working with Results

The `ToolChainResult` provides detailed information about the execution.

```php
$result = $chain->execute($args, $context, $memory);

// Final value
$value = $result->value();

// Status
$result->successful();  // bool
$result->failed();      // bool
$result->hasErrors();   // bool

// Timing
$result->getDuration();    // seconds
$result->getDurationMs();  // milliseconds

// Step details
$result->getExecutedStepCount();
$result->getSkippedStepCount();
$result->getStepResults();
$result->getStepValue(0);  // Value from specific step

// Errors
$result->getErrors();
$result->getFirstError();

// Export
$result->toArray();
$result->toJson();
```

## Chainable Tools

For tools that are frequently chained, implement `ChainableToolInterface` for automatic input/output transformation.

```php
use Vizra\VizraADK\Contracts\ChainableToolInterface;
use Vizra\VizraADK\Tools\Chaining\ChainableTool;

class MyChainableTool implements ChainableToolInterface
{
    use ChainableTool; // Provides sensible defaults

    public function definition(): array
    {
        return [...];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['result' => 'data']);
    }

    // Optional: Override for custom behavior
    public function transformOutputForChain(string $rawOutput): mixed
    {
        return json_decode($rawOutput, true);
    }

    public function acceptChainInput(mixed $previousOutput, array $initialArguments): array
    {
        return ['id' => $previousOutput['user_id']];
    }
}
```

When a tool implements `ChainableToolInterface`:
- Output is automatically transformed via `transformOutputForChain()`
- Input is automatically mapped via `acceptChainInput()`
- No need for explicit `transform()` or argument mappers

## Named Chains

Name your chains for better debugging and tracing.

```php
$chain = ToolChain::create('user-onboarding-pipeline')
    ->pipe(CreateUserTool::class)
    ->pipe(SendWelcomeEmailTool::class)
    ->pipe(SetupDefaultsTool::class);

$result = $chain->execute($args, $context, $memory);
echo $result->getChainName(); // "user-onboarding-pipeline"
```

## Real-World Example

```php
use Vizra\VizraADK\Tools\Chaining\ToolChain;

class OrderProcessingService
{
    public function processOrder(array $orderData, AgentContext $context, AgentMemory $memory): array
    {
        $result = ToolChain::create('order-processing')
            // Validate the order
            ->pipe(ValidateOrderTool::class)
            ->transform(fn($r) => json_decode($r, true))
            ->when(fn($data) => $data['valid'], fn($data) => [
                'error' => 'Invalid order',
                'reasons' => $data['errors']
            ])

            // Check inventory
            ->pipe(CheckInventoryTool::class, fn($data, $initial) => [
                'items' => $initial['items']
            ])
            ->transform(fn($r) => json_decode($r, true))
            ->when(fn($data) => $data['in_stock'])

            // Process payment
            ->pipe(ProcessPaymentTool::class, fn($data, $initial) => [
                'amount' => $initial['total'],
                'payment_method' => $initial['payment_method']
            ])
            ->transform(fn($r) => json_decode($r, true))

            // Create shipment
            ->pipe(CreateShipmentTool::class, fn($data, $initial) => [
                'order_id' => $data['order_id'],
                'address' => $initial['shipping_address']
            ])

            // Log completion
            ->tap(fn($result) => logger()->info('Order processed', $result))

            ->execute($orderData, $context, $memory);

        if ($result->failed()) {
            return ['success' => false, 'error' => $result->getFirstError()->getMessage()];
        }

        return ['success' => true, 'data' => $result->value()];
    }
}
```

## API Reference

### ToolChain

| Method | Description |
|--------|-------------|
| `create(?string $name)` | Create a new chain, optionally named |
| `pipe($tool, ?Closure $mapper)` | Add a tool step |
| `transform(Closure $fn)` | Add a transformation step |
| `when(Closure $condition, ?Closure $otherwise)` | Add a conditional step |
| `tap(Closure $callback)` | Add a side-effect step |
| `stopOnError(bool $stop)` | Configure error handling |
| `continueOnError()` | Continue on errors |
| `beforeEachStep(Closure $cb)` | Set before-step callback |
| `afterEachStep(Closure $cb)` | Set after-step callback |
| `execute($args, $context, $memory)` | Run the chain |
| `getSteps()` | Get all steps |
| `getName()` | Get chain name |
| `isEmpty()` | Check if empty |
| `count()` | Get step count |

### ToolChainResult

| Method | Description |
|--------|-------------|
| `value()` | Get final value |
| `successful()` | Check if successful |
| `failed()` | Check if failed |
| `hasErrors()` | Check for errors |
| `getErrors()` | Get all errors |
| `getFirstError()` | Get first error |
| `throw()` | Throw first error if failed |
| `valueOrThrow()` | Get value or throw |
| `getDuration()` | Get duration in seconds |
| `getDurationMs()` | Get duration in ms |
| `getStepResults()` | Get all step results |
| `getStepValue(int $i)` | Get specific step value |
| `getExecutedStepCount()` | Count executed steps |
| `getSkippedStepCount()` | Count skipped steps |
| `toArray()` | Export as array |
| `toJson()` | Export as JSON |
