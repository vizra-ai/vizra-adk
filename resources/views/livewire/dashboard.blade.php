<div>
@push('scripts')
<style>
    .feature-card {
        transition: all 0.2s ease-in-out;
    }
    .feature-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .gradient-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .showcase-card {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        transition: all 0.3s ease;
    }
    .showcase-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.15);
    }
</style>
@endpush

<div class="min-h-screen bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Minimal Header -->
        <div class="text-center mb-12">
            <h1 class="text-3xl font-bold text-gray-900 mb-3">Vizra SDK</h1>
            <p class="text-gray-600 max-w-2xl mx-auto mb-6">
                Build, test, and deploy intelligent AI agents with Laravel's elegant framework
            </p>
            <div class="flex justify-center items-center space-x-6 text-sm text-gray-500">
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

        <!-- Value Proposition Cards -->
        <div class="grid md:grid-cols-3 gap-6 mb-12">
            <!-- Agent Development -->
            <div class="showcase-card rounded-3xl p-8 text-center">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Intelligent Agents</h3>
                <p class="text-gray-600 text-sm leading-relaxed">
                    Create powerful AI agents with memory, tools, and conversation abilities. From simple chatbots to complex reasoning systems.
                </p>
            </div>

            <!-- Testing & Evaluation -->
            <div class="showcase-card rounded-3xl p-8 text-center">
                <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">LLM-as-a-Judge</h3>
                <p class="text-gray-600 text-sm leading-relaxed">
                    Advanced evaluation frameworks with AI-powered quality assessment. Test agent responses at scale with intelligent scoring.
                </p>
            </div>

            <!-- Production Ready -->
            <div class="showcase-card rounded-3xl p-8 text-center">
                <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-gray-900 mb-3">Production Ready</h3>
                <p class="text-gray-600 text-sm leading-relaxed">
                    Built on Laravel's robust foundation with streaming responses, analytics, and enterprise-grade features out of the box.
                </p>
            </div>
        </div>

        <!-- Core Features -->
        <div class="bg-white rounded-3xl shadow-xl border border-gray-200 p-8 mb-12">
            <h2 class="text-2xl font-bold text-gray-900 text-center mb-8">Start Building Now</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Evaluation Runner -->
                <a href="{{ route('agent-adk.eval-runner') }}" class="feature-card bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-blue-200 rounded-2xl p-6 hover:border-blue-300 transition-all group">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-blue-600 transition-colors">Test Quality</h3>
                    <p class="text-gray-600 text-sm mb-4">Run comprehensive evaluations with real-time results and AI-powered quality scoring</p>
                    <div class="flex items-center text-blue-600 text-sm font-medium">
                        Start testing
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>

                <!-- Chat Interface -->
                <a href="{{ route('agent-adk.chat') }}" class="feature-card bg-gradient-to-br from-purple-50 to-indigo-50 border-2 border-purple-200 rounded-2xl p-6 hover:border-purple-300 transition-all group">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-purple-600 transition-colors">Chat & Interact</h3>
                    <p class="text-gray-600 text-sm mb-4">Real-time conversations with streaming responses and rich web interface</p>
                    <div class="flex items-center text-purple-600 text-sm font-medium">
                        Start chatting
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>

                <!-- Analytics -->
                <a href="{{ route('agent-adk.analytics') }}" class="feature-card bg-gradient-to-br from-green-50 to-emerald-50 border-2 border-green-200 rounded-2xl p-6 hover:border-green-300 transition-all group">
                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-green-600 transition-colors">Monitor Performance</h3>
                    <p class="text-gray-600 text-sm mb-4">Track usage, performance metrics, and system health in real-time</p>
                    <div class="flex items-center text-green-600 text-sm font-medium">
                        View insights
                        <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>
            </div>
        </div>

        <!-- Quick Start & Your Agents -->
        <div class="grid lg:grid-cols-2 gap-8">
            <!-- Quick Start Commands -->
            <div class="bg-white rounded-3xl shadow-xl border border-gray-200 p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Quick Start</h2>
                <div class="grid grid-cols-1 gap-4">
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-200">
                        <div class="flex items-center mb-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">Create Agent</span>
                        </div>
                        <code class="text-xs text-gray-600 bg-white px-3 py-2 rounded-lg border block">php artisan agent:make:agent MyAgent</code>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-200">
                        <div class="flex items-center mb-3">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">Create Tool</span>
                        </div>
                        <code class="text-xs text-gray-600 bg-white px-3 py-2 rounded-lg border block">php artisan agent:make:tool MyTool</code>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-200">
                        <div class="flex items-center mb-3">
                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">Create Evaluation</span>
                        </div>
                        <code class="text-xs text-gray-600 bg-white px-3 py-2 rounded-lg border block">php artisan agent:make:eval MyEvaluation</code>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-200">
                        <div class="flex items-center mb-3">
                            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">List Agents</span>
                        </div>
                        <code class="text-xs text-gray-600 bg-white px-3 py-2 rounded-lg border block">php artisan agent:list</code>
                    </div>
                </div>
            </div>

            @if(count($registeredAgents) > 0)
            <!-- Your Agents -->
            <div class="bg-white rounded-3xl shadow-xl border border-gray-200 p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Your Agents</h2>
                <div class="space-y-3">
                    @foreach($registeredAgents as $name => $class)
                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl p-4 border border-gray-200 hover:border-gray-300 transition-all">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center mr-4">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900">{{ $name }}</h4>
                                    <p class="text-xs text-gray-500 font-mono">{{ $class }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                <div class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5"></div>
                                Active
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            <!-- Get Started -->
            <div class="bg-gradient-to-br from-white to-gray-50 rounded-3xl shadow-xl border border-gray-200 p-8 text-center">
                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-lg">
                    <svg class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Ready to Build Your First Agent?</h3>
                <p class="text-gray-600 mb-8">
                    Start your AI agent development journey with Laravel's elegant framework and cutting-edge LLM capabilities.
                </p>
                <div class="bg-gray-100 rounded-2xl p-6 border border-gray-200">
                    <code class="text-sm text-gray-800 font-mono">php artisan agent:make:agent MyFirstAgent</code>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
</div>
