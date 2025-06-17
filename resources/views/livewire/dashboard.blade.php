<div>
    <div class="space-y-8">
        <!-- Hero Section -->
        <div class="relative overflow-hidden bg-gradient-to-r from-blue-600/20 via-purple-600/20 to-cyan-600/20 rounded-2xl p-8 border border-gray-800/50">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-500/10 via-purple-500/10 to-cyan-500/10"></div>
            <div class="relative">
                <div class="text-center">
                    <h1 class="text-3xl font-bold text-white mb-3">Vizra ADK Dashboard</h1>
                    <p class="text-gray-300 text-lg max-w-2xl mx-auto mb-6">
                        Build, test, and deploy intelligent AI agents with Laravel's elegant framework
                    </p>
                    <div class="flex justify-center items-center space-x-6 text-sm text-gray-400">
                        <span class="flex items-center">
                            <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                            v{{ $packageVersion }}
                        </span>
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            {{ $agentCount }} agents
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Value Proposition Cards -->
        <div class="grid md:grid-cols-3 gap-6">
            <!-- Agent Development -->
            <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-6 border border-gray-800/50 hover:border-gray-700/50 transition-all duration-200">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500/20 to-indigo-600/20 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-3">Intelligent Agents</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Create powerful AI agents with memory, tools, and conversation abilities. From simple chatbots to complex reasoning systems.
                </p>
            </div>

            <!-- Testing & Evaluation -->
            <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-6 border border-gray-800/50 hover:border-gray-700/50 transition-all duration-200">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500/20 to-indigo-600/20 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-3">LLM-as-a-Judge</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Advanced evaluation frameworks with AI-powered quality assessment. Test agent responses at scale with intelligent scoring.
                </p>
            </div>

            <!-- Production Ready -->
            <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-6 border border-gray-800/50 hover:border-gray-700/50 transition-all duration-200">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500/20 to-emerald-600/20 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-3">Production Ready</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Built on Laravel's robust foundation with streaming responses, analytics, and enterprise-grade features out of the box.
                </p>
            </div>
        </div>

        <!-- Core Features -->
        <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-8 border border-gray-800/50">
            <h2 class="text-2xl font-bold text-white text-center mb-8">Start Building Now</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Evaluation Runner -->
                <a href="{{ route('vizra.eval-runner') }}" class="group bg-gray-800/50 hover:bg-gray-800/70 rounded-xl p-6 transition-all duration-200 border border-gray-700/50 hover:border-blue-500/50">
                    <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Evaluation Runner</h3>
                    <p class="text-gray-400 text-sm">Test your agents with CSV datasets and automated quality scoring</p>
                    <div class="mt-4 flex items-center text-blue-400 text-sm font-medium">
                        Run Evaluations
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>

                <!-- Chat Interface -->
                <a href="{{ route('vizra.chat') }}" class="group bg-gray-800/50 hover:bg-gray-800/70 rounded-xl p-6 transition-all duration-200 border border-gray-700/50 hover:border-purple-500/50">
                    <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Chat Interface</h3>
                    <p class="text-gray-400 text-sm">Interactive conversations with your agents in real-time</p>
                    <div class="mt-4 flex items-center text-purple-400 text-sm font-medium">
                        Start Chatting
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>

            </div>
        </div>

        <!-- Quick Start Commands -->
        <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-8 border border-gray-800/50">
            <h2 class="text-2xl font-bold text-white text-center mb-8">Quick Start Commands</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Create an Agent</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm">
                        <code class="text-green-400">php artisan vizra:make:agent MyAgent</code>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Create a Tool</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm">
                        <code class="text-green-400">php artisan vizra:make:tool MyTool</code>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Interactive Chat</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm">
                        <code class="text-green-400">php artisan vizra:chat my_agent</code>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Create Evaluation</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm">
                        <code class="text-green-400">php artisan vizra:make:eval MyEvaluation</code>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">List All Agents</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm">
                        <code class="text-green-400">php artisan vizra:list</code>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Run Evaluation</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm">
                        <code class="text-green-400">php artisan vizra:run:eval MyEvaluation</code>
                    </div>
                </div>
            </div>
        </div>

        <!-- Your Agents -->
        @if($agentCount > 0)
        <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-8 border border-gray-800/50">
            <h2 class="text-2xl font-bold text-white text-center mb-8">Your Agents</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($registeredAgents as $agentName => $agentClass)
                    <div class="bg-gray-800/50 rounded-lg p-4">
                        <h4 class="font-semibold text-white mb-1">{{ $agentName }}</h4>
                        <p class="text-gray-400 text-sm">{{ class_basename($agentClass) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Recent Activity Stats -->
        @if(isset($recent_activity))
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-6 border border-gray-800/50">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-green-400 bg-green-500/20 px-2 py-1 rounded-full">Active</span>
                </div>
                <h3 class="text-2xl font-bold text-white mb-1">{{ $recent_activity['active_sessions'] ?? 0 }}</h3>
                <p class="text-gray-400 text-sm">Active Sessions</p>
            </div>

            <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-6 border border-gray-800/50">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-white mb-1">{{ $recent_activity['total_messages'] ?? 0 }}</h3>
                <p class="text-gray-400 text-sm">Total Messages</p>
            </div>

            <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-6 border border-gray-800/50">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-orange-500/20 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-white mb-1">{{ $recent_activity['evaluations_run'] ?? 0 }}</h3>
                <p class="text-gray-400 text-sm">Evaluations Run</p>
            </div>
        </div>
        @endif
    </div>
</div>