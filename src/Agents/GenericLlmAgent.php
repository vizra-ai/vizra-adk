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
        return $this;
    }

}
