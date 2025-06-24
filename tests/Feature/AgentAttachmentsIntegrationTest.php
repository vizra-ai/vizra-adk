<?php

use Illuminate\Support\Facades\File;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\Support\Document;
use Prism\Prism\ValueObjects\Messages\Support\Image;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Models\AgentSession;
use Vizra\VizraADK\System\AgentContext;

beforeEach(function () {
    // Create test files for testing
    if (! File::exists(storage_path('app/tests'))) {
        File::makeDirectory(storage_path('app/tests'), 0755, true);
    }

    // Create test image (1x1 transparent PNG)
    $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    File::put(storage_path('app/tests/test-image.png'), $imageData);

    // Create test PDF
    $pdfContent = '%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> /MediaBox [0 0 612 792] /Contents 4 0 R >>
endobj
4 0 obj
<< /Length 44 >>
stream
BT
/F1 12 Tf
100 700 Td
(Test PDF Document) Tj
ET
endstream
endobj
xref
0 5
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000307 00000 n 
trailer
<< /Size 5 /Root 1 0 R >>
startxref
399
%%EOF';
    File::put(storage_path('app/tests/test-document.pdf'), $pdfContent);
});

afterEach(function () {
    // Clean up test files
    File::deleteDirectory(storage_path('app/tests'));
    
    // Clean up database
    AgentSession::query()->delete();
});

it('stores image metadata in context and recreates Image objects', function () {
    // Create a test agent that inspects context
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_metadata_agent';
        protected string $description = 'Test agent for metadata';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';

        public function run(mixed $input, AgentContext $context): mixed
        {
            // Check metadata storage
            $metadata = $context->getState('prism_images_metadata', []);
            $images = $context->getState('prism_images', []);
            
            // In the actual flow, images might be empty if loaded from DB
            // but metadata should be present
            
            return json_encode([
                'metadata_count' => count($metadata),
                'images_count' => count($images),
                'metadata_structure' => $metadata,
            ]);
        }
    };

    // Register the test agent
    Agent::build(get_class($testAgent))->register();

    // Execute with image
    $result = $testAgent::ask('Test metadata storage')
        ->withImage(storage_path('app/tests/test-image.png'))
        ->withSession('test-metadata-session')
        ->go();

    $decoded = json_decode($result, true);
    
    expect($decoded['metadata_count'])->toBe(1);
    expect($decoded['metadata_structure'][0])->toHaveKeys(['type', 'data', 'mimeType']);
    expect($decoded['metadata_structure'][0]['type'])->toBe('image');
    expect($decoded['metadata_structure'][0]['mimeType'])->toBe('image/png');
    expect($decoded['metadata_structure'][0]['data'])->toBeString();
});

it('handles images from arrays when loaded from database', function () {
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_array_handling';
        protected string $description = 'Test array handling';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';

        public function prepareMessagesForPrism(AgentContext $context): array
        {
            return parent::prepareMessagesForPrism($context);
        }
    };

    // Create a context with images as arrays (simulating DB load)
    $context = new AgentContext('test-session');
    $context->addMessage([
        'role' => 'user',
        'content' => 'Test with array image',
        'images' => [
            [
                'image' => 'base64data',
                'mimeType' => 'image/png'
            ]
        ]
    ]);
    
    // Test the prepareMessagesForPrism method directly
    $agent = new $testAgent();
    $messages = $agent->prepareMessagesForPrism($context);
    
    expect($messages)->toHaveCount(1);
    
    // Get additional content from the message
    $firstMessage = $messages[0];
    $reflection = new \ReflectionClass($firstMessage);
    $property = $reflection->getProperty('additionalContent');
    $property->setAccessible(true);
    $additionalContent = $property->getValue($firstMessage);
    
    // Filter to only Image objects
    $images = array_filter($additionalContent, fn($item) => $item instanceof Image);
    
    expect($images)->toHaveCount(1);
    expect(reset($images))->toBeInstanceOf(Image::class);
});

it('handles document metadata storage and recreation', function () {
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_doc_metadata';
        protected string $description = 'Test document metadata';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gemini-2.0-flash'; // Use Gemini since it supports documents

        public function run(mixed $input, AgentContext $context): mixed
        {
            $metadata = $context->getState('prism_documents_metadata', []);
            
            return json_encode([
                'metadata_count' => count($metadata),
                'has_data_format' => isset($metadata[0]['dataFormat']),
                'data_format' => $metadata[0]['dataFormat'] ?? null,
            ]);
        }
    };

    Agent::build(get_class($testAgent))->register();

    $result = $testAgent::ask('Test document metadata')
        ->withDocument(storage_path('app/tests/test-document.pdf'))
        ->withSession('test-doc-session')
        ->go();

    $decoded = json_decode($result, true);
    
    expect($decoded['metadata_count'])->toBe(1);
    expect($decoded['has_data_format'])->toBeTrue();
    expect($decoded['data_format'])->toBe('base64');
});

it('persists attachment metadata across context saves', function () {
    // Skip this test if no real API keys are available
    if (config('prism.providers.openai.api_key') === 'test-key') {
        $this->markTestSkipped('Test requires real API key');
    }
    
    $sessionId = 'persistence-test-session';
    
    // First agent execution with attachments
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_persistence';
        protected string $description = 'Test persistence';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';

        public function run(mixed $input, AgentContext $context): mixed
        {
            return 'First execution';
        }
    };

    Agent::build(get_class($testAgent))->register();

    // First execution - add image
    $testAgent::ask('First message')
        ->withImage(storage_path('app/tests/test-image.png'))
        ->withSession($sessionId)
        ->go();

    // Verify session was saved with metadata
    $session = AgentSession::where('session_id', $sessionId)->first();
    expect($session)->not->toBeNull();
    expect($session->state_data)->toHaveKey('prism_images_metadata');
    expect($session->state_data['prism_images_metadata'])->toHaveCount(1);

    // Second execution - metadata should be available
    $testAgent2 = new class extends BaseLlmAgent
    {
        protected string $name = 'test_persistence';
        protected string $description = 'Test persistence';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';

        public function run(mixed $input, AgentContext $context): mixed
        {
            $metadata = $context->getState('prism_images_metadata', []);
            return json_encode(['metadata_count' => count($metadata)]);
        }
    };

    Agent::build(get_class($testAgent2))->register();

    $result = $testAgent2::ask('Second message')
        ->withSession($sessionId)
        ->go();

    $decoded = json_decode($result, true);
    expect($decoded['metadata_count'])->toBe(1);
});

it('recreates Image objects from metadata when context is loaded', function () {
    // Skip this test if no real API keys are available
    if (config('prism.providers.openai.api_key') === 'test-key') {
        $this->markTestSkipped('Test requires real API key');
    }
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_recreation';
        protected string $description = 'Test recreation';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';

        public function run(mixed $input, AgentContext $context): mixed
        {
            // The BaseLlmAgent should recreate images from metadata
            $images = $context->getState('prism_images', []);
            $metadata = $context->getState('prism_images_metadata', []);
            
            // Initially, if loaded from DB, images might be empty
            // But our code in BaseLlmAgent::run should recreate them
            
            return parent::run($input, $context);
        }
        
        protected function prepareMessagesForPrism(AgentContext $context): array
        {
            $messages = parent::prepareMessagesForPrism($context);
            
            // Verify we have messages with images
            foreach ($messages as $message) {
                if ($message instanceof \Prism\Prism\ValueObjects\Messages\UserMessage) {
                    $reflection = new \ReflectionClass($message);
                    $property = $reflection->getProperty('additionalContent');
                    $property->setAccessible(true);
                    $additionalContent = $property->getValue($message);
                    
                    // Should have recreated Image objects
                    foreach ($additionalContent as $content) {
                        if ($content instanceof Image) {
                            return [new \Prism\Prism\ValueObjects\Messages\AssistantMessage('Image found')];
                        }
                    }
                }
            }
            
            return [new \Prism\Prism\ValueObjects\Messages\AssistantMessage('No image found')];
        }
    };

    Agent::build(get_class($testAgent))->register();

    // Simulate metadata in context (as if loaded from DB)
    $context = new AgentContext('test-session');
    $context->setState('prism_images_metadata', [
        [
            'type' => 'image',
            'data' => base64_encode('fake image data'),
            'mimeType' => 'image/png'
        ]
    ]);

    // Save to simulate DB persistence
    app(\Vizra\VizraADK\Services\StateManager::class)->saveContext($context, 'test_recreation', false);

    // Execute agent - should recreate images
    $result = $testAgent::ask('Test recreation')
        ->withSession('test-session')
        ->go();

    expect($result)->toBe('Image found');
});

it('handles multiple attachments correctly', function () {
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_multiple';
        protected string $description = 'Test multiple attachments';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gemini-2.0-flash';

        public function run(mixed $input, AgentContext $context): mixed
        {
            $imageMetadata = $context->getState('prism_images_metadata', []);
            $docMetadata = $context->getState('prism_documents_metadata', []);
            
            return json_encode([
                'images' => count($imageMetadata),
                'documents' => count($docMetadata),
            ]);
        }
    };

    Agent::build(get_class($testAgent))->register();

    $result = $testAgent::ask('Test multiple attachments')
        ->withImage(storage_path('app/tests/test-image.png'))
        ->withImageFromBase64('anotherimage', 'image/jpeg')
        ->withDocument(storage_path('app/tests/test-document.pdf'))
        ->withDocumentFromBase64('anotherdoc', 'application/pdf')
        ->withSession('test-multiple-session')
        ->go();

    $decoded = json_decode($result, true);
    expect($decoded['images'])->toBe(2);
    expect($decoded['documents'])->toBe(2);
});

it('handles provider-specific attachment support gracefully', function () {
    // Test with OpenAI (doesn't support documents)
    $openAiAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_openai';
        protected string $description = 'OpenAI test';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';
        protected ?Provider $provider = Provider::OpenAI;

        public function run(mixed $input, AgentContext $context): mixed
        {
            // OpenAI should handle images but not documents
            return 'OpenAI executed';
        }
    };

    Agent::build(get_class($openAiAgent))->register();

    // This should work (images are supported)
    $result = $openAiAgent::ask('Test with image')
        ->withImage(storage_path('app/tests/test-image.png'))
        ->withSession('test-openai-session')
        ->go();

    expect($result)->toBe('OpenAI executed');

    // Test with Gemini (supports both)
    $geminiAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_gemini';
        protected string $description = 'Gemini test';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gemini-2.0-flash';
        protected ?Provider $provider = Provider::Gemini;

        public function run(mixed $input, AgentContext $context): mixed
        {
            return 'Gemini executed';
        }
    };

    Agent::build(get_class($geminiAgent))->register();

    // This should work (both images and documents are supported)
    $result = $geminiAgent::ask('Test with both')
        ->withImage(storage_path('app/tests/test-image.png'))
        ->withDocument(storage_path('app/tests/test-document.pdf'))
        ->withSession('test-gemini-session')
        ->go();

    expect($result)->toBe('Gemini executed');
});