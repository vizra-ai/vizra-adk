@push('scripts')
<style>
    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f8fafc;
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    /* Chat message animations */
    .chat-message {
        animation: messageSlideIn 0.3s ease-out;
    }

    @keyframes messageSlideIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Typing indicator dots */
    .typing-dot {
        animation: typingDot 1.4s infinite ease-in-out;
    }

    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }

    @keyframes typingDot {
        0%, 80%, 100% {
            transform: scale(0);
            opacity: 0.5;
        }
        40% {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* Smooth focus transitions */
    .focus-ring {
        transition: all 0.2s ease-in-out;
    }

    .focus-ring:focus {
        transform: scale(1.02);
    }

    /* Message bubble hover effects */
    .message-bubble {
        transition: all 0.2s ease-in-out;
    }

    .message-bubble:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* Gradient text effect */
    .gradient-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Header animation effects */
    .header-card {
        transition: all 0.3s ease-in-out;
    }

    .header-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }

    .agent-icon {
        transition: all 0.3s ease-in-out;
    }

    .agent-icon:hover {
        transform: scale(1.05) rotate(5deg);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
    }

    .header-button {
        position: relative;
        overflow: hidden;
        transition: all 0.2s ease-in-out;
    }

    .header-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        transition: left 0.5s ease-in-out;
    }

    .header-button:hover::before {
        left: 100%;
    }

    .status-indicator {
        animation: statusPulse 2s infinite ease-in-out;
    }

    @keyframes statusPulse {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.7;
            transform: scale(1.1);
        }
    }

    /* Compact Trace Cards */
    .compact-trace-card {
        backdrop-filter: blur(8px);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .compact-trace-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    /* Trace connection lines */
    .compact-trace-card .group:hover .absolute {
        opacity: 0.8;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking Livewire...');
    console.log('Livewire available:', typeof window.Livewire !== 'undefined');

    // Test Livewire connectivity with newer syntax
    if (window.Livewire) {
        console.log('Livewire found');

        // Modern Livewire event listeners
        document.addEventListener('livewire:navigated', () => {
            console.log('Livewire navigated');
        });

        document.addEventListener('livewire:init', () => {
            console.log('Livewire initialized');
        });
    }
});

// Test button functionality
function testModalButton() {
    console.log('Manual test: calling openLoadSessionModal via Livewire');
    if (window.Livewire) {
        // Try to find the component and call the method directly
        const component = window.Livewire.find(document.querySelector('[wire\\:id]').getAttribute('wire:id'));
        if (component) {
            component.call('openLoadSessionModal');
        }
    }
}
</script>
@endpush

<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Enhanced Header -->
        <div class="mb-6">
            <div class="header-card bg-gradient-to-r from-slate-50 via-blue-50 to-indigo-50 rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600/5 via-indigo-600/5 to-purple-600/5 px-6 py-5">
                    <div class="flex items-center justify-between">
                        <!-- Title Section -->
                        <div class="flex items-center space-x-4">
                            <div class="agent-icon flex items-center justify-center w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold bg-gradient-to-r from-slate-800 to-slate-600 bg-clip-text text-transparent">
                                    Laravel Agent ADK
                                </h1>
                                <p class="text-slate-600 text-sm font-medium mt-1">
                                    AI Agent Development & Testing Interface
                                </p>
                            </div>
                        </div>

                        <!-- Controls Section -->
                        <div class="flex items-center space-x-4">
                            <!-- Action Buttons -->
                            <div class="flex space-x-2">

                                <button wire:click="openLoadSessionModal"
                                        class="header-button inline-flex items-center px-4 py-2.5 bg-white/80 backdrop-blur-sm border border-slate-200 shadow-sm text-sm font-medium rounded-xl text-slate-700 hover:bg-white hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-300 transition-all duration-200">
                                    <svg class="w-4 h-4 mr-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                    </svg>
                                    Load Session
                                </button>
                            </div>

                            <!-- Agent Selection -->
                            <div class="flex items-center space-x-3">
                                @if(count($registeredAgents) > 0)
                                    <div class="flex items-center space-x-2">
                                        <label class="text-sm font-medium text-slate-700">Agent:</label>
                                        <select id="agent-select"
                                                wire:change="selectAgent($event.target.value)"
                                                class="px-4 py-2.5 bg-white/90 backdrop-blur-sm border border-slate-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-300 text-sm font-medium text-slate-700 min-w-[220px] transition-all duration-200">
                                            <option value="">Choose an agent...</option>
                                            @foreach($registeredAgents as $name => $class)
                                                <option value="{{ $name }}" {{ $selectedAgent === $name ? 'selected' : '' }}>
                                                    {{ $name }} ({{ class_basename($class) }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                @else
                                    <div class="flex items-center space-x-2 px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-xl">
                                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                        </svg>
                                        <span class="text-sm font-medium text-amber-700">No agents registered</span>
                                    </div>
                                @endif

                                 <button wire:click="refreshData"
                                        class="header-button inline-flex items-center px-4 py-2.5 bg-white/80 backdrop-blur-sm border border-slate-200 shadow-sm text-sm font-medium rounded-xl text-slate-700 hover:bg-white hover:border-slate-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-300 transition-all duration-200">
                                    <svg class="w-4 h-4  text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>

                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 h-[calc(100vh-280px)]">
        <!-- Details Panel -->
        <div class="flex flex-col h-full">
            <div class="flex flex-col h-full overflow-hidden">
                    <!-- Tab Navigation -->
                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl p-4 mb-6 border border-gray-200">
                        <div class="flex items-center mb-3">
                            <div class="flex-shrink-0">
                                <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <h3 class="ml-3 text-sm font-medium text-gray-700">Agent Details</h3>
                        </div>
                        <nav class="flex space-x-2" aria-label="Tabs">
                            <button wire:click="setActiveTab('agent-info')"
                                    class="flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 {{ $activeTab === 'agent-info' ? 'bg-blue-500 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-gray-200' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Agent Info
                            </button>
                            <button wire:click="setActiveTab('session-memory')"
                                    class="flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 {{ $activeTab === 'session-memory' ? 'bg-blue-500 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-gray-200' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Session/Memory
                            </button>
                            <button wire:click="setActiveTab('traces')"
                                    class="flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 {{ $activeTab === 'traces' ? 'bg-blue-500 text-white shadow-md' : 'bg-white text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-gray-200' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Traces
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex-1 overflow-hidden flex flex-col">
                        <!-- Agent Info Tab -->
                        @if($activeTab === 'agent-info' && $selectedAgent && $agentInfo)
                            <div class="flex-1 overflow-y-auto custom-scrollbar">
                                <div class="space-y-3 p-4">
                                    <!-- Compact Agent Header -->
                                    <div class="bg-white/70 backdrop-blur-sm rounded-lg border border-gray-200 shadow-sm">
                                        <div class="px-4 py-3">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-gray-900">{{ $agentInfo['name'] ?? 'Unknown Agent' }}</h3>
                                                        <p class="text-sm text-blue-600 font-mono">AI Agent</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-4 text-sm">
                                                    @if(isset($agentInfo['tools']) && count($agentInfo['tools']) > 0)
                                                        <div class="flex items-center text-gray-600">
                                                            <svg class="w-4 h-4 mr-1 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                                                            </svg>
                                                            <span class="font-medium">{{ count($agentInfo['tools']) }}</span> tools
                                                        </div>
                                                    @endif
                                                    <div class="flex items-center text-gray-600">
                                                        @if(isset($agentInfo['error']))
                                                            <svg class="w-4 h-4 mr-1 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                                            </svg>
                                                            <span class="font-medium">Error</span>
                                                        @else
                                                            <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                            </svg>
                                                            <span class="font-medium">Active</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    @if(isset($agentInfo['error']))
                                        <!-- Error Alert -->
                                        <div class="bg-red-50/80 backdrop-blur-sm border border-red-200 p-3 rounded-lg">
                                            <div class="flex items-start space-x-2">
                                                <svg class="h-4 w-4 text-red-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                                </svg>
                                                <div>
                                                    <h3 class="text-sm font-medium text-red-800">Agent Error</h3>
                                                    <div class="mt-1 text-sm text-red-700">{{ $agentInfo['error'] }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Compact Instructions -->
                                    <div class="bg-white/70 backdrop-blur-sm rounded-lg border border-gray-200 shadow-sm">
                                        <div class="px-3 py-2 border-b border-gray-200">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <h4 class="text-sm font-medium text-gray-900">Instructions</h4>
                                            </div>
                                        </div>
                                        <div class="px-3 py-2">
                                            <div class="text-xs text-gray-700 whitespace-pre-wrap leading-relaxed max-h-24 overflow-y-auto custom-scrollbar">{{ Str::limit($agentInfo['instructions'] ?? 'No instructions available', 200) }}</div>
                                        </div>
                                    </div>

                                    @if(isset($agentInfo['tools']) && count($agentInfo['tools']) > 0)
                                        <!-- Compact Available Tools -->
                                        <div class="bg-white/70 backdrop-blur-sm rounded-lg border border-gray-200 shadow-sm">
                                            <div class="px-3 py-2 border-b border-gray-200">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        <h4 class="text-sm font-medium text-gray-900">Available Tools</h4>
                                                    </div>
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                                        {{ count($agentInfo['tools']) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="px-3 py-2">
                                                <div class="space-y-2 max-h-32 overflow-y-auto custom-scrollbar">
                                                    @foreach($agentInfo['tools'] as $tool)
                                                        <div class="group bg-gray-50/80 hover:bg-gray-100/80 transition-colors duration-200 px-3 py-2 rounded border border-gray-200/50">
                                                            <div class="flex items-start space-x-2">
                                                                <div class="w-4 h-4 bg-blue-100 rounded flex items-center justify-center mt-0.5 flex-shrink-0">
                                                                    <svg class="w-2 h-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <h5 class="text-xs font-semibold text-gray-900 group-hover:text-blue-700 transition-colors duration-200">{{ $tool['name'] }}</h5>
                                                                    <p class="text-xs text-gray-600 mt-0.5 leading-relaxed">{{ Str::limit($tool['description'], 80) }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @elseif($activeTab === 'agent-info')
                            <div class="text-center py-12 px-6">
                                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-4">
                                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No Agent Selected</h3>
                                <p class="text-gray-500 max-w-sm mx-auto">Choose an agent from the dropdown above to view its information, capabilities, and configuration details.</p>
                            </div>
                        @endif

                        <!-- Session/Memory Tab -->
                        @if($activeTab === 'session-memory')
                            @if($selectedAgent)
                                <div class="flex-1 overflow-y-auto custom-scrollbar">
                                    <div class="space-y-3 p-4">
                                    <!-- Compact Session Header -->
                                    <div class="bg-white/70 backdrop-blur-sm rounded-lg px-4 py-3 border border-purple-100 shadow-sm">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-900">Session Management</h3>
                                                    <p class="text-sm text-purple-600 font-mono">{{ $sessionId }}</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-4 text-sm">
                                                <div class="flex items-center text-gray-600">
                                                    <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <span class="font-medium">{{ count($chatHistory) }}</span> msgs
                                                </div>
                                                <div class="flex items-center text-gray-600">
                                                    <svg class="w-4 h-4 mr-1 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span class="font-medium">Active</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    @if(isset($contextData['error']))
                                        <!-- Error Alert -->
                                        <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </div>
                                                <div class="ml-3">
                                                    <h3 class="text-sm font-medium text-red-800">Context Error</h3>
                                                    <div class="mt-1 text-sm text-red-700">
                                                        {{ $contextData['error'] }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Compact Context Information -->
                                    <div class="bg-white/70 backdrop-blur-sm rounded-lg border border-gray-200 shadow-sm">
                                        <div class="px-4 py-3 border-b border-gray-200">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                </svg>
                                                <h4 class="text-sm font-medium text-gray-900">Context Information</h4>
                                            </div>
                                        </div>
                                        <div class="px-4 py-3">
                                            @if(isset($contextData['error']))
                                                <div class="text-red-600 text-sm">{{ $contextData['error'] }}</div>
                                            @else
                                                <div class="grid grid-cols-3 gap-4 text-sm">
                                                    <div class="flex items-center space-x-2">
                                                        <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                                        </svg>
                                                        <div>
                                                            <div class="text-xs text-gray-500">Messages</div>
                                                            <div class="font-semibold text-blue-600">{{ $contextData['messages_count'] ?? 0 }}</div>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center space-x-2">
                                                        <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1721 9z" />
                                                        </svg>
                                                        <div>
                                                            <div class="text-xs text-gray-500">State Keys</div>
                                                            <div class="font-semibold text-green-600">{{ count($contextData['state_keys'] ?? []) }}</div>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center space-x-2">
                                                        <svg class="w-4 h-4 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                        </svg>
                                                        <div>
                                                            <div class="text-xs text-gray-500">Session</div>
                                                            <div class="text-xs font-mono text-purple-600 bg-purple-50 px-1 py-0.5 rounded">
                                                                {{ Str::limit($contextData['session_id'] ?? 'N/A', 8) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                @if(isset($contextData['state_keys']) && count($contextData['state_keys']) > 0)
                                                    <div class="mt-3 pt-3 border-t border-gray-100">
                                                        <div class="text-xs text-gray-500 mb-2">Active State Keys</div>
                                                        <div class="flex flex-wrap gap-1">
                                                            @foreach($contextData['state_keys'] as $key)
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                                    {{ $key }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    @if(isset($memoryData) && count($memoryData) > 0)
                                        <!-- Compact Memory Store -->
                                        <div class="bg-white/70 backdrop-blur-sm rounded-lg border border-gray-200 shadow-sm">
                                            <div class="px-4 py-3 border-b border-gray-200">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
                                                        </svg>
                                                        <h4 class="text-sm font-medium text-gray-900">Memory Store</h4>
                                                    </div>
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                        {{ count($memoryData) }} entries
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="px-4 py-3">
                                                <div class="space-y-2 max-h-32 overflow-y-auto custom-scrollbar">
                                                    @foreach($memoryData as $key => $value)
                                                        <div class="group bg-gray-50/50 hover:bg-gray-100/70 transition-colors duration-200 px-3 py-2 rounded border border-gray-100">
                                                            <div class="flex items-start justify-between">
                                                                <div class="flex-1 min-w-0">
                                                                    <div class="flex items-center space-x-2 mb-1">
                                                                        <h5 class="text-sm font-medium text-gray-900 group-hover:text-purple-700 transition-colors duration-200">{{ $key }}</h5>
                                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ is_array($value) ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700' }}">
                                                                            {{ is_array($value) ? 'Array' : 'String' }}
                                                                        </span>
                                                                    </div>
                                                                    <div class="text-xs text-gray-600 font-mono bg-white/50 rounded px-2 py-1 border max-h-12 overflow-y-auto">
                                                                        {{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : Str::limit($value, 100) }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @else
                                <div class="flex-1 overflow-y-auto custom-scrollbar">
                                    <div class="text-center py-12 px-6">
                                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-4">
                                            <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Agent Selected</h3>
                                        <p class="text-gray-500 max-w-sm mx-auto">Choose an agent from the dropdown above to view session data, conversation context, and memory information.</p>
                                    </div>
                                </div>
                            @endif
                        @endif

                        <!-- Traces Tab -->
                        @if($activeTab === 'traces')
                            <div class="flex-1 overflow-y-auto custom-scrollbar">
                                <div class="p-4">
                                @if(isset($traceData['error']))
                                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-red-800">Trace Error</h3>
                                                <div class="mt-2 text-sm text-red-700">
                                                    <p>{{ $traceData['error'] }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @elseif(empty($traceData))
                                    <div class="text-center text-gray-500 py-12">
                                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-4">
                                            <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-2">No Execution Traces</h3>
                                        <p class="text-gray-500 max-w-sm mx-auto">Execution traces will appear here after running the agent. Traces show the step-by-step execution flow including tool calls and responses.</p>
                                    </div>
                                @else
                                    <!-- Compact Trace Header -->
                                    <div class="bg-white/70 backdrop-blur-sm rounded-lg border border-gray-200 shadow-sm mb-4">
                                        <div class="px-4 py-3">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-gray-900">Execution Traces</h3>
                                                        <p class="text-sm text-blue-600 font-mono">{{ $sessionId }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-4 text-sm">
                                                    <div class="flex items-center text-gray-600">
                                                        <svg class="w-4 h-4 mr-1 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        <span class="font-medium">{{ count($traceData) }}</span> spans
                                                    </div>
                                                    <div class="flex items-center text-gray-600">
                                                        <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        <span class="font-medium">Available</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Compact Trace Timeline -->
                                    <div class="flex-1 overflow-hidden">
                                        <div class="h-full overflow-y-auto custom-scrollbar">
                                            <div class="space-y-1">
                                                @foreach($traceData as $rootSpan)
                                                    @include('agent-adk::partials.trace-span', ['span' => $rootSpan, 'level' => 0])
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

        <!-- Chat Area -->
        <div class="flex flex-col h-[calc(100vh-280px)]">
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 flex flex-col h-full overflow-hidden">
                <!-- Chat Header -->
                <div class="px-6 py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100 rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center shadow-sm">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">
                                    @if($selectedAgent)
                                        Chat with {{ $selectedAgent }}
                                    @else
                                        Chat Interface
                                    @endif
                                </h3>
                                <div class="flex items-center space-x-2 mt-1">
                                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                    <p class="text-sm text-gray-500">Session: {{ $sessionId }}</p>
                                </div>
                            </div>
                        </div>
                        @if($selectedAgent && count($chatHistory) > 0)
                            <button wire:click="clearChat"
                                    class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all duration-200">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="flex-1 overflow-y-auto custom-scrollbar bg-gray-50" id="chat-messages">
                    <div class="p-6 space-y-6">
                        @if($selectedAgent)
                            @if(count($chatHistory) === 0)
                                <div class="text-center py-12">
                                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 mb-4">
                                        <svg class="h-8 w-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">Start a conversation</h3>
                                    <p class="text-gray-500 mb-1">Ready to chat with {{ $selectedAgent }}</p>
                                    <p class="text-sm text-gray-400">Type a message below to begin your conversation</p>
                                </div>
                            @else
                                @foreach($chatHistory as $index => $chatMessage)
                                    <div class="flex {{ $chatMessage['role'] === 'user' ? 'justify-end' : 'justify-start' }} chat-message">
                                        <div class="max-w-xs lg:max-w-md xl:max-w-lg {{ $chatMessage['role'] === 'user' ? 'order-2' : 'order-1' }}">
                                            @if($chatMessage['role'] === 'user')
                                                <!-- User Message -->
                                                <div class="group">
                                                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-2xl rounded-br-md px-5 py-3 shadow-md message-bubble">
                                                        <p class="text-sm leading-relaxed">{{ $chatMessage['content'] }}</p>
                                                    </div>
                                                    <div class="flex items-center justify-end mt-1 space-x-1 text-xs text-gray-400">
                                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        <span>{{ $chatMessage['timestamp'] }}</span>
                                                    </div>
                                                </div>
                                            @elseif($chatMessage['role'] === 'assistant')
                                                <!-- Assistant Message -->
                                                <div class="group flex space-x-3">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center">
                                                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="bg-white border border-gray-200 rounded-2xl rounded-bl-md px-5 py-3 shadow-sm message-bubble">
                                                            <p class="text-sm text-gray-900 leading-relaxed whitespace-pre-wrap">{{ $chatMessage['content'] }}</p>
                                                        </div>
                                                        <div class="flex items-center mt-1 space-x-1 text-xs text-gray-400">
                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                            </svg>
                                                            <span>{{ $selectedAgent }}</span>
                                                            <span>•</span>
                                                            <span>{{ $chatMessage['timestamp'] }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @elseif($chatMessage['role'] === 'error')
                                                <!-- Error Message -->
                                                <div class="group flex space-x-3">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                                                            <svg class="w-4 h-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="bg-red-50 border border-red-200 rounded-2xl rounded-bl-md px-5 py-3">
                                                            <p class="text-sm text-red-900 leading-relaxed">{{ $chatMessage['content'] }}</p>
                                                        </div>
                                                        <div class="flex items-center mt-1 space-x-1 text-xs text-red-400">
                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                            </svg>
                                                            <span>Error</span>
                                                            <span>•</span>
                                                            <span>{{ $chatMessage['timestamp'] }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                @php
                                                    $isSuccess = false;
                                                    $displayContent = $chatMessage['content'];
                                                    try {
                                                        $jsonData = json_decode($chatMessage['content'], true);
                                                        $isSuccess = isset($jsonData['status']) && $jsonData['status'] === 'success';
                                                        if ($isSuccess && isset($jsonData['message'])) {
                                                            $displayContent = $jsonData['message'];
                                                        }
                                                    } catch (\Exception $e) {}
                                                @endphp
                                                <!-- System Message -->
                                                <div class="group flex space-x-3">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-8 h-8 {{ $isSuccess ? 'bg-green-100' : 'bg-orange-100' }} rounded-lg flex items-center justify-center">
                                                            @if($isSuccess)
                                                                <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                                </svg>
                                                            @else
                                                                <svg class="w-4 h-4 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                                </svg>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="{{ $isSuccess ? 'bg-green-50 border-green-200' : 'bg-orange-50 border-orange-200' }} border rounded-2xl rounded-bl-md px-5 py-3">
                                                            <p class="text-sm {{ $isSuccess ? 'text-green-900' : 'text-orange-900' }} leading-relaxed">{{ $displayContent }}</p>
                                                        </div>
                                                        <div class="flex items-center mt-1 space-x-1 text-xs {{ $isSuccess ? 'text-green-400' : 'text-orange-400' }}">
                                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                            </svg>
                                                            <span>{{ $isSuccess ? 'Success' : 'System' }}</span>
                                                            <span>•</span>
                                                            <span>{{ $chatMessage['timestamp'] }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                @if($isLoading)
                                    <!-- Loading Indicator -->
                                    <div class="flex justify-start">
                                        <div class="group flex space-x-3">
                                            <div class="flex-shrink-0">
                                                <div class="w-8 h-8 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="bg-white border border-gray-200 rounded-2xl rounded-bl-md px-5 py-3 shadow-sm">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="flex space-x-1">
                                                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                                                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                                                            <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                                                        </div>
                                                        <span class="text-sm text-gray-600 font-medium">{{ $selectedAgent }} is thinking...</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center mt-1 space-x-1 text-xs text-gray-400">
                                                    <svg class="w-3 h-3 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <span>Processing your request...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        @else
                            <!-- No Agent Selected State -->
                            <div class="text-center py-12">
                                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-100 mb-4">
                                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">No Agent Selected</h3>
                                <p class="text-gray-500 max-w-sm mx-auto">Choose an agent from the dropdown above to start chatting and exploring AI capabilities.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Message Input -->
                @if($selectedAgent)
                    <div class="px-6 py-4 border-t border-gray-200 bg-white rounded-b-xl">
                        <form wire:submit.prevent="sendMessage" class="flex items-center space-x-3">
                            <div class="flex-1">
                                <div class="relative">
                                    <input type="text"
                                           wire:model.live="message"
                                           wire:keydown.enter="sendMessage"
                                           placeholder="Type your message..."
                                           class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none bg-gray-50 hover:bg-white transition-colors duration-200 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                           @if($isLoading) disabled @endif>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="absolute bottom-[-22px] right-0 flex items-center justify-end text-xs text-gray-500">
                                    @if(!empty($message) && is_string($message))
                                        <div class="flex items-center space-x-1">
                                            <span>{{ $this->getMessageCharacterCount() }}</span>
                                            <span>/ 2000 characters</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <button type="submit"
                                    @if($isLoading || !$message || trim($message) === '') disabled @endif
                                    class="flex items-center justify-center px-4 py-3 h-[46px] bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:from-blue-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:from-gray-400 disabled:to-gray-500 transition-all duration-200 shadow-md hover:shadow-lg min-w-[80px]">
                                @if($isLoading)
                                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                @else
                                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    <span class="font-medium">Send</span>
                                @endif
                            </button>
                        </form>
                    </div>
                @else
                    <!-- No Agent Message Input State -->
                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                        <div class="flex items-center justify-center py-2">
                            <div class="text-sm text-gray-500 text-center">
                                <svg class="w-5 h-5 mx-auto mb-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <p>Select an agent to start chatting</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Load Session ID Modal -->
    @if($showLoadSessionModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeLoadSessionModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Load Session ID
                                </h3>
                                <div class="mt-4">
                                    <p class="text-sm text-gray-500 mb-4">
                                        Enter a session ID to load an existing conversation with its traces and context.
                                    </p>
                                    <div class="mt-2">
                                        <label for="session-id-input" class="block text-sm font-medium text-gray-700">
                                            Session ID
                                        </label>
                                        <input type="text"
                                               id="session-id-input"
                                               wire:model="loadSessionId"
                                               wire:keydown.enter="loadSessionFromModal"
                                               placeholder="e.g., chat-abc123de"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        <p class="mt-1 text-xs text-gray-500">Current session: {{ $sessionId }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button"
                                wire:click="loadSessionFromModal"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                                @if(empty($loadSessionId)) disabled @endif>
                            Load Session
                        </button>
                        <button type="button"
                                wire:click="closeLoadSessionModal"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

        <!-- Scripts -->
        <script>
            // Auto-scroll chat messages to bottom
            document.addEventListener('livewire:updated', () => {
                const chatMessages = document.getElementById('chat-messages');
                if (chatMessages) {
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            });

            // Toggle span details in trace visualization
            function toggleSpanDetails(spanId) {
                const details = document.getElementById('span-details-' + spanId);
                const chevron = document.getElementById('chevron-' + spanId);

                if (details) {
                    details.classList.toggle('hidden');

                    // Rotate chevron icon
                    if (chevron) {
                        if (details.classList.contains('hidden')) {
                            chevron.classList.remove('rotate-180');
                        } else {
                            chevron.classList.add('rotate-180');
                        }
                    }
                }
            }
        </script>
</div>
