<?php

use Livewire\Livewire;
use Vizra\VizraADK\Livewire\Analytics;
use Vizra\VizraADK\Services\AnalyticsService;

test('refresh data dispatches data refreshed event', function () {
    app()->instance(AnalyticsService::class, new class
    {
        public function getAgentPerformanceMetrics()
        {
            return [];
        }

        public function getConversationAnalytics()
        {
            return [];
        }

        public function getToolUsageStats()
        {
            return [];
        }

        public function getVectorMemoryAnalytics()
        {
            return [];
        }

        public function getSystemHealthMetrics()
        {
            return [];
        }

        public function clearCache() {}
    });

    Livewire::test(Analytics::class)
        ->call('refreshData')
        ->assertDispatched('data-refreshed');
});
