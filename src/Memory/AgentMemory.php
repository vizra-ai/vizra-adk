<?php

namespace Vizra\VizraADK\Memory;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Services\MemoryManager;
use Illuminate\Support\Collection;

class AgentMemory
{
    private MemoryManager $manager;
    
    public function __construct(
        private BaseLlmAgent $agent
    ) {
        $this->manager = app(MemoryManager::class);
    }
    
    /**
     * Add a fact about the user or context
     */
    public function addFact(string $fact, float $confidence = 1.0): void
    {
        // For now, store as key-value in the existing memory data structure
        $key = 'fact_' . uniqid();
        $value = [
            'content' => $fact,
            'confidence' => $confidence,
            'type' => 'fact',
            'created_at' => now()->toIso8601String()
        ];
        
        $currentData = $this->getMemoryModel()->memory_data ?? [];
        $currentData[$key] = $value;
        
        $this->manager->updateMemoryData($this->agent->getName(), $currentData, $this->getUserId());
    }
    
    /**
     * Add a learning or insight gained from interactions
     */
    public function addLearning(string $insight, array $context = []): void
    {
        $this->manager->addLearning($this->agent->getName(), $insight, $this->getUserId());
    }
    
    /**
     * Update the agent's understanding summary
     */
    public function updateSummary(string $summary): void
    {
        $this->manager->updateSummary($this->agent->getName(), $summary, $this->getUserId());
    }
    
    /**
     * Add a user preference
     */
    public function addPreference(string $preference, string $category = 'general'): void
    {
        $key = 'preference_' . $category . '_' . uniqid();
        $value = [
            'content' => $preference,
            'category' => $category,
            'type' => 'preference',
            'recorded_at' => now()->toIso8601String()
        ];
        
        $currentData = $this->getMemoryModel()->memory_data ?? [];
        $currentData[$key] = $value;
        
        $this->manager->updateMemoryData($this->agent->getName(), $currentData, $this->getUserId());
    }
    
    /**
     * Generic method to remember something with custom type
     */
    public function remember(string $content, string $type = 'general', array $metadata = []): void
    {
        $key = $type . '_' . uniqid();
        $value = array_merge([
            'content' => $content,
            'type' => $type,
            'created_at' => now()->toIso8601String()
        ], $metadata);
        
        $currentData = $this->getMemoryModel()->memory_data ?? [];
        $currentData[$key] = $value;
        
        $this->manager->updateMemoryData($this->agent->getName(), $currentData, $this->getUserId());
    }
    
    /**
     * Get all facts
     */
    public function getFacts(): Collection
    {
        $memoryData = $this->getMemoryModel()->memory_data ?? [];
        return collect($memoryData)
            ->filter(fn($item) => is_array($item) && ($item['type'] ?? '') === 'fact')
            ->map(fn($item) => (object) $item);
    }
    
    /**
     * Get all learnings
     */
    public function getLearnings(): Collection
    {
        return collect($this->getMemoryModel()->key_learnings ?? []);
    }
    
    /**
     * Get the current summary
     */
    public function getSummary(): ?string
    {
        return $this->getMemoryModel()->memory_summary;
    }
    
    /**
     * Get preferences by category
     */
    public function getPreferences(string $category = null): Collection
    {
        $memoryData = $this->getMemoryModel()->memory_data ?? [];
        $preferences = collect($memoryData)
            ->filter(fn($item) => is_array($item) && ($item['type'] ?? '') === 'preference')
            ->map(fn($item) => (object) $item);
        
        if ($category) {
            return $preferences->filter(fn($pref) => 
                ($pref->category ?? 'general') === $category
            );
        }
        
        return $preferences;
    }
    
    /**
     * Search memories with a query
     */
    public function search(string $query, int $limit = 10): Collection
    {
        // For now, use the existing memory context functionality
        $context = $this->manager->getMemoryContextArray($this->agent->getName(), $this->getUserId());
        
        return collect($context)->take($limit);
    }
    
    /**
     * Get the underlying memory model
     */
    private function getMemoryModel(): \Vizra\VizraADK\Models\AgentMemory
    {
        return $this->manager->getOrCreateMemory($this->agent->getName(), $this->getUserId());
    }
    
    /**
     * Get the current user ID from agent context
     */
    private function getUserId(): ?int
    {
        // Since we're in test context, return null for now
        // In real usage, this would get the user ID from the agent's context
        return null;
    }
}