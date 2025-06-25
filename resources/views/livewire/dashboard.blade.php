<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="space-y-8">
        <!-- Hero Section with Docs-style Header -->
        <div class="text-center mb-12">
            <!-- Robot Icon -->
            <div class="inline-flex items-center justify-center w-20 h-20 mb-6 rounded-2xl bg-gradient-to-br from-purple-500/20 to-blue-500/20 border border-purple-500/30">
                <svg class="w-10 h-10 text-purple-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12,2A2,2 0 0,1 14,4C14,4.74 13.6,5.39 13,5.73V7H14A7,7 0 0,1 21,14H22A1,1 0 0,1 23,15V18A1,1 0 0,1 22,19H21V20A2,2 0 0,1 19,22H5A2,2 0 0,1 3,20V19H2A1,1 0 0,1 1,18V15A1,1 0 0,1 2,14H3A7,7 0 0,1 10,7H11V5.73C10.4,5.39 10,4.74 10,4A2,2 0 0,1 12,2M7.5,13A2.5,2.5 0 0,0 5,15.5A2.5,2.5 0 0,0 7.5,18A2.5,2.5 0 0,0 10,15.5A2.5,2.5 0 0,0 7.5,13M16.5,13A2.5,2.5 0 0,0 14,15.5A2.5,2.5 0 0,0 16.5,18A2.5,2.5 0 0,0 19,15.5A2.5,2.5 0 0,0 16.5,13Z"/>
                </svg>
            </div>

            <h1 class="text-4xl sm:text-5xl font-bold text-white mb-4">
                Vizra ADK
            </h1>
            <p class="text-xl text-gray-400 mb-8 max-w-2xl mx-auto">
                Build, test, and deploy intelligent AI agents with<br> Laravel's elegant framework
            </p>

            <!-- Stats -->
            <div class="flex justify-center items-center space-x-8 text-sm">
                <span class="flex items-center text-gray-400">
                    <div class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                    <span class="text-white font-medium">v{{ $packageVersion }}</span>
                </span>
                <span class="flex items-center text-gray-400">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="text-white font-medium mr-2">{{ $agentCount }}</span> agents registered
                </span>
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

            <!-- Multi-Provider Support -->
            <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-6 border border-gray-800/50 hover:border-gray-700/50 transition-all duration-200">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500/20 to-indigo-600/20 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-3">Multi-LLM Support</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Works with OpenAI, Anthropic Claude, Google Gemini, and more. Switch between providers with a single config change.
                </p>
            </div>

            <!-- Laravel Integration -->
            <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-6 border border-gray-800/50 hover:border-gray-700/50 transition-all duration-200">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500/20 to-emerald-600/20 rounded-xl flex items-center justify-center mb-6">
                    <svg class="w-7 h-7 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-white mb-3">Laravel Native</h3>
                <p class="text-gray-400 text-sm leading-relaxed">
                    Seamlessly integrates with Laravel using familiar patterns. Artisan commands, service providers, and facades - everything works the Laravel way.
                </p>
            </div>
        </div>

        <!-- Core Features -->
        <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-8 border border-gray-800/50">
            <h2 class="text-2xl font-bold text-white text-center mb-8">Start Building Now</h2>
            <div class="grid md:grid-cols-3 gap-6">

                <!-- Chat Interface -->
                <a href="{{ route('vizra.chat') }}" wire:navigate class="group bg-gray-800/50 hover:bg-gray-800/70 rounded-xl p-6 transition-all duration-200 border border-gray-700/50 hover:border-purple-500/50">
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

                 <!-- Evaluation Runner -->
                <a href="{{ route('vizra.eval-runner') }}" wire:navigate class="group bg-gray-800/50 hover:bg-gray-800/70 rounded-xl p-6 transition-all duration-200 border border-gray-700/50 hover:border-blue-500/50">
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

                <!-- Documentation -->
                <a href="https://vizra.ai/docs/adk" target="_blank" class="group bg-gray-800/50 hover:bg-gray-800/70 rounded-xl p-6 transition-all duration-200 border border-gray-700/50 hover:border-green-500/50">
                    <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Documentation</h3>
                    <p class="text-gray-400 text-sm">Learn everything about building agents with comprehensive guides</p>
                    <div class="mt-4 flex items-center text-green-400 text-sm font-medium">
                        Read Docs
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </div>
                </a>

            </div>
        </div>

        <!-- Quick Start Commands -->
        <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-8 border border-gray-800/50" x-data="{ copied: null }">
            <h2 class="text-2xl font-bold text-white text-center mb-8">Quick Start Commands</h2>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Create an Agent</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm relative group">
                        <code class="text-green-400" id="cmd-agent">php artisan vizra:make:agent MyAgent</code>
                        <button @click="navigator.clipboard.writeText($el.previousElementSibling.textContent); copied = 'cmd-agent'; setTimeout(() => copied = null, 2000)"
                                class="absolute top-2 right-2 p-2 bg-gray-700/50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 hover:bg-gray-600/50">
                            <svg x-show="copied !== 'cmd-agent'" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied === 'cmd-agent'" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Create a Tool</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm relative group">
                        <code class="text-green-400" id="cmd-tool">php artisan vizra:make:tool MyTool</code>
                        <button @click="navigator.clipboard.writeText($el.previousElementSibling.textContent); copied = 'cmd-tool'; setTimeout(() => copied = null, 2000)"
                                class="absolute top-2 right-2 p-2 bg-gray-700/50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 hover:bg-gray-600/50">
                            <svg x-show="copied !== 'cmd-tool'" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied === 'cmd-tool'" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Interactive Chat</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm relative group">
                        <code class="text-green-400" id="cmd-chat">php artisan vizra:chat my_agent</code>
                        <button @click="navigator.clipboard.writeText($el.previousElementSibling.textContent); copied = 'cmd-chat'; setTimeout(() => copied = null, 2000)"
                                class="absolute top-2 right-2 p-2 bg-gray-700/50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 hover:bg-gray-600/50">
                            <svg x-show="copied !== 'cmd-chat'" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied === 'cmd-chat'" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Create Evaluation</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm relative group">
                        <code class="text-green-400" id="cmd-eval">php artisan vizra:make:eval MyEvaluation</code>
                        <button @click="navigator.clipboard.writeText($el.previousElementSibling.textContent); copied = 'cmd-eval'; setTimeout(() => copied = null, 2000)"
                                class="absolute top-2 right-2 p-2 bg-gray-700/50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 hover:bg-gray-600/50">
                            <svg x-show="copied !== 'cmd-eval'" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied === 'cmd-eval'" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Discover Agents</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm relative group">
                        <code class="text-green-400" id="cmd-discover">php artisan vizra:discover-agents</code>
                        <button @click="navigator.clipboard.writeText($el.previousElementSibling.textContent); copied = 'cmd-discover'; setTimeout(() => copied = null, 2000)"
                                class="absolute top-2 right-2 p-2 bg-gray-700/50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 hover:bg-gray-600/50">
                            <svg x-show="copied !== 'cmd-discover'" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied === 'cmd-discover'" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white mb-4">Run Evaluation</h3>
                    <div class="bg-gray-800/50 rounded-lg p-4 font-mono text-sm relative group">
                        <code class="text-green-400" id="cmd-run-eval">php artisan vizra:run:eval MyEvaluation</code>
                        <button @click="navigator.clipboard.writeText($el.previousElementSibling.textContent); copied = 'cmd-run-eval'; setTimeout(() => copied = null, 2000)"
                                class="absolute top-2 right-2 p-2 bg-gray-700/50 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 hover:bg-gray-600/50">
                            <svg x-show="copied !== 'cmd-run-eval'" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <svg x-show="copied === 'cmd-run-eval'" class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Your Agents -->
        @if($agentCount > 0)
        <div class="bg-gray-900/50 backdrop-blur-xl rounded-2xl p-8 border border-gray-800/50">
            <h2 class="text-2xl font-bold text-white text-center mb-8">Your Agents</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($registeredAgents as $agentName => $agentClass)
                    <div class="bg-gradient-to-br from-gray-800/50 to-gray-900/50 rounded-xl p-6 border border-gray-700/50">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-gradient-to-br from-purple-500/20 to-blue-500/20 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-lg font-semibold text-white mb-1 truncate">{{ $agentName }}</h4>
                                <p class="text-sm text-gray-400 font-mono truncate">{{ class_basename($agentClass) }}</p>
                                <p class="text-xs text-gray-500 mt-2">Available in Chat Interface</p>
                            </div>
                        </div>
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
