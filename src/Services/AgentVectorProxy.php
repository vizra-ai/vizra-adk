<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Support\Collection;
use Vizra\VizraADK\Models\VectorMemory;

/**
 * Proxy class for vector memory operations within an agent context.
 * Automatically injects the agent class into all vector memory operations.
 *
 * @method Collection addDocument(string|array $contentOrArray, array $metadata = null)
 * @method VectorMemory addChunk(string|array $contentOrArray, array $metadata = null)
 * @method Collection search(string|array $queryOrArray, int $limit = null)
 * @method array generateRagContext(string|array $queryOrArray, array $options = null)
 * @method int deleteMemories(string|array $namespaceOrArray = null)
 * @method int deleteMemoriesBySource(string|array $sourceOrArray, string $namespace = null)
 * @method array getStatistics(string|array $namespaceOrArray = null)
 */
class AgentVectorProxy
{
    /**
     * Create a new agent vector proxy instance.
     */
    public function __construct(
        private string $agentClass,
        private VectorMemoryManager $manager
    ) {}

    /**
     * Dynamically pass method calls to the vector memory manager.
     * Automatically injects the agent class as the first parameter.
     *
     * @param  string  $method
     * @param  array  $arguments
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $arguments)
    {
        if (!method_exists($this->manager, $method)) {
            throw new \BadMethodCallException(
                sprintf('Method %s::%s does not exist.', VectorMemoryManager::class, $method)
            );
        }

        // Inject agent class as first parameter
        array_unshift($arguments, $this->agentClass);

        return $this->manager->$method(...$arguments);
    }

    /**
     * Get the agent class this proxy is bound to.
     */
    public function getAgentClass(): string
    {
        return $this->agentClass;
    }

    /**
     * Get the underlying vector memory manager instance.
     */
    public function getManager(): VectorMemoryManager
    {
        return $this->manager;
    }
}