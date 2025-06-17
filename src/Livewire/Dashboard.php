<?php

namespace Vizra\VizraADK\Livewire;

use Livewire\Component;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Services\AgentRegistry;

class Dashboard extends Component
{
    public $packageVersion;
    public $agentCount;
    public $registeredAgents = [];

    public function mount()
    {
        $this->packageVersion = $this->getPackageVersion();
        $this->loadAgentData();
    }

    public function loadAgentData()
    {
        $registry = app(AgentRegistry::class);
        $this->registeredAgents = $registry->getAllRegisteredAgents();
        $this->agentCount = count($this->registeredAgents);
    }

    private function getPackageVersion()
    {
        $composerPath = __DIR__ . '/../../composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            return $composer['version'] ?? 'dev';
        }
        return 'unknown';
    }

    public function render()
    {
        return view('vizra-adk::livewire.dashboard')
            ->layout('vizra-adk::layouts.app');
    }
}
