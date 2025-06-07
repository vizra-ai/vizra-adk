<?php

namespace AaronLumsden\LaravelAgentADK\Console\Commands;

use AaronLumsden\LaravelAgentADK\Services\VectorMemoryManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class VectorMemoryStore extends Command
{
    protected $signature = 'vector:store 
                           {agent : The agent name to store memory for}
                           {--file= : File path to read content from}
                           {--content= : Direct content to store}
                           {--namespace=default : Memory namespace}
                           {--source= : Source identifier}
                           {--source-id= : Source ID}
                           {--metadata= : JSON metadata}';

    protected $description = 'Store content in vector memory for an agent';

    public function handle(VectorMemoryManager $vectorMemory): int
    {
        $agentName = $this->argument('agent');
        $namespace = $this->option('namespace');
        $source = $this->option('source');
        $sourceId = $this->option('source-id');
        $metadataJson = $this->option('metadata');

        // Get content from file or direct input
        $content = null;
        if ($filePath = $this->option('file')) {
            if (!File::exists($filePath)) {
                $this->error("File not found: {$filePath}");
                return 1;
            }
            $content = File::get($filePath);
            $source = $source ?? basename($filePath);
        } elseif ($directContent = $this->option('content')) {
            $content = $directContent;
        } else {
            $this->error('Either --file or --content must be provided');
            return 1;
        }

        if (empty(trim($content))) {
            $this->error('Content is empty');
            return 1;
        }

        // Parse metadata
        $metadata = [];
        if ($metadataJson) {
            $metadata = json_decode($metadataJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON in metadata: ' . json_last_error_msg());
                return 1;
            }
        }

        $this->info("Storing content in vector memory...");
        $this->info("Agent: {$agentName}");
        $this->info("Namespace: {$namespace}");
        $this->info("Content length: " . strlen($content) . " characters");

        try {
            $memories = $vectorMemory->addDocument(
                agentName: $agentName,
                content: $content,
                metadata: $metadata,
                namespace: $namespace,
                source: $source,
                sourceId: $sourceId
            );

            $this->info("✅ Successfully stored {$memories->count()} chunks in vector memory");
            
            if ($memories->count() > 1) {
                $this->info("Document was automatically chunked for optimal embedding");
            }

            // Show some statistics
            $stats = $vectorMemory->getStatistics($agentName, $namespace);
            $this->newLine();
            $this->info("📊 Agent Statistics:");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Memories', $stats['total_memories']],
                    ['Total Tokens', number_format($stats['total_tokens'])],
                    ['Embedding Providers', implode(', ', array_keys($stats['providers']))],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to store content: " . $e->getMessage());
            return 1;
        }
    }
}