<?php

namespace Vizra\VizraADK\Agents;

// This is a concrete implementation of BaseLlmAgent for ad-hoc definitions.
// It allows setting properties directly.
class GenericLlmAgent extends BaseLlmAgent
{
    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }
}
