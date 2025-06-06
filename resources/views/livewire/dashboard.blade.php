<div>
@push('scripts')
<style>
    /* Enhanced animations and transitions */
    .dashboard-card {
        transition: all 0.3s ease-in-out;
    }

    .dashboard-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    }

    .hero-icon {
        transition: all 0.5s ease-in-out;
        animation: heroIconFloat 3s ease-in-out infinite;
    }

    @keyframes heroIconFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-5px) rotate(5deg); }
    }

    .stat-card {
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease-in-out;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.6s ease-in-out;
    }

    .stat-card:hover::before {
        left: 100%;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px -8px rgba(0, 0, 0, 0.15);
    }

    .quick-start-item {
        position: relative;
        transition: all 0.3s ease-in-out;
        overflow: hidden;
    }

    .quick-start-item::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background: linear-gradient(135deg, transparent 0%, rgba(59, 130, 246, 0.05) 100%);
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }

    .quick-start-item:hover::after {
        opacity: 1;
    }

    .quick-start-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.12);
    }

    .status-pulse {
        animation: statusPulse 2s ease-in-out infinite;
    }

    @keyframes statusPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.05); }
    }

    .gradient-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Quick Start and Agent Card Enhancements */
    .quickstart-card, .agent-card {
        position: relative;
        overflow: hidden;
    }

    .quickstart-card::before, .agent-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        transition: left 0.6s ease-in-out;
    }

    .quickstart-card:hover::before, .agent-card:hover::before {
        left: 100%;
    }
</style>
@endpush

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Enhanced Hero Section -->
    <div class="dashboard-card bg-gradient-to-br from-white via-green-50/30 to-emerald-50/40 overflow-hidden shadow-xl rounded-2xl mb-8 border border-slate-200/60">
        <div class="bg-gradient-to-r from-green-600/5 via-emerald-600/5 to-teal-600/5 px-8 py-8">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 mb-6 shadow-lg shadow-blue-500/25 hero-icon">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h1 class="text-4xl font-bold gradient-text mb-3">Welcome to Laravel Agent ADK</h1>
                <p class="text-xl text-gray-600 mb-6 max-w-2xl mx-auto leading-relaxed">Build powerful AI agents with Laravel's elegant framework and cutting-edge LLM technology</p>
                <div class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200/50 shadow-sm status-pulse">
                    <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                    Package Active & Ready
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Package Version -->
        <div class="stat-card bg-gradient-to-br from-white to-blue-50/30 overflow-hidden shadow-lg rounded-xl border border-blue-100/50">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/25">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Package Version</dt>
                            <dd class="text-2xl font-bold text-gray-900">{{ $packageVersion }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registered Agents -->
        <div class="stat-card bg-gradient-to-br from-white to-green-50/30 overflow-hidden shadow-lg rounded-xl border border-green-100/50">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/25">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Registered Agents</dt>
                            <dd class="text-2xl font-bold text-gray-900">{{ $agentCount }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status -->
        <div class="stat-card bg-gradient-to-br from-white to-purple-50/30 overflow-hidden shadow-lg rounded-xl border border-purple-100/50">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/25">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Status</dt>
                            <dd class="text-2xl font-bold text-gray-900">Ready</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Start Section -->
    <div class=" mb-8">
        <div class="flex items-center mb-6">

            <h3 class="text-2xl font-bold gradient-text">Quick Start</h3>
        </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Create Agent -->
                <div class="quickstart-card bg-gradient-to-br from-white to-blue-50/50 border border-blue-100/50 rounded-xl p-6 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/25 mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900">Create an Agent</h4>
                    </div>
                    <p class="text-gray-600 mb-4 leading-relaxed">Generate a new AI agent class with built-in capabilities and LLM integration.</p>
                    <div class="bg-gradient-to-r from-gray-50 to-blue-50/50 border border-gray-200/50 rounded-lg p-3 font-mono text-sm text-gray-800 shadow-inner">
                        php artisan agent:make:agent MyAgent
                    </div>
                </div>

                <!-- Create Tool -->
                <div class="quickstart-card bg-gradient-to-br from-white to-green-50/50 border border-green-100/50 rounded-xl p-6 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/25 mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900">Create a Tool</h4>
                    </div>
                    <p class="text-gray-600 mb-4 leading-relaxed">Build powerful tools to extend your agent's capabilities and integrations.</p>
                    <div class="bg-gradient-to-r from-gray-50 to-green-50/50 border border-gray-200/50 rounded-lg p-3 font-mono text-sm text-gray-800 shadow-inner">
                        php artisan agent:make:tool MyTool
                    </div>
                </div>

                <!-- Chat Interface -->
                <a href="{{ route('agent-adk.chat') }}" class="quickstart-card bg-gradient-to-br from-white to-purple-50/50 border border-purple-100/50 rounded-xl p-6 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 block group">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-purple-500/25 mr-4 group-hover:scale-110 transition-transform duration-200">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900 group-hover:text-purple-700 transition-colors">Chat Interface</h4>
                    </div>
                    <p class="text-gray-600 mb-4 leading-relaxed">Interactive web-based chat with your agents in real-time conversation.</p>
                    <div class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold bg-gradient-to-r from-purple-100 to-indigo-100 text-purple-800 border border-purple-200/50 shadow-sm group-hover:shadow-md transition-shadow">
                        Open Chat Interface
                        <svg class="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>

                <!-- Run Evaluation -->
                <div class="quickstart-card bg-gradient-to-br from-white to-orange-50/50 border border-orange-100/50 rounded-xl p-6 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-red-500 rounded-xl flex items-center justify-center shadow-lg shadow-orange-500/25 mr-4">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-900">Run Evaluation</h4>
                    </div>
                    <p class="text-gray-600 mb-4 leading-relaxed">Test agent quality and performance with comprehensive evaluation frameworks.</p>
                    <div class="bg-gradient-to-r from-gray-50 to-orange-50/50 border border-gray-200/50 rounded-lg p-3 font-mono text-sm text-gray-800 shadow-inner">
                        php artisan agent:eval eval_name
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(count($registeredAgents) > 0)
    <!-- Enhanced Registered Agents Section -->
    <div class="dashboard-card  overflow-hidden ">
        <div class=" px-8 py-8">
            <div class="flex items-center mb-6">

                <h3 class="text-2xl font-bold gradient-text">Registered Agents</h3>
            </div>
            <div class="space-y-4">
                @foreach($registeredAgents as $name => $class)
                <div class="agent-card bg-gradient-to-br from-white to-green-50/50 border border-green-100/50 rounded-xl p-6 shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-lg shadow-green-500/25 mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900">{{ $name }}</h4>
                                <p class="text-sm text-gray-600 font-mono">{{ $class }}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 border border-green-200/50 shadow-sm">
                                <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                                Active
                            </span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @else
    <!-- Enhanced No Agents Registered -->
    <div class="dashboard-card bg-gradient-to-br from-white via-gray-50/30 to-slate-50/40 overflow-hidden shadow-xl rounded-2xl border border-slate-200/60">
        <div class="bg-gradient-to-r from-gray-600/5 via-slate-600/5 to-zinc-600/5 px-8 py-12 text-center">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-gradient-to-br from-gray-500 to-slate-600 mb-6 shadow-lg shadow-gray-500/25">
                <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
            </div>
            <h3 class="text-2xl font-bold gradient-text mb-3">No Agents Registered</h3>
            <p class="text-gray-600 mb-6 max-w-md mx-auto leading-relaxed">Get started by creating your first agent using the command below. Your journey into AI agent development begins here!</p>
            <div class="bg-gradient-to-r from-gray-50 to-slate-50/50 border border-gray-200/50 rounded-lg p-4 font-mono text-sm text-gray-800 shadow-inner max-w-sm mx-auto">
                php artisan agent:make:agent MyFirstAgent
            </div>
        </div>
    </div>
    @endif
</div>
</div>
