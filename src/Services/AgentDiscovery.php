<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Vizra\VizraADK\Agents\BaseAgent;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Agents\BaseWorkflowAgent;

class AgentDiscovery
{
    protected const CACHE_KEY = 'vizra_adk_discovered_agents';
    protected const CACHE_TTL = 86400; // 24 hours

    /**
     * Discover all agents in the configured namespace
     *
     * @return array<string, string> Class name => Agent name mapping
     */
    public function discover(): array
    {
        // Check if we're in a Laravel environment
        if (function_exists('app') && function_exists('config')) {
            // In development, skip caching
            $env = config('app.env', 'production');
            if (in_array($env, ['local', 'development', 'testing'])) {
                return $this->scanForAgents();
            }

            // In production, use cache
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                return $this->scanForAgents();
            });
        }
        
        // Fallback for non-Laravel environments (like testing)
        return $this->scanForAgents();
    }

    /**
     * Clear the discovery cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Scan the configured namespace directory for agent classes
     *
     * @return array<string, string>
     */
    protected function scanForAgents(): array
    {
        // Get namespace from config or use default
        $namespace = 'App\\Agents';
        if (function_exists('config')) {
            $namespace = config('vizra-adk.namespaces.agents', 'App\\Agents');
        }
        
        $directory = $this->namespaceToPath($namespace);
        
        if (!File::exists($directory)) {
            return [];
        }

        $agents = [];
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->fileToClassName($file, $namespace, $directory);
            
            if (!$className) {
                continue;
            }
            
            // Try to load the class if it doesn't exist
            if (!class_exists($className)) {
                try {
                    require_once $file->getPathname();
                } catch (\Exception $e) {
                    continue;
                }
                
                // Check again after loading
                if (!class_exists($className)) {
                    continue;
                }
            }

            if ($this->isValidAgentClass($className)) {
                $agentName = $this->getAgentName($className);
                if ($agentName) {
                    $agents[$className] = $agentName;
                }
            }
        }

        return $agents;
    }

    /**
     * Convert namespace to directory path
     */
    protected function namespaceToPath(string $namespace): string
    {
        // Convert namespace to path
        $relativePath = str_replace('\\', '/', $namespace);
        
        // Check common locations
        if (str_starts_with($namespace, 'App\\')) {
            if (function_exists('app_path')) {
                return app_path(str_replace('App/', '', $relativePath));
            }
            // Fallback for non-Laravel environments
            $basePath = dirname(dirname(dirname(dirname(dirname(__DIR__)))));
            return $basePath . '/app/' . str_replace('App/', '', $relativePath);
        }

        // Default to base path
        if (function_exists('base_path')) {
            return base_path($relativePath);
        }
        
        // Fallback
        return dirname(dirname(dirname(dirname(dirname(__DIR__))))) . '/' . $relativePath;
    }

    /**
     * Convert file path to fully qualified class name
     */
    protected function fileToClassName(\SplFileInfo $file, string $namespace, string $directory): ?string
    {
        $relativePath = str_replace($directory . '/', '', $file->getPathname());
        $relativePath = str_replace('.php', '', $relativePath);
        $relativePath = str_replace('/', '\\', $relativePath);

        return $namespace . '\\' . $relativePath;
    }

    /**
     * Check if a class is a valid agent class
     */
    protected function isValidAgentClass(string $className): bool
    {
        try {
            $reflection = new \ReflectionClass($className);
            
            // Check if it extends one of the base agent classes
            return $reflection->isSubclassOf(BaseAgent::class) ||
                   $reflection->isSubclassOf(BaseLlmAgent::class) ||
                   $reflection->isSubclassOf(BaseWorkflowAgent::class);
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    /**
     * Get the agent name from a class
     */
    protected function getAgentName(string $className): ?string
    {
        try {
            $reflection = new \ReflectionClass($className);
            
            // Don't instantiate abstract classes
            if ($reflection->isAbstract()) {
                return null;
            }

            // Get the name property value
            $nameProperty = $reflection->getProperty('name');
            $nameProperty->setAccessible(true);
            
            // Create a temporary instance to read the property
            $instance = $reflection->newInstanceWithoutConstructor();
            $name = $nameProperty->getValue($instance);
            
            return !empty($name) ? $name : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}