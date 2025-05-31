<?php

namespace AaronLumsden\LaravelAgentADK\Agents;

// This is a concrete implementation of BaseLlmAgent for ad-hoc definitions.
// It allows setting properties directly.
class GenericLlmAgent extends BaseLlmAgent
{
    // Tools can be added programmatically if a method is exposed,
    // but for MVP, ad-hoc agents defined via builder won't have tools by default.
    // protected array $dynamicallyRegisteredTools = [];

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function setDescription(string $description): static
    {
        // BaseAgent doesn't have setDescription, this property is protected.
        // For generic agent, we might need to reconsider or accept it's not settable post-hoc easily
        // Or BaseAgent needs a setter, or this class overrides getName/getDescription.
        // For now, assuming description is mostly for registry, not direct use by agent itself.
        // Let's make description public for this generic class, or add setter in BaseAgent.
        // For simplicity in MVP, we'll assume description is set at definition time.
        // $this->description = $description; // If BaseAgent.description was public or had setter

        // To make this work without altering BaseAgent significantly for now,
        // we can acknowledge that description for GenericLlmAgent is primarily what's stored
        // in the AgentRegistry's definition array, and not directly set on the instance post-construction
        // in a way that BaseAgent's getDescription() would pick up unless overridden here.
        // This GenericLlmAgent's description property (if we added one) would be separate.
        // Or, we modify BaseAgent to have a public/protected $description and a setter.
        // For now, we rely on the name for identification, and description is metadata in registry.
        return $this;
    }

    // registerTools will be empty by default for GenericLlmAgent
    // protected function registerTools(): array
    // {
    //     return $this->dynamicallyRegisteredTools;
    // }
    // public function addTool(string $toolClass): static
    // {
    //     $this->dynamicallyRegisteredTools[] = $toolClass;
    //     $this->loadedTools = []; // Reset loaded tools to force reload
    //     $this->loadTools();
    //     return $this;
    // }
}
