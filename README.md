# Laravel AI Integration

[![Tests](https://github.com/GhDj/laravel-ai-integration/actions/workflows/tests.yml/badge.svg)](https://github.com/GhDj/laravel-ai-integration/actions/workflows/tests.yml)
[![Code Style](https://github.com/GhDj/laravel-ai-integration/actions/workflows/code-style.yml/badge.svg)](https://github.com/GhDj/laravel-ai-integration/actions/workflows/code-style.yml)
[![Static Analysis](https://github.com/GhDj/laravel-ai-integration/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/GhDj/laravel-ai-integration/actions/workflows/static-analysis.yml)
[![Latest Version](https://img.shields.io/packagist/v/ghdj/laravel-ai-integration.svg)](https://packagist.org/packages/ghdj/laravel-ai-integration)
[![License](https://img.shields.io/packagist/l/ghdj/laravel-ai-integration.svg)](https://packagist.org/packages/ghdj/laravel-ai-integration)
[![PHP Version](https://img.shields.io/packagist/php-v/ghdj/laravel-ai-integration.svg)](https://packagist.org/packages/ghdj/laravel-ai-integration)

A Laravel package for seamless integration with multiple AI providers (OpenAI, Claude, Gemini) featuring rate limiting, cost tracking, and prompt templating.

## Requirements

- PHP 8.1+
- Laravel 10.0+

## Installation

```bash
composer require ghdj/laravel-ai-integration
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=ai-config
```

## Configuration

Add your API keys to `.env`:

```env
AI_DEFAULT_PROVIDER=openai

OPENAI_API_KEY=your-openai-key
OPENAI_DEFAULT_MODEL=gpt-4o

CLAUDE_API_KEY=your-claude-key
CLAUDE_DEFAULT_MODEL=claude-sonnet-4-20250514

GEMINI_API_KEY=your-gemini-key
GEMINI_DEFAULT_MODEL=gemini-1.5-pro
```

## Basic Usage

### Simple Chat

```php
use Ghdj\AIIntegration\Facades\AI;

// Using default provider
$response = AI::provider()->chat([
    ['role' => 'user', 'content' => 'Hello, how are you?']
]);

echo $response->getContent();
```

### Switching Providers

```php
// OpenAI
$response = AI::provider('openai')->chat([
    ['role' => 'user', 'content' => 'Explain quantum computing']
]);

// Claude
$response = AI::provider('claude')->chat([
    ['role' => 'user', 'content' => 'Explain quantum computing']
]);

// Gemini
$response = AI::provider('gemini')->chat([
    ['role' => 'user', 'content' => 'Explain quantum computing']
]);
```

### With System Message

```php
$response = AI::provider('openai')->chat([
    ['role' => 'system', 'content' => 'You are a helpful coding assistant.'],
    ['role' => 'user', 'content' => 'Write a function to reverse a string in PHP']
]);
```

### Using Options

```php
$response = AI::provider('openai')->chat(
    messages: [
        ['role' => 'user', 'content' => 'Write a haiku about programming']
    ],
    options: [
        'model' => 'gpt-4o',
        'temperature' => 0.7,
        'max_tokens' => 150,
    ]
);
```

### Text Completion

```php
$response = AI::provider('openai')->complete(
    'Translate to French: Hello, world!',
    ['temperature' => 0.3]
);

echo $response->getContent();
```

## Streaming Responses

```php
$stream = AI::provider('openai')->chatStream([
    ['role' => 'user', 'content' => 'Tell me a story']
]);

foreach ($stream as $chunk) {
    echo $chunk; // Output chunks as they arrive
    flush();
}

// Get final usage stats
echo "Tokens used: " . $stream->getTotalTokens();
```

## Embeddings

```php
// Single text
$response = AI::provider('openai')->embed('Hello world');
$vector = $response->getFirstEmbedding();

// Multiple texts
$response = AI::provider('openai')->embed([
    'First text to embed',
    'Second text to embed',
]);
$vectors = $response->getEmbeddings();

// Gemini batch embeddings
$response = AI::provider('gemini')->embed([
    'Text one',
    'Text two',
    'Text three',
]);
```

> Note: Claude does not support embeddings. Use OpenAI or Gemini.

## Tool/Function Calling

### OpenAI Tools

```php
$tools = [
    [
        'type' => 'function',
        'function' => [
            'name' => 'get_weather',
            'description' => 'Get the current weather for a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'City name',
                    ],
                ],
                'required' => ['location'],
            ],
        ],
    ],
];

$response = AI::provider('openai')->chatWithTools(
    messages: [
        ['role' => 'user', 'content' => 'What is the weather in Paris?']
    ],
    tools: $tools
);

if ($response->hasToolCalls()) {
    foreach ($response->getToolCalls() as $toolCall) {
        $name = $toolCall['function']['name'];
        $args = json_decode($toolCall['function']['arguments'], true);

        // Execute tool and continue conversation
        $result = executeYourTool($name, $args);

        // Send tool result back
        $response = AI::provider('openai')->chat([
            ['role' => 'user', 'content' => 'What is the weather in Paris?'],
            ['role' => 'assistant', 'content' => null, 'tool_calls' => $response->getToolCalls()],
            ['role' => 'tool', 'tool_call_id' => $toolCall['id'], 'content' => json_encode($result)],
        ]);
    }
}
```

### Claude Tools

```php
$tools = [
    [
        'name' => 'calculator',
        'description' => 'Perform basic math operations',
        'input_schema' => [
            'type' => 'object',
            'properties' => [
                'operation' => ['type' => 'string', 'enum' => ['add', 'subtract', 'multiply', 'divide']],
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number'],
            ],
            'required' => ['operation', 'a', 'b'],
        ],
    ],
];

$response = AI::provider('claude')->chatWithTools(
    messages: [['role' => 'user', 'content' => 'What is 25 * 4?']],
    tools: $tools
);
```

### JSON Mode (OpenAI)

```php
$response = AI::provider('openai')->chat(
    messages: [
        ['role' => 'user', 'content' => 'List 3 colors as JSON array']
    ],
    options: [
        'response_format' => ['type' => 'json_object'],
    ]
);

$data = json_decode($response->getContent(), true);
```

## Prompt Templating

### Basic Templates

```php
// Register a template
AI::registerPrompt('greeting', 'Hello {{ name }}, welcome to {{ place }}!');

// Render the template
$text = AI::renderPrompt('greeting', [
    'name' => 'John',
    'place' => 'Laravel'
]);
// Output: Hello John, welcome to Laravel!
```

### Templates with Defaults

```php
AI::registerPrompt('welcome', [
    'template' => 'Welcome {{ name | ucfirst }}, your role is {{ role }}.',
    'defaults' => ['role' => 'user'],
]);

echo AI::renderPrompt('welcome', ['name' => 'john']);
// Output: Welcome John, your role is user.
```

### System Prompts

```php
AI::registerPrompt('assistant', [
    'template' => 'Help me with: {{ task }}',
    'system' => 'You are a helpful {{ specialty }} assistant.',
    'defaults' => ['specialty' => 'general'],
]);

$messages = AI::promptToMessages('assistant', [
    'task' => 'Write a unit test',
    'specialty' => 'PHP',
]);

// Returns:
// [
//     ['role' => 'system', 'content' => 'You are a helpful PHP assistant.'],
//     ['role' => 'user', 'content' => 'Help me with: Write a unit test'],
// ]

$response = AI::provider('openai')->chat($messages);
```

### Available Filters

```php
AI::registerPrompt('filters', '
    Upper: {{ text | upper }}
    Lower: {{ text | lower }}
    Capitalize: {{ text | ucfirst }}
');

echo AI::renderPrompt('filters', ['text' => 'hello WORLD']);
// Upper: HELLO WORLD
// Lower: hello world
// Capitalize: Hello WORLD
```

### Loading Templates from Files

Create template files in your configured prompts directory:

**prompts/summarize.txt**
```
Summarize the following text in {{ style }} style:

{{ content }}
```

**prompts/code-review.json**
```json
{
    "template": "Review this {{ language }} code:\n\n```{{ language }}\n{{ code }}\n```",
    "system": "You are an expert code reviewer.",
    "defaults": {
        "language": "php"
    }
}
```

**prompts/translate.php**
```php
<?php
return [
    'template' => 'Translate to {{ target_language }}: {{ text }}',
    'defaults' => [
        'target_language' => 'English',
    ],
];
```

Load and use:

```php
$promptManager = AI::prompts();

// Load from file
$template = $promptManager->load('summarize');
echo $template->render(['style' => 'concise', 'content' => $article]);

// Load JSON template
$template = $promptManager->load('code-review');
$messages = $template->with(['code' => $code])->toMessages();
```

## Rate Limiting

Rate limiting is built-in and configurable per provider:

```php
// config/ai.php
'rate_limiting' => [
    'enabled' => true,
    'default_limit' => 60,
    'default_window' => 60, // seconds
    'providers' => [
        'openai' => ['limit' => 100, 'window' => 60],
        'claude' => ['limit' => 50, 'window' => 60],
        'gemini' => ['limit' => 60, 'window' => 60],
    ],
],
```

Manual rate limit checking:

```php
use Ghdj\AIIntegration\Services\RateLimiter;

$rateLimiter = app(RateLimiter::class);

// Check remaining requests
$remaining = $rateLimiter->remaining('openai');

// Reset limits
$rateLimiter->reset('openai');
```

## Cost Tracking

Track API usage and costs:

```php
use Ghdj\AIIntegration\Services\CostTracker;

$tracker = app(CostTracker::class);

// After making requests, check costs
$openaiCost = $tracker->getTotalCost('openai');
$claudeCost = $tracker->getTotalCost('claude');
$totalCost = $tracker->getTotalCost(); // All providers

// Get detailed usage
$usage = $tracker->getUsage('openai');
foreach ($usage as $request) {
    echo "Model: {$request['model']}, Cost: \${$request['cost']}\n";
}

// Reset tracking
$tracker->reset();
```

Configure pricing in `config/ai.php`:

```php
'cost_tracking' => [
    'enabled' => true,
    'pricing' => [
        'openai' => [
            'gpt-4o' => ['prompt' => 0.0025, 'completion' => 0.01],
            'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.0006],
            'text-embedding-3-small' => ['prompt' => 0.00002, 'completion' => 0],
        ],
        'claude' => [
            'claude-sonnet-4-20250514' => ['prompt' => 0.003, 'completion' => 0.015],
            'claude-opus-4-20250514' => ['prompt' => 0.015, 'completion' => 0.075],
        ],
        'gemini' => [
            'gemini-1.5-pro' => ['prompt' => 0.00125, 'completion' => 0.005],
            'gemini-1.5-flash' => ['prompt' => 0.000075, 'completion' => 0.0003],
        ],
    ],
],
```

## Custom Providers

Register your own AI provider:

```php
use Ghdj\AIIntegration\Contracts\AIProviderInterface;

class MyCustomProvider implements AIProviderInterface
{
    public function __construct(private array $config) {}

    public function getName(): string
    {
        return 'my-custom';
    }

    public function getModels(): array
    {
        return ['custom-model-v1', 'custom-model-v2'];
    }

    public function supportsStreaming(): bool
    {
        return false;
    }

    public function chat(array $messages, array $options = []): AIResponseInterface
    {
        // Your implementation
    }

    // Implement other interface methods...
}

// Register the provider
AI::extend('my-custom', function (array $config) {
    return new MyCustomProvider($config);
});

// Add configuration
config(['ai.providers.my-custom' => [
    'api_key' => env('MY_CUSTOM_API_KEY'),
]]);

// Use it
$response = AI::provider('my-custom')->chat([
    ['role' => 'user', 'content' => 'Hello!']
]);
```

## Response Object

All chat responses implement `AIResponseInterface`:

```php
$response = AI::provider('openai')->chat($messages);

$response->getContent();          // The response text
$response->getRole();             // 'assistant'
$response->getModel();            // 'gpt-4o'
$response->getFinishReason();     // 'stop', 'length', 'tool_calls'
$response->getPromptTokens();     // Input tokens used
$response->getCompletionTokens(); // Output tokens used
$response->getTotalTokens();      // Total tokens
$response->getUsage();            // ['prompt_tokens' => X, 'completion_tokens' => Y, 'total_tokens' => Z]
$response->hasToolCalls();        // bool
$response->getToolCalls();        // Array of tool calls
$response->getRaw();              // Raw API response
$response->toArray();             // Array representation
```

## Error Handling

```php
use Ghdj\AIIntegration\Exceptions\AIException;
use Ghdj\AIIntegration\Exceptions\APIException;
use Ghdj\AIIntegration\Exceptions\OpenAIException;
use Ghdj\AIIntegration\Exceptions\ClaudeException;
use Ghdj\AIIntegration\Exceptions\GeminiException;
use Ghdj\AIIntegration\Exceptions\RateLimitExceededException;
use Ghdj\AIIntegration\Exceptions\ProviderNotFoundException;

try {
    $response = AI::provider('openai')->chat($messages);
} catch (RateLimitExceededException $e) {
    // Rate limit exceeded (internal or API)
    Log::warning('Rate limit hit', ['provider' => 'openai']);
} catch (OpenAIException $e) {
    if ($e->isRateLimitError()) {
        // API rate limit
    } elseif ($e->isQuotaError()) {
        // Quota exceeded
    } elseif ($e->isAuthenticationError()) {
        // Invalid API key
    }
} catch (ClaudeException $e) {
    if ($e->isOverloaded()) {
        // Claude API overloaded (529)
    }
} catch (APIException $e) {
    // General API error
    $statusCode = $e->getCode();
    $response = $e->getResponse();
} catch (AIException $e) {
    // Base exception for all AI errors
}
```

## Testing

```bash
composer test
```

## License

MIT License. See [LICENSE](LICENSE) for details.
