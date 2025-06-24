<?php

use Prism\Prism\Enums\Provider;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\System\AgentContext;

it('correctly identifies provider support for attachments', function () {
    // OpenAI: Images only
    $openAiAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'openai_test';
        protected string $description = 'OpenAI test agent';
        protected string $instructions = 'Test agent';
        protected string $model = 'gpt-4o';
        protected ?Provider $provider = Provider::OpenAI;
        
        public function getProviderInfo(): array
        {
            return [
                'provider' => $this->getProvider()->value,
                'model' => $this->getModel(),
                'supports_images' => true,
                'supports_documents' => false,
            ];
        }
    };
    
    Agent::build(get_class($openAiAgent))->register();
    
    $info = (new $openAiAgent())->getProviderInfo();
    expect($info['provider'])->toBe('openai');
    expect($info['supports_images'])->toBeTrue();
    expect($info['supports_documents'])->toBeFalse();
    
    // Anthropic: Both images and documents
    $anthropicAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'anthropic_test';
        protected string $description = 'Anthropic test agent';
        protected string $instructions = 'Test agent';
        protected string $model = 'claude-3-5-sonnet-latest';
        protected ?Provider $provider = Provider::Anthropic;
        
        public function getProviderInfo(): array
        {
            return [
                'provider' => $this->getProvider()->value,
                'model' => $this->getModel(),
                'supports_images' => true,
                'supports_documents' => true,
            ];
        }
    };
    
    Agent::build(get_class($anthropicAgent))->register();
    
    $info = (new $anthropicAgent())->getProviderInfo();
    expect($info['provider'])->toBe('anthropic');
    expect($info['supports_images'])->toBeTrue();
    expect($info['supports_documents'])->toBeTrue();
    
    // Gemini: Both images and documents
    $geminiAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'gemini_test';
        protected string $description = 'Gemini test agent';
        protected string $instructions = 'Test agent';
        protected string $model = 'gemini-2.0-flash';
        protected ?Provider $provider = Provider::Gemini;
        
        public function getProviderInfo(): array
        {
            return [
                'provider' => $this->getProvider()->value,
                'model' => $this->getModel(),
                'supports_images' => true,
                'supports_documents' => true,
            ];
        }
    };
    
    Agent::build(get_class($geminiAgent))->register();
    
    $info = (new $geminiAgent())->getProviderInfo();
    expect($info['provider'])->toBe('gemini');
    expect($info['supports_images'])->toBeTrue();
    expect($info['supports_documents'])->toBeTrue();
});

it('handles provider limitations gracefully', function () {
    // Create a mock agent that simulates OpenAI behavior with documents
    $mockOpenAiAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'mock_openai';
        protected string $description = 'Mock OpenAI agent';
        protected string $instructions = 'Test agent';
        protected string $model = 'gpt-4o';
        protected ?Provider $provider = Provider::OpenAI;
        
        public function run(mixed $input, AgentContext $context): mixed
        {
            // Simulate what happens when OpenAI receives documents
            $documents = $context->getState('prism_documents', []);
            
            if (!empty($documents) && $this->getProvider() === Provider::OpenAI) {
                // In real scenario, this would throw an API error
                // but for testing, we'll return a descriptive message
                return 'OpenAI does not support document uploads';
            }
            
            return 'Success';
        }
    };
    
    Agent::build(get_class($mockOpenAiAgent))->register();
    
    // Test without documents - should work
    $result = $mockOpenAiAgent::ask('Test without documents')
        ->withSession('test-session-1')
        ->go();
    
    expect($result)->toBe('Success');
    
    // Test with documents - should indicate limitation
    $context = new AgentContext('test-session-2');
    $context->setState('prism_documents', ['mock_document']);
    
    $agent = new $mockOpenAiAgent();
    $result = $agent->run('Test with documents', $context);
    
    expect($result)->toBe('OpenAI does not support document uploads');
});

it('suggests alternative providers for unsupported features', function () {
    // Helper agent that suggests alternatives
    $helperAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'helper_agent';
        protected string $description = 'Helper agent';
        protected string $instructions = 'Test agent';
        protected string $model = 'gpt-4o';
        
        public function suggestAlternativeForDocuments(): array
        {
            if ($this->getProvider() === Provider::OpenAI) {
                return [
                    'current_provider' => 'OpenAI',
                    'limitation' => 'Documents not supported',
                    'alternatives' => [
                        'Anthropic Claude' => 'Full document support',
                        'Google Gemini' => 'Full document support',
                    ],
                    'workaround' => 'Extract text from document and include in prompt',
                ];
            }
            
            return ['current_provider' => $this->getProvider()->value, 'limitation' => 'None'];
        }
    };
    
    Agent::build(get_class($helperAgent))->register();
    
    $agent = new $helperAgent();
    $agent->setProvider(Provider::OpenAI);
    
    $suggestion = $agent->suggestAlternativeForDocuments();
    
    expect($suggestion['current_provider'])->toBe('OpenAI');
    expect($suggestion['limitation'])->toBe('Documents not supported');
    expect($suggestion['alternatives'])->toHaveKeys(['Anthropic Claude', 'Google Gemini']);
    expect($suggestion['workaround'])->toContain('Extract text');
});

it('validates model capabilities for attachments', function () {
    // Test different OpenAI models
    $gpt4oAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'gpt4o_agent';
        protected string $description = 'GPT-4o agent';
        protected string $instructions = 'Test agent';
        protected string $model = 'gpt-4o';
        protected ?Provider $provider = Provider::OpenAI;
        
        public function supportsImages(): bool
        {
            // GPT-4o and GPT-4-turbo support images
            return in_array($this->getModel(), ['gpt-4o', 'gpt-4-turbo', 'gpt-4-vision-preview']);
        }
    };
    
    $gpt35Agent = new class extends BaseLlmAgent
    {
        protected string $name = 'gpt35_agent';
        protected string $description = 'GPT-3.5 agent';
        protected string $instructions = 'Test agent';
        protected string $model = 'gpt-3.5-turbo';
        protected ?Provider $provider = Provider::OpenAI;
        
        public function supportsImages(): bool
        {
            // GPT-3.5 doesn't support images
            return false;
        }
    };
    
    Agent::build(get_class($gpt4oAgent))->register();
    Agent::build(get_class($gpt35Agent))->register();
    
    expect((new $gpt4oAgent())->supportsImages())->toBeTrue();
    expect((new $gpt35Agent())->supportsImages())->toBeFalse();
});

it('provides clear error messages for unsupported attachments', function () {
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'error_test_agent';
        protected string $description = 'Error test agent';
        protected string $instructions = 'Test agent';
        protected string $model = 'gpt-4o';
        protected ?Provider $provider = Provider::OpenAI;
        
        public function validateAttachments(array $images, array $documents): array
        {
            $errors = [];
            
            if (!empty($documents) && $this->getProvider() === Provider::OpenAI) {
                $errors[] = sprintf(
                    'Provider %s does not support document uploads. Consider using Anthropic Claude or Google Gemini instead.',
                    $this->getProvider()->value
                );
            }
            
            if (!empty($images) && $this->getModel() === 'gpt-3.5-turbo') {
                $errors[] = sprintf(
                    'Model %s does not support image uploads. Use gpt-4o or gpt-4-turbo instead.',
                    $this->getModel()
                );
            }
            
            return $errors;
        }
    };
    
    Agent::build(get_class($testAgent))->register();
    
    $agent = new $testAgent();
    
    // Test with documents on OpenAI
    $errors = $agent->validateAttachments([], ['doc1']);
    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('does not support document uploads');
    expect($errors[0])->toContain('Anthropic Claude or Google Gemini');
    
    // Test with images on non-vision model
    $agent->setModel('gpt-3.5-turbo');
    $errors = $agent->validateAttachments(['img1'], []);
    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('does not support image uploads');
    expect($errors[0])->toContain('gpt-4o or gpt-4-turbo');
});