<?php

use Vizra\VizraSdk\Agents\BaseLlmAgent;
use Vizra\VizraSdk\System\AgentContext;
use Vizra\VizraSdk\Execution\AgentExecutor;
use Prism\Prism\ValueObjects\Messages\Support\Image;
use Prism\Prism\ValueObjects\Messages\Support\Document;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Create test files for testing
    if (!File::exists(storage_path('app/tests'))) {
        File::makeDirectory(storage_path('app/tests'), 0755, true);
    }
    
    // Create test image
    File::put(storage_path('app/tests/test-image.jpg'), 'fake image content');
    
    // Create test document
    File::put(storage_path('app/tests/test-document.pdf'), 'fake document content');
});

afterEach(function () {
    // Clean up test files
    File::deleteDirectory(storage_path('app/tests'));
});

it('can add image to agent conversation through executor', function () {
    // Create a test agent that extends BaseLlmAgent
    $testAgent = new class extends BaseLlmAgent {
        protected string $name = 'test_image_agent';
        protected string $description = 'Test agent for image functionality';
        protected string $instructions = 'You are a test agent that can process images.';
        protected string $model = 'gpt-4o';
    };

    // Use the fluent API to add an image
    $imagePath = storage_path('app/tests/test-image.jpg');
    
    // Create executor with image
    $executor = $testAgent::ask('What is in this image?')
        ->withImage($imagePath, 'image/jpeg')
        ->withSession('test-session');

    // Verify the executor has the image
    $reflection = new \ReflectionClass($executor);
    $imagesProperty = $reflection->getProperty('images');
    $imagesProperty->setAccessible(true);
    $images = $imagesProperty->getValue($executor);
    
    expect($images)->toHaveCount(1);
    expect($images[0])->toBeInstanceOf(Image::class);
});

it('can add multiple images to conversation', function () {
    $testAgent = new class extends BaseLlmAgent {
        protected string $name = 'test_multi_image_agent';
        protected string $description = 'Test agent for multiple images';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';
    };
    
    $executor = $testAgent::ask('Compare these images')
        ->withImage(storage_path('app/tests/test-image.jpg'))
        ->withImageFromUrl('https://example.com/image.jpg')
        ->withImageFromBase64('base64data', 'image/png')
        ->withSession('test-session');

    $reflection = new \ReflectionClass($executor);
    $imagesProperty = $reflection->getProperty('images');
    $imagesProperty->setAccessible(true);
    $images = $imagesProperty->getValue($executor);
    
    expect($images)->toHaveCount(3);
    expect($images[0])->toBeInstanceOf(Image::class);
    expect($images[1])->toBeInstanceOf(Image::class);
    expect($images[2])->toBeInstanceOf(Image::class);
});

it('can add document to agent conversation', function () {
    $testAgent = new class extends BaseLlmAgent {
        protected string $name = 'test_document_agent';
        protected string $description = 'Test agent for documents';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';
    };
    
    $documentPath = storage_path('app/tests/test-document.pdf');
    
    $executor = $testAgent::ask('Summarize this document')
        ->withDocument($documentPath, 'application/pdf')
        ->withSession('test-session');

    $reflection = new \ReflectionClass($executor);
    $documentsProperty = $reflection->getProperty('documents');
    $documentsProperty->setAccessible(true);
    $documents = $documentsProperty->getValue($executor);
    
    expect($documents)->toHaveCount(1);
    expect($documents[0])->toBeInstanceOf(Document::class);
});

it('can add multiple documents to conversation', function () {
    $testAgent = new class extends BaseLlmAgent {
        protected string $name = 'test_multi_doc_agent';
        protected string $description = 'Test agent for multiple documents';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';
    };
    
    $executor = $testAgent::ask('Compare these documents')
        ->withDocument(storage_path('app/tests/test-document.pdf'))
        ->withDocumentFromUrl('https://example.com/document.pdf')
        ->withDocumentFromBase64('base64data', 'application/pdf')
        ->withSession('test-session');

    $reflection = new \ReflectionClass($executor);
    $documentsProperty = $reflection->getProperty('documents');
    $documentsProperty->setAccessible(true);
    $documents = $documentsProperty->getValue($executor);
    
    expect($documents)->toHaveCount(3);
    expect($documents[0])->toBeInstanceOf(Document::class);
    expect($documents[1])->toBeInstanceOf(Document::class);
    expect($documents[2])->toBeInstanceOf(Document::class);
});

it('can combine images and documents', function () {
    $testAgent = new class extends BaseLlmAgent {
        protected string $name = 'test_combined_agent';
        protected string $description = 'Test agent for combined attachments';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';
    };
    
    $executor = $testAgent::ask('Analyze these files')
        ->withImage(storage_path('app/tests/test-image.jpg'))
        ->withDocument(storage_path('app/tests/test-document.pdf'))
        ->withSession('test-session');

    $reflection = new \ReflectionClass($executor);
    
    $imagesProperty = $reflection->getProperty('images');
    $imagesProperty->setAccessible(true);
    $images = $imagesProperty->getValue($executor);
    
    $documentsProperty = $reflection->getProperty('documents');
    $documentsProperty->setAccessible(true);
    $documents = $documentsProperty->getValue($executor);
    
    expect($images)->toHaveCount(1);
    expect($documents)->toHaveCount(1);
    expect($images[0])->toBeInstanceOf(Image::class);
    expect($documents[0])->toBeInstanceOf(Document::class);
});

it('maintains fluent interface with other methods', function () {
    $testAgent = new class extends BaseLlmAgent {
        protected string $name = 'test_fluent_agent';
        protected string $description = 'Test agent for fluent interface';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';
    };
    
    $executor = $testAgent::ask('Analyze with custom settings')
        ->withImage(storage_path('app/tests/test-image.jpg'))
        ->temperature(0.5)
        ->maxTokens(500)
        ->withDocument(storage_path('app/tests/test-document.pdf'))
        ->withContext(['extra_info' => 'test'])
        ->withSession('test-session');

    $reflection = new \ReflectionClass($executor);
    
    // Check images
    $imagesProperty = $reflection->getProperty('images');
    $imagesProperty->setAccessible(true);
    expect($imagesProperty->getValue($executor))->toHaveCount(1);
    
    // Check documents
    $documentsProperty = $reflection->getProperty('documents');
    $documentsProperty->setAccessible(true);
    expect($documentsProperty->getValue($executor))->toHaveCount(1);
    
    // Check parameters
    $parametersProperty = $reflection->getProperty('parameters');
    $parametersProperty->setAccessible(true);
    $parameters = $parametersProperty->getValue($executor);
    expect($parameters['temperature'])->toBe(0.5);
    expect($parameters['max_tokens'])->toBe(500);
    
    // Check context
    $contextProperty = $reflection->getProperty('context');
    $contextProperty->setAccessible(true);
    $context = $contextProperty->getValue($executor);
    expect($context['extra_info'])->toBe('test');
});

it('passes prism images and documents through executor to context', function () {
    $this->markTestSkipped('This test requires full integration setup');
    // Create a test agent that can inspect what's passed to it
    $testAgent = new class extends BaseLlmAgent {
        protected string $name = 'test_context_agent';
        protected string $description = 'Test agent for context passing';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';
        
        public static $capturedContext = null;
        
        public function run(mixed $input, AgentContext $context): mixed
        {
            // Capture the context for inspection
            self::$capturedContext = $context;
            
            // Get the images and documents from context
            $images = $context->getState('prism_images', []);
            $documents = $context->getState('prism_documents', []);
            
            return json_encode([
                'images_count' => count($images),
                'documents_count' => count($documents),
                'has_images' => !empty($images),
                'has_documents' => !empty($documents),
                'first_image_is_image' => isset($images[0]) && $images[0] instanceof Image,
                'first_document_is_document' => isset($documents[0]) && $documents[0] instanceof Document,
            ]);
        }
    };
    
    // Reset static property
    $testAgent::$capturedContext = null;
    
    // Register the test agent
    app(\Vizra\VizraSdk\Services\AgentRegistry::class)
        ->register('test_context_agent', get_class($testAgent));
    
    // Execute with attachments
    $result = $testAgent::ask('Test context passing')
        ->withImage(storage_path('app/tests/test-image.jpg'))
        ->withDocument(storage_path('app/tests/test-document.pdf'))
        ->withSession('test-session')
        ->execute();
    
    $decodedResult = json_decode($result, true);
    
    expect($decodedResult['images_count'])->toBe(1);
    expect($decodedResult['documents_count'])->toBe(1);
    expect($decodedResult['has_images'])->toBeTrue();
    expect($decodedResult['has_documents'])->toBeTrue();
    expect($decodedResult['first_image_is_image'])->toBeTrue();
    expect($decodedResult['first_document_is_document'])->toBeTrue();
    
    // Also verify the context was set
    expect($testAgent::$capturedContext)->not->toBeNull();
    $images = $testAgent::$capturedContext->getState('prism_images', []);
    $documents = $testAgent::$capturedContext->getState('prism_documents', []);
    expect($images)->toHaveCount(1);
    expect($documents)->toHaveCount(1);
});

it('correctly adds attachments to user messages in conversation history', function () {
    $this->markTestSkipped('This test requires full integration setup');
    $testAgent = new class extends BaseLlmAgent {
        protected string $name = 'test_history_agent';
        protected string $description = 'Test agent for conversation history';
        protected string $instructions = 'You are a test agent.';
        protected string $model = 'gpt-4o';
        
        public function run(mixed $input, AgentContext $context): mixed
        {
            // Get the images and documents from context
            $images = $context->getState('prism_images', []);
            $documents = $context->getState('prism_documents', []);
            
            // Check the conversation history
            $history = $context->getConversationHistory();
            
            // Handle both array and Collection returns
            if ($history instanceof \Illuminate\Support\Collection) {
                $history = $history->toArray();
            }
            
            $lastUserMessage = null;
            foreach (array_reverse($history) as $message) {
                if ($message['role'] === 'user') {
                    $lastUserMessage = $message;
                    break;
                }
            }
            
            return [
                'has_user_message' => $lastUserMessage !== null,
                'user_message_has_images' => isset($lastUserMessage['images']),
                'user_message_has_documents' => isset($lastUserMessage['documents']),
                'images_in_message' => $lastUserMessage['images'] ?? [],
                'documents_in_message' => $lastUserMessage['documents'] ?? [],
            ];
        }
    };
    
    // Register the test agent
    app(\Vizra\VizraSdk\Services\AgentRegistry::class)
        ->register('test_history_agent', get_class($testAgent));
    
    $result = $testAgent::ask('Test history')
        ->withImage(storage_path('app/tests/test-image.jpg'))
        ->withDocument(storage_path('app/tests/test-document.pdf'))
        ->withSession('test-session')
        ->execute();
    
    expect($result['has_user_message'])->toBeTrue();
    expect($result['user_message_has_images'])->toBeTrue();
    expect($result['user_message_has_documents'])->toBeTrue();
    expect($result['images_in_message'])->toHaveCount(1);
    expect($result['documents_in_message'])->toHaveCount(1);
});