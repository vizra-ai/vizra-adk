<div class="min-h-screen bg-gray-950">
    <div x-data="{ 
        autoRefresh: @entangle('autoRefresh'),
        refreshInterval: @entangle('refreshInterval'),
        intervalId: null,
        
        startAutoRefresh() {
            if (this.autoRefresh) {
                this.intervalId = setInterval(() => {
                    $wire.loadAnalyticsData();
                }, this.refreshInterval * 1000);
            }
        },
        
        stopAutoRefresh() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        }
    }" 
    x-init="
        $watch('autoRefresh', value => {
            if (value) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
        if (autoRefresh) startAutoRefresh();
    "
    x-on:data-refreshed.window="console.log('Analytics data refreshed')"
    class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    @push('scripts')
    <style>
        .metric-card {
            transition: all 0.3s ease-in-out;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .pulse-dot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
    @endpush

    <!-- Minimal Header -->
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-white mb-2">Analytics Dashboard</h1>
        <p class="text-gray-400">Real-time insights into your AI agent performance and system health</p>
        @if($lastUpdated)
        <p class="text-sm text-gray-500 mt-2">Last updated: {{ $lastUpdated }}</p>
        @endif
    </div>

    <!-- Controls -->
    <div class="flex items-center justify-center space-x-4 mb-8 bg-gray-900/50 rounded-2xl p-4 border border-gray-800/50 shadow-sm max-w-md mx-auto">
        <!-- Auto Refresh Toggle -->
        <div class="flex items-center space-x-2">
            <label class="text-sm text-gray-400">Auto Refresh</label>
            <button 
                wire:click="toggleAutoRefresh"
                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-900 {{ $autoRefresh ? 'bg-blue-600' : 'bg-gray-700' }}"
            >
                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform {{ $autoRefresh ? 'translate-x-6' : 'translate-x-1' }}"></span>
            </button>
        </div>
        
        <!-- Manual Refresh Button -->
        <button 
            wire:click="refreshData" 
            class="px-4 py-2 bg-gray-800 border border-gray-700 text-gray-300 rounded-xl hover:bg-gray-700 hover:border-gray-600 transition-colors flex items-center space-x-2"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span>Refresh</span>
        </button>
    </div>

    @if (session()->has('error'))
        <div class="bg-red-900/20 border border-red-700/50 rounded-lg p-4 mb-6">
            <div class="flex">
                <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <div class="ml-3">
                    <p class="text-sm text-red-300">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- System Health Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="metric-card bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400">System Status</p>
                    <p class="text-2xl font-bold {{ $this->healthStatus === 'healthy' ? 'text-green-400' : ($this->healthStatus === 'warning' ? 'text-yellow-400' : 'text-red-400') }}">
                        {{ ucfirst($this->healthStatus) }}
                    </p>
                </div>
                <div class="w-3 h-3 rounded-full pulse-dot {{ $this->healthStatus === 'healthy' ? 'bg-green-500' : ($this->healthStatus === 'warning' ? 'bg-yellow-500' : 'bg-red-500') }}"></div>
            </div>
        </div>

        <div class="metric-card bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400">Active Sessions</p>
                    <p class="text-2xl font-bold text-blue-400">{{ $agentMetrics['active_sessions'] ?? 0 }}</p>
                </div>
                <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>

        <div class="metric-card bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400">Success Rate</p>
                    <p class="text-2xl font-bold text-green-400">{{ $agentMetrics['success_rate'] ?? 0 }}%</p>
                </div>
                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>

        <div class="metric-card bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-400">Avg Response Time</p>
                    <p class="text-2xl font-bold text-purple-400">{{ $agentMetrics['average_response_time'] ?? 0 }}ms</p>
                </div>
                <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Agent Performance Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Top Performing Agents -->
        <div class="bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                <svg class="w-5 h-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                Top Performing Agents
            </h3>
            <div class="space-y-3">
                @forelse($agentMetrics['top_agents'] ?? [] as $agent)
                <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg border border-gray-700/50">
                    <div>
                        <p class="font-medium text-white">{{ $agent['name'] }}</p>
                        <p class="text-sm text-gray-400">{{ $agent['sessions'] }} sessions</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-400">Avg Duration</p>
                        <p class="font-medium text-gray-300">{{ $agent['avg_duration'] }}s</p>
                    </div>
                </div>
                @empty
                <p class="text-gray-500 text-center py-4">No agent data available</p>
                @endforelse
            </div>
        </div>

        <!-- Message Trends -->
        <div class="bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                </svg>
                Message Trends (7 Days)
            </h3>
            <div class="space-y-2">
                @forelse($conversationAnalytics['message_trends'] ?? [] as $trend)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">{{ $trend['date'] }}</span>
                    <div class="flex items-center">
                        <div class="w-32 bg-gray-700 rounded-full h-2 mr-3">
                            <div class="bg-green-500 h-2 rounded-full" style="width: {{ max(($trend['messages'] / max(array_column($conversationAnalytics['message_trends'] ?? [], 'messages') ?: [1])) * 100, 2) }}%"></div>
                        </div>
                        <span class="text-sm font-medium text-gray-300">{{ $trend['messages'] }}</span>
                    </div>
                </div>
                @empty
                <p class="text-gray-500 text-center py-4">No trend data available</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tool Usage and Vector Memory -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        <!-- Tool Usage Statistics -->
        <div class="bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                <svg class="w-5 h-5 text-orange-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                Most Used Tools
            </h3>
            <div class="space-y-3">
                @forelse($toolUsageStats['most_used_tools'] ?? [] as $tool)
                <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg border border-gray-700/50">
                    <span class="font-medium text-white">{{ $tool['name'] }}</span>
                    <span class="text-sm text-gray-400">{{ $tool['count'] }} uses</span>
                </div>
                @empty
                <p class="text-gray-500 text-center py-4">No tool usage data available</p>
                @endforelse
            </div>
            
            @if(!empty($toolUsageStats['tool_performance']))
            <div class="mt-4 pt-4 border-t border-gray-700">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-gray-400">Total Calls</p>
                        <p class="font-semibold text-gray-300">{{ $toolUsageStats['tool_performance']['total_tool_calls'] ?? 0 }}</p>
                    </div>
                    <div>
                        <p class="text-gray-400">Success Rate</p>
                        <p class="font-semibold text-green-400">
                            {{ $toolUsageStats['tool_performance']['total_tool_calls'] > 0 ? round(($toolUsageStats['tool_performance']['successful_calls'] / $toolUsageStats['tool_performance']['total_tool_calls']) * 100, 1) : 0 }}%
                        </p>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Vector Memory Analytics -->
        <div class="bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 p-6">
            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                <svg class="w-5 h-5 text-purple-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                Vector Memory
            </h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-800/50 rounded-lg p-3 border border-gray-700/50">
                    <p class="text-sm text-gray-400">Documents</p>
                    <p class="text-xl font-bold text-white">{{ $vectorMemoryAnalytics['total_documents'] ?? 0 }}</p>
                </div>
                <div class="bg-gray-800/50 rounded-lg p-3 border border-gray-700/50">
                    <p class="text-sm text-gray-400">Storage</p>
                    <p class="text-xl font-bold text-white">{{ $vectorMemoryAnalytics['storage_usage']['total_storage_mb'] ?? 0 }}MB</p>
                </div>
            </div>
            
            @if(!empty($vectorMemoryAnalytics['search_performance']))
            <div class="mt-4 space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-400">Avg Search Time</span>
                    <span class="font-medium text-gray-300">{{ $vectorMemoryAnalytics['search_performance']['average_search_time'] ?? 0 }}ms</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-400">Cache Hit Rate</span>
                    <span class="font-medium text-green-400">{{ $vectorMemoryAnalytics['search_performance']['cache_hit_rate'] ?? 0 }}%</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-400">Monthly Cost</span>
                    <span class="font-medium text-gray-300">${{ $vectorMemoryAnalytics['embedding_costs']['monthly_cost'] ?? 0 }}</span>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- System Health Details -->
    <div class="bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 p-6">
        <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
            <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            System Health Details
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="space-y-3">
                <h4 class="font-medium text-gray-300">Services</h4>
                @foreach(['database_status' => 'Database', 'cache_status' => 'Cache', 'queue_status' => 'Queue'] as $key => $label)
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-400">{{ $label }}</span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                        {{ ($systemHealth[$key]['status'] ?? 'unknown') === 'healthy' ? 'bg-green-900/50 text-green-400' : 
                           (($systemHealth[$key]['status'] ?? 'unknown') === 'warning' ? 'bg-yellow-900/50 text-yellow-400' : 'bg-red-900/50 text-red-400') }}">
                        {{ ucfirst($systemHealth[$key]['status'] ?? 'unknown') }}
                    </span>
                </div>
                @endforeach
            </div>
            
            <div class="space-y-3">
                <h4 class="font-medium text-gray-300">Memory Usage</h4>
                @if(!empty($systemHealth['memory_usage']))
                <div class="text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Current</span>
                        <span class="text-gray-300">{{ $systemHealth['memory_usage']['current_mb'] }}MB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Peak</span>
                        <span class="text-gray-300">{{ $systemHealth['memory_usage']['peak_mb'] }}MB</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Limit</span>
                        <span class="text-gray-300">{{ $systemHealth['memory_usage']['limit_mb'] }}</span>
                    </div>
                </div>
                @endif
            </div>
            
            <div class="space-y-3">
                <h4 class="font-medium text-gray-300">Response Times</h4>
                @if(!empty($systemHealth['response_times']))
                <div class="text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Database</span>
                        <span class="text-gray-300">{{ $systemHealth['response_times']['database'] }}ms</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Cache</span>
                        <span class="text-gray-300">{{ $systemHealth['response_times']['cache'] }}ms</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">API</span>
                        <span class="text-gray-300">{{ $systemHealth['response_times']['api'] }}ms</span>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>