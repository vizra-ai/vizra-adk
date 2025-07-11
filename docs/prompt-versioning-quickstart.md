# Dynamic Prompts Quick Start

## 1. Create Prompt Files

Store prompts in `resources/prompts/{agent_name}/{version}.txt`:

```bash
# Create directory
mkdir -p resources/prompts/my_agent

# Create default prompt
echo "You are a helpful assistant." > resources/prompts/my_agent/default.txt

# Create alternative versions
echo "You are a concise assistant. Be brief." > resources/prompts/my_agent/concise.txt
echo "You are a detailed assistant. Provide comprehensive answers." > resources/prompts/my_agent/detailed.txt
```

## 2. Use in Code

```php
// Runtime version selection
$response = MyAgent::run('Hello')
    ->withPromptVersion('concise')
    ->go();

// With Agent facade
$response = Agent::build('my_agent')
    ->withPromptVersion('detailed')
    ->ask('Hello')
    ->go();
```

## 3. Use in Evaluations

```php
class MyEvaluation extends BaseEvaluation
{
    // Specify prompt version column in CSV
    public ?string $promptVersionColumn = 'prompt_version';

    // Or set default for all tests
    public array $agentConfig = [
        'prompt_version' => 'detailed'
    ];
}
```

## 4. CLI Management

```bash
# List all prompts
php artisan vizra:prompt list

# Create new prompt
php artisan vizra:prompt create my_agent v2 --content="New prompt"

# Export prompt
php artisan vizra:prompt export my_agent v2

# Import prompt
php artisan vizra:prompt import my_agent v3 --file=prompt.txt
```

## 5. Enable Database Storage (Optional)

```bash
# Run migration
php artisan migrate

# Update config
VIZRA_ADK_PROMPTS_USE_DATABASE=true

# Activate version
php artisan vizra:prompt activate my_agent v2
```

That's it! Your agents now support multiple prompt versions.
