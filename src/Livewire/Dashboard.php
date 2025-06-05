<?php

namespace AaronLumsden\LaravelAgentADK\Livewire;

use Livewire\Component;
use AaronLumsden\LaravelAgentADK\Facades\Agent;
use AaronLumsden\LaravelAgentADK\Services\AgentRegistry;

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
        return view('agent-adk::livewire.dashboard')
            ->layout('agent-adk::layouts.app');
    }
}
