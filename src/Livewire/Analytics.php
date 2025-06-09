<?php

namespace Vizra\VizraSdk\Livewire;

use Livewire\Component;
use Vizra\VizraSdk\Services\AnalyticsService;
use Illuminate\Support\Facades\Cache;

class Analytics extends Component
{
    public $refreshInterval = 30; // seconds
    public $selectedTimeframe = '24h';
    public $autoRefresh = true;

    // Data properties
    public $agentMetrics = [];
    public $conversationAnalytics = [];
    public $toolUsageStats = [];
    public $vectorMemoryAnalytics = [];
    public $systemHealth = [];
    public $lastUpdated;

    protected $analyticsService;

    public function boot()
    {
        try {
            $this->analyticsService = app(AnalyticsService::class);
        } catch (\Exception $e) {
            // Create a mock service if the real one fails
            $this->analyticsService = new class {
                public function getAgentPerformanceMetrics() { return []; }
                public function getConversationAnalytics() { return []; }
                public function getToolUsageStats() { return []; }
                public function getVectorMemoryAnalytics() { return []; }
                public function getSystemHealthMetrics() { return []; }
                public function clearCache() { }
            };
        }
    }

    public function mount()
    {
        $this->loadAnalyticsData();
    }

    public function loadAnalyticsData()
    {
        try {
            $this->agentMetrics = $this->analyticsService->getAgentPerformanceMetrics();
            $this->conversationAnalytics = $this->analyticsService->getConversationAnalytics();
            $this->toolUsageStats = $this->analyticsService->getToolUsageStats();
            $this->vectorMemoryAnalytics = $this->analyticsService->getVectorMemoryAnalytics();
            $this->systemHealth = $this->analyticsService->getSystemHealthMetrics();
            $this->lastUpdated = now()->format('H:i:s');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to load analytics data: ' . $e->getMessage());
        }
    }

    public function refreshData()
    {
        $this->analyticsService->clearCache();
        $this->loadAnalyticsData();
        $this->dispatchBrowserEvent('data-refreshed');
    }

    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function setTimeframe($timeframe)
    {
        $this->selectedTimeframe = $timeframe;
        $this->refreshData();
    }

    public function getHealthStatusProperty()
    {
        $healthChecks = [
            $this->systemHealth['database_status']['status'] ?? 'unknown',
            $this->systemHealth['cache_status']['status'] ?? 'unknown',
            $this->systemHealth['queue_status']['status'] ?? 'unknown',
        ];

        if (in_array('error', $healthChecks)) {
            return 'error';
        } elseif (in_array('unknown', $healthChecks)) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    public function render()
    {
        return view('agent-adk::livewire.analytics')
            ->layout('agent-adk::layouts.app', [
                'title' => 'Analytics Dashboard'
            ]);
    }
}