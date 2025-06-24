# Vizra ADK Attachment Testing Guide

## Overview

This document describes the comprehensive test suite for image and document attachments in the Vizra ADK.

## Test Files

### Unit Tests
- `tests/Unit/AgentAttachmentsUnitTest.php` - Core unit tests for attachment functionality
  - Tests AgentExecutor attachment storage
  - Tests BaseLlmAgent attachment processing
  - Tests metadata storage and recreation
  - Tests array handling from database

### Feature Tests
- `tests/Feature/AgentAttachmentsTest.php` - Integration tests for attachments
  - Tests fluent API for adding attachments
  - Tests multiple attachments
  - Tests combining images and documents
  
- `tests/Feature/AgentAttachmentsIntegrationTest.php` - Full integration tests
  - Tests metadata persistence across sessions
  - Tests context state management
  - Tests database storage and retrieval
  - Tests provider-specific behavior

- `tests/Feature/ProviderAttachmentSupportTest.php` - Provider capability tests
  - Tests provider limitations (OpenAI, Anthropic, Gemini)
  - Tests model-specific capabilities
  - Tests error handling and suggestions

## Key Test Scenarios

### 1. Image Handling
```php
// Test image upload via fluent API
$response = Agent::ask('Analyze this image')
    ->withImage('/path/to/image.jpg')
    ->go();

// Test multiple image formats
->withImage($path)                              // From file path
->withImageFromBase64($data, 'image/png')      // From base64
->withImageFromUrl('https://example.com/img')  // From URL
```

### 2. Document Handling
```php
// Test document upload (Gemini/Anthropic only)
$response = Agent::ask('Summarize this document')
    ->withDocument('/path/to/doc.pdf')
    ->go();

// Test multiple document formats
->withDocument($path)                           // From file path
->withDocumentFromBase64($data, 'application/pdf')  // From base64
->withDocumentFromUrl('https://example.com/doc')    // From URL
```

### 3. Metadata Storage
Tests verify that:
- Prism Image/Document objects are converted to metadata for database storage
- Metadata includes all necessary fields (data, mimeType, dataFormat, etc.)
- Context state persists across agent executions

### 4. Object Recreation
Tests verify that:
- Images are recreated from metadata when context is loaded
- Documents are recreated with correct format handling
- Arrays from database are converted back to Prism objects

### 5. Provider Limitations
Tests verify that:
- OpenAI supports images but not documents
- Anthropic and Gemini support both images and documents
- Appropriate error messages are provided for unsupported features

## Running Tests

### Run All Attachment Tests
```bash
# Unit tests
./vendor/bin/pest tests/Unit/AgentAttachmentsUnitTest.php

# Feature tests
./vendor/bin/pest tests/Feature/AgentAttachments*Test.php

# Provider tests
./vendor/bin/pest tests/Feature/ProviderAttachmentSupportTest.php
```

### Run Specific Test
```bash
./vendor/bin/pest --filter="handles images as arrays"
```

## Test Data

Tests use minimal test files:
- **Images**: 1x1 transparent PNG (base64 encoded)
- **Documents**: Simple PDF with text content

Test files are created in `storage/app/tests/` and cleaned up after each test.

## Key Assertions

### Unit Tests
- Verify Prism objects are created correctly
- Verify metadata extraction from Prism objects
- Verify array-to-object conversion
- Verify context state management

### Integration Tests
- Verify end-to-end attachment flow
- Verify database persistence
- Verify session continuity
- Verify provider-specific behavior

## Common Issues

### 1. Provider API Keys
Some tests require valid API keys. Tests will skip if keys are not configured:
```php
if (!config('prism.providers.anthropic.api_key')) {
    $this->markTestSkipped('No Anthropic API key configured');
}
```

### 2. File Permissions
Ensure the test directory is writable:
```bash
chmod -R 755 storage/app/tests
```

### 3. Memory Limits
Large attachments may require increased memory:
```php
ini_set('memory_limit', '256M');
```

## Future Enhancements

1. **Performance Tests**: Test large file handling
2. **Async Tests**: Test attachment handling in queued jobs
3. **Streaming Tests**: Test streaming responses with attachments
4. **Error Recovery**: Test graceful handling of corrupted attachments