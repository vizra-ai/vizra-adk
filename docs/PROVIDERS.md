# Supported LLM Providers

Vizra ADK supports all providers available in Prism PHP. You can use any of the following providers with your agents:

## Available Providers

### 1. OpenAI
- **Provider Name**: `openai`
- **Example Models**: `gpt-4`, `gpt-4-turbo`, `gpt-3.5-turbo`, `o1-preview`
- **Usage**:
```php
protected ?Provider $provider = Provider::OpenAI;
protected string $model = 'gpt-4-turbo';
```

### 2. Anthropic
- **Provider Name**: `anthropic`
- **Example Models**: `claude-3-opus-20240229`, `claude-3-sonnet-20240229`, `claude-3-haiku-20240307`
- **Usage**:
```php
protected ?Provider $provider = Provider::Anthropic;
protected string $model = 'claude-3-opus-20240229';
```

### 3. Google Gemini
- **Provider Names**: `gemini`, `google`
- **Example Models**: `gemini-1.5-pro`, `gemini-1.5-flash`, `gemini-pro`
- **Usage**:
```php
protected ?Provider $provider = Provider::Gemini;
protected string $model = 'gemini-1.5-flash';
```

### 4. DeepSeek
- **Provider Name**: `deepseek`
- **Example Models**: `deepseek-chat`, `deepseek-coder`
- **Usage**:
```php
protected ?Provider $provider = Provider::DeepSeek;
protected string $model = 'deepseek-chat';
```

### 5. Ollama (Local Models)
- **Provider Name**: `ollama`
- **Example Models**: `llama2`, `codellama`, `phi`, `mistral`, `mixtral`
- **Usage**:
```php
protected ?Provider $provider = Provider::Ollama;
protected string $model = 'llama2';
```

### 6. Mistral AI
- **Provider Name**: `mistral`
- **Example Models**: `mistral-large-latest`, `mistral-medium-latest`, `mistral-small-latest`
- **Usage**:
```php
protected ?Provider $provider = Provider::Mistral;
protected string $model = 'mistral-large-latest';
```

### 7. Groq
- **Provider Name**: `groq`
- **Example Models**: `mixtral-8x7b-32768`, `llama2-70b-4096`
- **Usage**:
```php
protected ?Provider $provider = Provider::Groq;
protected string $model = 'mixtral-8x7b-32768';
```

### 8. xAI
- **Provider Names**: `xai`, `grok`
- **Example Models**: `grok-beta`
- **Usage**:
```php
protected ?Provider $provider = Provider::XAI;
protected string $model = 'grok-beta';
```

### 9. Voyage AI
- **Provider Names**: `voyageai`, `voyage`
- **Example Models**: `voyage-large-2`, `voyage-code-2`
- **Note**: Primarily for embeddings
- **Usage**:
```php
protected ?Provider $provider = Provider::VoyageAI;
protected string $model = 'voyage-large-2';
```

## Setting Provider at Runtime

You can set the provider dynamically using the `setProvider()` method:

```php
// Using the Provider enum
$agent->setProvider(Provider::Anthropic);

// Using a string
$agent->setProvider('anthropic');

// Chain with other setters
$agent->setProvider('groq')
    ->setModel('mixtral-8x7b-32768')
    ->setTemperature(0.7);
```

## Auto-Detection

The ADK can automatically detect the provider based on the model name:

```php
// These will auto-detect the correct provider
protected string $model = 'gpt-4'; // Auto-detects OpenAI
protected string $model = 'claude-3-opus-20240229'; // Auto-detects Anthropic
protected string $model = 'gemini-pro'; // Auto-detects Gemini
protected string $model = 'deepseek-chat'; // Auto-detects DeepSeek
protected string $model = 'llama2'; // Auto-detects Ollama
protected string $model = 'mistral-large-latest'; // Auto-detects Mistral
protected string $model = 'grok-beta'; // Auto-detects xAI
```

## Configuration

Set the default provider in your `.env` file:

```env
VIZRA_ADK_DEFAULT_PROVIDER=openai
VIZRA_ADK_DEFAULT_MODEL=gpt-4-turbo
```

Or in the config file `config/vizra-adk.php`:

```php
'default_provider' => 'anthropic',
'default_model' => 'claude-3-opus-20240229',
```

## Provider-Specific Requirements

Some providers require additional configuration:

- **Ollama**: Requires Ollama to be installed and running locally
- **Groq**: Requires a Groq API key
- **DeepSeek**: Requires a DeepSeek API key
- **VoyageAI**: Primarily for embeddings, not text generation

Refer to the [Prism PHP documentation](https://prismphp.com/providers) for provider-specific setup instructions.