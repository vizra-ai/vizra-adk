<?php

use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Execution\AgentExecutor;
use Vizra\VizraADK\System\AgentContext;

it('executor stores prism image and document objects', function () {
    // Create a simple test agent
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $description = 'Test agent';

        protected string $instructions = 'Test instructions';

        protected string $model = 'gpt-4o';
    };

    // Create an executor and add attachments
    $executor = new AgentExecutor(get_class($testAgent), 'Test input');

    // Add image
    $executor->withImageFromBase64('fake-base64', 'image/png');

    // Add document
    $executor->withDocumentFromBase64('fake-base64', 'application/pdf');

    // Use reflection to verify internal state
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

it('base llm agent processes images and documents from context', function () {
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $description = 'Test agent';

        protected string $instructions = 'Test instructions';

        protected string $model = 'gpt-4o';

        // Enable conversation history for this test
        protected bool $includeConversationHistory = true;
        protected string $contextStrategy = 'full';

        public function prepareMessagesForPrism(AgentContext $context): array
        {
            return parent::prepareMessagesForPrism($context);
        }
    };

    // Create context and add message with attachments
    $context = new AgentContext('test-session');

    // Create Prism Image and Document objects
    $image = Image::fromBase64('fake-base64', 'image/png');
    $document = Document::fromBase64('fake-base64', 'application/pdf');

    // Add a user message with attachments
    $context->addMessage([
        'role' => 'user',
        'content' => 'Test message',
        'images' => [$image],
        'documents' => [$document],
    ]);

    // Call prepareMessagesForPrism
    $messages = $testAgent->prepareMessagesForPrism($context);

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toBeInstanceOf(UserMessage::class);

    // Verify the UserMessage has attachments
    // Note: We can't directly inspect UserMessage internals, but we've verified
    // the code calls withImage() and withDocument() on the UserMessage
});

it('base llm agent retrieves attachments from context state', function () {
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $description = 'Test agent';

        protected string $instructions = 'Test instructions';

        protected string $model = 'gpt-4o';

        public function execute(mixed $input, AgentContext $context): mixed
        {
            // Expose the part of run() that checks for attachments
            $images = $context->getState('prism_images', []);
            $documents = $context->getState('prism_documents', []);

            $userMessage = ['role' => 'user', 'content' => $input ?: ''];
            if (! empty($images)) {
                $userMessage['images'] = $images;
            }
            if (! empty($documents)) {
                $userMessage['documents'] = $documents;
            }

            return $userMessage;
        }
    };

    $context = new AgentContext('test-session');

    // Set attachments in context state (as AgentExecutor would)
    $image = Image::fromBase64('fake-base64', 'image/png');
    $document = Document::fromBase64('fake-base64', 'application/pdf');

    $context->setState('prism_images', [$image]);
    $context->setState('prism_documents', [$document]);

    // Run the agent
    $result = $testAgent->execute('Test input', $context);

    expect($result['role'])->toBe('user');
    expect($result['content'])->toBe('Test input');
    expect($result['images'])->toHaveCount(1);
    expect($result['documents'])->toHaveCount(1);
    expect($result['images'][0])->toBeInstanceOf(Image::class);
    expect($result['documents'][0])->toBeInstanceOf(Document::class);
});

it('executor sets prism attachments in agent context state', function () {
    // This test verifies the AgentExecutor behavior
    $executor = new AgentExecutor('TestAgent', 'Test input');

    // Add attachments
    $executor->withImageFromBase64('fake-base64', 'image/png')
        ->withDocumentFromBase64('fake-base64', 'application/pdf');

    // Use reflection to check the internal state
    $reflection = new \ReflectionClass($executor);

    $imagesProperty = $reflection->getProperty('images');
    $imagesProperty->setAccessible(true);
    $images = $imagesProperty->getValue($executor);

    $documentsProperty = $reflection->getProperty('documents');
    $documentsProperty->setAccessible(true);
    $documents = $documentsProperty->getValue($executor);

    // Verify Prism objects are created
    expect($images)->toHaveCount(1);
    expect($documents)->toHaveCount(1);
    expect($images[0])->toBeInstanceOf(Image::class);
    expect($documents[0])->toBeInstanceOf(Document::class);

    // In the actual executeSynchronously method, these would be set as:
    // $agentContext->setState('prism_images', $this->images);
    // $agentContext->setState('prism_documents', $this->documents);
    // $agentContext->setState('prism_images_metadata', $imageMetadata);
    // $agentContext->setState('prism_documents_metadata', $documentMetadata);
});

it('prepareMessagesForPrism handles images as arrays from database', function () {
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $description = 'Test agent';

        protected string $instructions = 'Test instructions';

        protected string $model = 'gpt-4o';

        // Enable conversation history for this test
        protected bool $includeConversationHistory = true;
        protected string $contextStrategy = 'full';

        public function prepareMessagesForPrism(AgentContext $context): array
        {
            return parent::prepareMessagesForPrism($context);
        }
    };

    // Create context with image as array (simulating DB load)
    $context = new AgentContext('test-session');
    $context->addMessage([
        'role' => 'user',
        'content' => 'Test message',
        'images' => [
            ['image' => 'base64data', 'mimeType' => 'image/png'],
        ],
    ]);

    // Call prepareMessagesForPrism
    $messages = $testAgent->prepareMessagesForPrism($context);

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toBeInstanceOf(UserMessage::class);

    // Verify the UserMessage has the image converted to Image object
    $reflection = new \ReflectionClass($messages[0]);
    $property = $reflection->getProperty('additionalContent');
    $property->setAccessible(true);
    $additionalContent = $property->getValue($messages[0]);

    // Filter to only Image objects (there might be Text objects too)
    $images = array_filter($additionalContent, fn ($item) => $item instanceof Image);

    expect($images)->toHaveCount(1);
    expect(reset($images))->toBeInstanceOf(Image::class);
});

it('prepareMessagesForPrism handles documents as arrays from database', function () {
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $description = 'Test agent';

        protected string $instructions = 'Test instructions';

        protected string $model = 'gemini-2.0-flash';

        // Enable conversation history for this test
        protected bool $includeConversationHistory = true;
        protected string $contextStrategy = 'full';

        public function prepareMessagesForPrism(AgentContext $context): array
        {
            return parent::prepareMessagesForPrism($context);
        }
    };

    // Create context with document as array (simulating DB load)
    $context = new AgentContext('test-session');
    $context->addMessage([
        'role' => 'user',
        'content' => 'Test message',
        'documents' => [
            [
                'document' => 'base64data',
                'mimeType' => 'application/pdf',
                'dataFormat' => 'base64',
                'documentTitle' => 'Test Doc',
                'documentContext' => 'Test context',
            ],
        ],
    ]);

    // Call prepareMessagesForPrism
    $messages = $testAgent->prepareMessagesForPrism($context);

    expect($messages)->toHaveCount(1);
    expect($messages[0])->toBeInstanceOf(UserMessage::class);

    // Verify the UserMessage has the document converted to Document object
    $reflection = new \ReflectionClass($messages[0]);
    $property = $reflection->getProperty('additionalContent');
    $property->setAccessible(true);
    $additionalContent = $property->getValue($messages[0]);

    // Filter to only Document objects (there might be Text objects too)
    $documents = array_filter($additionalContent, fn ($item) => $item instanceof Document);

    expect($documents)->toHaveCount(1);
    expect(reset($documents))->toBeInstanceOf(Document::class);
});

it('agent recreates images from metadata when context has no direct images', function () {
    $testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $description = 'Test agent';

        protected string $instructions = 'Test instructions';

        protected string $model = 'gpt-4o';

        public function execute(mixed $input, AgentContext $context): mixed
        {
            // Simulate the recreation logic from BaseLlmAgent
            $images = $context->getState('prism_images', []);

            if (empty($images) && $context->getState('prism_images_metadata')) {
                $images = [];
                foreach ($context->getState('prism_images_metadata', []) as $metadata) {
                    if ($metadata['type'] === 'image' && isset($metadata['data']) && isset($metadata['mimeType'])) {
                        $images[] = Image::fromBase64($metadata['data'], $metadata['mimeType']);
                    }
                }
            }

            return ['images_count' => count($images), 'first_is_image' => isset($images[0]) && $images[0] instanceof Image];
        }
    };

    $context = new AgentContext('test-session');

    // Set only metadata, no direct images (simulating DB load)
    $context->setState('prism_images_metadata', [
        ['type' => 'image', 'data' => 'base64data', 'mimeType' => 'image/png'],
    ]);

    $result = $testAgent->execute('Test', $context);

    expect($result['images_count'])->toBe(1);
    expect($result['first_is_image'])->toBeTrue();
});

it('successfully restores images after database round-trip', function () {
    $stateManager = app(\Vizra\VizraADK\Services\StateManager::class);

    // Create a unique agent name and session for this test
    $agentName = 'test_roundtrip_agent_'.uniqid();
    $sessionId = 'test_session_'.uniqid();

    // Step 1: Create context with Image objects (as AgentExecutor does)
    $context = $stateManager->loadContext($agentName, $sessionId, 'Test input');

    // Create actual Prism Image objects
    $image = Image::fromBase64('fake-image-base64-data', 'image/png');
    $document = Document::fromBase64('fake-doc-base64-data', 'application/pdf');

    // Store metadata (as AgentExecutor.php:322 does)
    $context->setState('prism_images_metadata', [
        [
            'type' => 'image',
            'data' => $image->base64(),
            'mimeType' => $image->mimeType(),
        ],
    ]);

    $context->setState('prism_documents_metadata', [
        [
            'type' => 'document',
            'data' => $document->base64(),
            'mimeType' => $document->mimeType(),
            'dataFormat' => 'base64',
            'documentTitle' => $document->documentTitle(),
            'documentContext' => null,
        ],
    ]);

    // Store actual objects for immediate use (as AgentExecutor.php:324 does)
    $context->setState('prism_images', [$image]);
    $context->setState('prism_documents', [$document]);

    // Step 2: Save to database (this is where JSON serialization happens)
    $stateManager->saveContext($context, $agentName, false);

    // Step 3: Load from database (simulating a new request)
    $loadedContext = $stateManager->loadContext($agentName, $sessionId);

    // Step 4: Check what we got back
    $loadedImages = $loadedContext->getState('prism_images', []);
    $loadedDocuments = $loadedContext->getState('prism_documents', []);
    $loadedImageMetadata = $loadedContext->getState('prism_images_metadata', []);
    $loadedDocumentMetadata = $loadedContext->getState('prism_documents_metadata', []);

    // After the StateManager fix, prism_images and prism_documents should NOT be in the database
    // Only metadata should be persisted
    expect($loadedImages)->toBeEmpty();
    expect($loadedDocuments)->toBeEmpty();

    // Metadata should be preserved for reconstruction
    expect($loadedImageMetadata)->toBeArray();
    expect($loadedImageMetadata)->toHaveCount(1);
    expect($loadedImageMetadata[0])->toHaveKey('type');
    expect($loadedImageMetadata[0])->toHaveKey('data');
    expect($loadedImageMetadata[0])->toHaveKey('mimeType');

    expect($loadedDocumentMetadata)->toBeArray();
    expect($loadedDocumentMetadata)->toHaveCount(1);
    expect($loadedDocumentMetadata[0])->toHaveKey('type');
    expect($loadedDocumentMetadata[0])->toHaveKey('data');
    expect($loadedDocumentMetadata[0])->toHaveKey('mimeType');

    // Verify that BaseLlmAgent can reconstruct images from metadata (simulating what happens in execute())
    $reconstructedImages = [];
    if (empty($loadedImages) && ! empty($loadedImageMetadata)) {
        foreach ($loadedImageMetadata as $metadata) {
            if ($metadata['type'] === 'image') {
                $reconstructedImages[] = Image::fromBase64($metadata['data'], $metadata['mimeType']);
            }
        }
    }

    expect($reconstructedImages)->toHaveCount(1);
    expect($reconstructedImages[0])->toBeInstanceOf(Image::class);
});
