@push('scripts')
<style>
    /* Fixed height container for the chat interface */
    .chat-interface-container {
        height: calc(100vh - 80px); /* Subtract header height */
    }

    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: #374151 #111827;
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    /* Custom select dropdown styling */
    .custom-select {
        appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }

    .custom-select:hover {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%233b82f6' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    }

    .custom-select:focus {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%233b82f6' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #111827;
        border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #374151;
        border-radius: 3px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #4b5563;
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
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
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
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
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
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.5s ease-in-out;
    }

    .header-button:hover::before {
        left: 100%;
    }

    .status-indicator {
        animation: statusPulse 2s ease-in-out infinite;
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
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* Trace connection lines */
    .compact-trace-card .group:hover .absolute {
        opacity: 0.8;
    }
</style>

<script>
// Auto-scroll chat to bottom function
function scrollChatToBottom() {
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTo({
            top: chatMessages.scrollHeight,
            behavior: 'smooth'
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking Livewire...');
    console.log('Livewire available:', typeof window.Livewire !== 'undefined');
    

    // Initial scroll to bottom
    scrollChatToBottom();

    // Test Livewire connectivity with newer syntax
    if (window.Livewire) {
        console.log('Livewire found');

        // Modern Livewire event listeners
        document.addEventListener('livewire:navigated', () => {
            console.log('Livewire navigated');
            scrollChatToBottom();
        });

        document.addEventListener('livewire:init', () => {
            console.log('Livewire initialized');
        });

        // Listen for when Livewire updates the DOM
        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
            succeed(({ snapshot, effect }) => {
                // Scroll to bottom after DOM updates
                setTimeout(scrollChatToBottom, 50);
            });
        });

        // Listen for custom chat-updated event
        Livewire.on('chat-updated', () => {
            setTimeout(scrollChatToBottom, 100);
        });
        
        // Listen for process-agent-response event to trigger async processing
        Livewire.on('process-agent-response', (event) => {
            // Use setTimeout to make the processing truly asynchronous
            setTimeout(() => {
                // Get the Livewire component and call the method
                const component = Livewire.find(document.querySelector('[wire\\:id]')?.getAttribute('wire:id'));
                if (component) {
                    component.call('processAgentResponse', event.userMessage);
                }
            }, 50); // Small delay to ensure DOM updates
        });

        // Removed refresh-traces-delayed event listener - now using wire:poll
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col h-full w-full chat-interface-container">
        <!-- Minimal Header -->
        <div class="text-center mb-4 flex-shrink-0">
            <h1 class="text-2xl font-bold text-white mb-2">Chat Interface</h1>
            <p class="text-gray-400">Interactive conversations with your AI agents</p>
        </div>

        <!-- Agent Selection & Controls -->
        <div class="flex items-center justify-between mb-4 bg-gray-900/50 rounded-2xl p-4 border border-gray-800/50 shadow-sm flex-shrink-0">
            <div class="flex items-center space-x-4">
                @if(count($registeredAgents) > 0)
                    <div class="flex items-center space-x-3">
                        <label class="text-sm font-medium text-gray-300">Agent:</label>
                        <select id="agent-select"
                                wire:change="changeAgent($event.target.value)"
                                class="custom-select px-4 py-2.5 bg-gray-800 border border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm font-medium text-gray-300 min-w-[220px] transition-all duration-200">
                            <option value="">Choose an agent...</option>
                            @foreach($registeredAgents as $name => $class)
                                <option value="{{ $name }}" @if($selectedAgent === $name) selected @endif>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                        <!-- Debug info -->
                        <span class="text-xs text-gray-500">
                            Session: {{ substr($sessionId, -8) }}
                        </span>
                    </div>
                @else
                    <div class="flex items-center space-x-2 px-4 py-2.5 bg-amber-900/20 border border-amber-700/50 rounded-xl">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <span class="text-sm font-medium text-amber-400">No agents registered</span>
                    </div>
                @endif
            </div>

            <div class="flex items-center space-x-2">
                <button wire:click="openLoadSessionModal"
                        class="inline-flex items-center px-4 py-2.5 bg-gray-800 border border-gray-700 text-sm font-medium rounded-xl text-gray-300 hover:bg-gray-700 hover:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all duration-200">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                    </svg>
                    Load Session
                </button>

                <button wire:click="refreshData"
                        class="inline-flex items-center px-4 py-2.5 bg-gray-800 border border-gray-700 text-sm font-medium rounded-xl text-gray-300 hover:bg-gray-700 hover:border-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all duration-200">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 flex-1 min-h-0 overflow-hidden">
        <!-- Details Panel -->
        <div class="flex flex-col h-full min-h-0 overflow-hidden">
            <div class="flex flex-col h-full min-h-0 overflow-hidden">
                    <!-- Tab Navigation -->
                    <div class="bg-gray-900/50 rounded-xl p-4 mb-4 border border-gray-800/50 flex-shrink-0">
                        <div class="flex items-center mb-3">
                            <div class="flex-shrink-0">
                                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                            </div>
                            <h3 class="ml-3 text-sm font-medium text-gray-300">Agent Details</h3>
                        </div>
                        <nav class="flex space-x-2" aria-label="Tabs">
                            <button wire:click="setActiveTab('agent-info')"
                                    class="flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 {{ $activeTab === 'agent-info' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200 border border-gray-700' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Agent Info
                            </button>
                            <button wire:click="setActiveTab('session-memory')"
                                    class="flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 {{ $activeTab === 'session-memory' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200 border border-gray-700' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                Session/Memory
                            </button>
                            <button wire:click="setActiveTab('traces')"
                                    class="flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 {{ $activeTab === 'traces' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-800 text-gray-400 hover:bg-gray-700 hover:text-gray-200 border border-gray-700' }}">
                                <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Traces
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="bg-gray-900/50 rounded-xl border border-gray-800/50 shadow-sm flex-1 overflow-hidden flex flex-col">
                        <!-- Agent Info Tab -->
                        @if($activeTab === 'agent-info' && $selectedAgent && !empty($agentInfo))
                            <div class="flex-1 overflow-y-auto custom-scrollbar">
                                <div class="space-y-3 p-4">
                                    <!-- Compact Agent Header -->
                                    <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg border border-gray-700/50 shadow-sm">
                                        <div class="px-4 py-3">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 bg-blue-900/50 rounded-lg flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-white">{{ $agentInfo['name'] ?? 'Unknown Agent' }}</h3>
                                                        <p class="text-sm text-blue-400 font-mono">AI Agent</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-4 text-sm">
                                                    @if(isset($agentInfo['tools']) && count($agentInfo['tools']) > 0)
                                                        <div class="flex items-center text-gray-400">
                                                            <svg class="w-4 h-4 mr-1 text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                                                            </svg>
                                                            <span class="font-medium">{{ count($agentInfo['tools']) }}</span> tools
                                                        </div>
                                                    @endif
                                                    @if(isset($agentInfo['subAgents']) && count($agentInfo['subAgents']) > 0)
                                                        <div class="flex items-center text-gray-400">
                                                            <svg class="w-4 h-4 mr-1 text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
                                                            </svg>
                                                            <span class="font-medium">{{ count($agentInfo['subAgents']) }}</span> sub-agents
                                                        </div>
                                                    @endif
                                                    <div class="flex items-center text-gray-400">
                                                        @if(isset($agentInfo['error']))
                                                            <svg class="w-4 h-4 mr-1 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                                            </svg>
                                                            <span class="font-medium">Error</span>
                                                        @else
                                                            <svg class="w-4 h-4 mr-1 text-green-400" fill="currentColor" viewBox="0 0 20 20">
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
                                        <div class="bg-red-900/20 backdrop-blur-sm border border-red-700/50 p-3 rounded-lg">
                                            <div class="flex items-start space-x-2">
                                                <svg class="h-4 w-4 text-red-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                                </svg>
                                                <div>
                                                    <h3 class="text-sm font-medium text-red-400">Agent Error</h3>
                                                    <div class="mt-1 text-sm text-red-300">{{ $agentInfo['error'] }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Compact Instructions -->
                                    <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg border border-gray-700/50 shadow-sm">
                                        <div class="px-3 py-2 border-b border-gray-700/50">
                                            <div class="flex items-center">
                                                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <h4 class="text-sm font-medium text-white">Instructions</h4>
                                            </div>
                                        </div>
                                        <div class="px-3 py-2 h-[calc(100vh-563px)] max-h-[200px]">
                                            <div class="text-xs text-gray-300 whitespace-pre-wrap leading-relaxed max-h-24 overflow-y-auto custom-scrollbar min-h-full">{{ $agentInfo['instructions'] ?? 'No instructions available' }}</div>
                                        </div>
                                    </div>

                                    @if(isset($agentInfo['tools']) && count($agentInfo['tools']) > 0)
                                        <!-- Compact Available Tools -->
                                        <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg border border-gray-700/50 shadow-sm">
                                            <div class="px-3 py-2 border-b border-gray-700/50">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        <h4 class="text-sm font-medium text-white">Available Tools</h4>
                                                    </div>
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-900/50 text-blue-300">
                                                        {{ count($agentInfo['tools']) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="px-3 py-2">
                                                <div class="space-y-2 max-h-32 overflow-y-auto custom-scrollbar">
                                                    @foreach($agentInfo['tools'] as $tool)
                                                        <div class="group bg-gray-700/30 hover:bg-gray-700/50 transition-colors duration-200 px-3 py-2 rounded border border-gray-700/30">
                                                            <div class="flex items-start space-x-2">
                                                                <div class="w-4 h-4 bg-blue-900/50 rounded flex items-center justify-center mt-0.5 flex-shrink-0">
                                                                    <svg class="w-2 h-2 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <h5 class="text-xs font-semibold text-white group-hover:text-blue-400 transition-colors duration-200">{{ $tool['name'] }}</h5>
                                                                    <p class="text-xs text-gray-400 mt-0.5 leading-relaxed">{{ Str::limit($tool['description'], 80) }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @if(isset($agentInfo['subAgents']) && count($agentInfo['subAgents']) > 0)
                                        <!-- Compact Available Sub-Agents -->
                                        <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg border border-gray-700/50 shadow-sm">
                                            <div class="px-3 py-2 border-b border-gray-700/50">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                        </svg>
                                                        <h4 class="text-sm font-medium text-white">Available Sub-Agents</h4>
                                                    </div>
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-indigo-900/50 text-indigo-300">
                                                        {{ count($agentInfo['subAgents']) }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="px-3 py-2">
                                                <div class="space-y-2 max-h-32 overflow-y-auto custom-scrollbar">
                                                    @foreach($agentInfo['subAgents'] as $subAgent)
                                                        <div class="group bg-gray-700/30 hover:bg-gray-700/50 transition-colors duration-200 px-3 py-2 rounded border border-gray-700/30">
                                                            <div class="flex items-start space-x-2">
                                                                <div class="w-4 h-4 bg-indigo-900/50 rounded flex items-center justify-center mt-0.5 flex-shrink-0">
                                                                    <svg class="w-2 h-2 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                    </svg>
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <h5 class="text-xs font-semibold text-white group-hover:text-indigo-400 transition-colors duration-200">{{ $subAgent['name'] }}</h5>
                                                                    <p class="text-xs text-gray-400 mt-0.5 leading-relaxed">{{ $subAgent['description'] }}</p>
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
                            @elseif($activeTab === 'agent-info' && !$selectedAgent)
                            <div class="text-center py-12 px-6">
                                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-800 mb-4">
                                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-white mb-2">No Agent Selected</h3>
                                <p class="text-gray-400 max-w-sm mx-auto">Choose an agent from the dropdown above to view its information, capabilities, and configuration details.</p>
                            </div>
                            @elseif($activeTab === 'agent-info' && $selectedAgent && empty($agentInfo))
                            <div class="text-center py-12 px-6">
                                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-800 mb-4">
                                    <svg class="h-8 w-8 text-gray-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-white mb-2">Loading Agent Info...</h3>
                                <p class="text-gray-400 max-w-sm mx-auto">Loading agent information for {{ $selectedAgent }}</p>
                            </div>
                        @endif

                        <!-- Session/Memory Tab -->
                        @if($activeTab === 'session-memory')
                            @if($selectedAgent)
                                <div class="flex flex-col h-full overflow-hidden p-4">
                                    <!-- Compact Session Header -->
                                    <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg px-4 py-3 border border-purple-900/50 shadow-sm mb-3 flex-shrink-0">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-8 h-8 bg-purple-900/50 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <h3 class="text-lg font-semibold text-white">Session Management</h3>
                                                    <p class="text-sm text-purple-400 font-mono">{{ $sessionId }}</p>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-4 text-sm">
                                                <div class="flex items-center text-gray-400">
                                                    <svg class="w-4 h-4 mr-1 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <span class="font-medium">{{ count($chatHistory) }}</span> msgs
                                                </div>
                                                <div class="flex items-center text-gray-400">
                                                    <svg class="w-4 h-4 mr-1 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span class="font-medium">Active</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    @if(isset($contextData['error']))
                                        <!-- Error Alert -->
                                        <div class="bg-red-900/20 border-l-4 border-red-400 p-4 rounded-r-lg mb-3 flex-shrink-0">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </div>
                                                <div class="ml-3">
                                                    <h3 class="text-sm font-medium text-red-400">Context Error</h3>
                                                    <div class="mt-1 text-sm text-red-300">
                                                        {{ $contextData['error'] }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    <!-- Three Row Grid Layout -->
                                    <div class="flex flex-col gap-3 flex-1 min-h-0">
                                        <!-- Context State Card -->
                                        <div class="flex-1 min-h-0 overflow-hidden">
                                            @if(!empty($contextStateData))
                                                @include('vizra-adk::components.json-viewer', [
                                                    'data' => $contextStateData,
                                                    'title' => 'Context State',
                                                    'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" /></svg>',
                                                    'bgColor' => 'bg-purple-900/20',
                                                    'borderColor' => 'border-purple-800/40',
                                                    'textColor' => 'text-purple-200',
                                                    'titleColor' => 'text-purple-300',
                                                    'iconColor' => 'text-purple-400',
                                                    'expandable' => true,
                                                    'startCollapsed' => false,
                                                    'copyable' => true,
                                                    'collapsible' => true
                                                ])
                                            @else
                                                <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg border border-gray-700/50 shadow-sm flex flex-col h-full overflow-hidden p-4">
                                                    <div class="text-gray-400 text-sm">No context state data available</div>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Session Data Card -->
                                        <div class="flex-1 min-h-0 overflow-hidden">
                                            @if(!empty($sessionData))
                                                @include('vizra-adk::components.json-viewer', [
                                                    'data' => $sessionData,
                                                    'title' => 'Session Data (Short-term)',
                                                    'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>',
                                                    'bgColor' => 'bg-blue-900/20',
                                                    'borderColor' => 'border-blue-800/40',
                                                    'textColor' => 'text-blue-200',
                                                    'titleColor' => 'text-blue-300',
                                                    'iconColor' => 'text-blue-400',
                                                    'expandable' => true,
                                                    'startCollapsed' => false,
                                                    'copyable' => true,
                                                    'collapsible' => true
                                                ])
                                            @else
                                                <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg border border-gray-700/50 shadow-sm flex flex-col h-full overflow-hidden p-4">
                                                    <div class="text-gray-400 text-sm">No session data available</div>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Memory Data Card -->
                                        <div class="flex-1 min-h-0 overflow-hidden">
                                            @if(!empty($longTermMemoryData))
                                                @include('vizra-adk::components.json-viewer', [
                                                    'data' => $longTermMemoryData,
                                                    'title' => 'Memory Data (Long-term)',
                                                    'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" /></svg>',
                                                    'bgColor' => 'bg-green-900/20',
                                                    'borderColor' => 'border-green-800/40',
                                                    'textColor' => 'text-green-200',
                                                    'titleColor' => 'text-green-300',
                                                    'iconColor' => 'text-green-400',
                                                    'expandable' => true,
                                                    'startCollapsed' => false,
                                                    'copyable' => true,
                                                    'collapsible' => true
                                                ])
                                            @else
                                                <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg border border-gray-700/50 shadow-sm flex flex-col h-full overflow-hidden p-4">
                                                    <div class="text-gray-400 text-sm">No long-term memory data available</div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="flex-1 overflow-y-auto custom-scrollbar">
                                    <div class="text-center py-12 px-6">
                                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-800 mb-4">
                                            <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-medium text-white mb-2">No Agent Selected</h3>
                                        <p class="text-gray-400 max-w-sm mx-auto">Choose an agent from the dropdown above to view session data, conversation context, and memory information.</p>
                                    </div>
                                </div>
                            @endif
                        @endif

                        <!-- Traces Tab -->
                        @if($activeTab === 'traces')
                            <div class="flex-1 overflow-y-auto custom-scrollbar" 
                                 @if($hasRunningTraces) wire:poll.1s="loadTraceData" @endif
                                 x-data="{ expandedSpans: {} }"
                                 wire:key="traces-container-{{ $sessionId }}">
                                <div class="p-4">
                                @if(isset($traceData['error']))
                                    <div class="bg-red-900/20 border border-red-700/50 rounded-lg p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-red-400">Trace Error</h3>
                                                <div class="mt-2 text-sm text-red-300">
                                                    <p>{{ $traceData['error'] }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @elseif(empty($traceData))
                                    <div class="text-center text-gray-400 py-12">
                                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-800 mb-4">
                                            <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-medium text-white mb-2">No Execution Traces</h3>
                                        <p class="text-gray-400 max-w-sm mx-auto">Execution traces will appear here after running the agent. Traces show the step-by-step execution flow including tool calls and responses.</p>
                                    </div>
                                @else
                                    <!-- Compact Trace Header -->
                                    <div class="bg-gray-800/50 backdrop-blur-sm rounded-lg border border-gray-700/50 shadow-sm mb-4">
                                        <div class="px-4 py-3">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-8 h-8 bg-blue-900/50 rounded-lg flex items-center justify-center">
                                                        <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                                        </svg>
                                                    </div>
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-white">Execution Traces</h3>
                                                        <p class="text-sm text-blue-400 font-mono">{{ $sessionId }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-4 text-sm">
                                                    <div class="flex items-center text-gray-400">
                                                        <svg class="w-4 h-4 mr-1 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        <span class="font-medium">{{ count($traceData) }}</span> spans
                                                    </div>
                                                    <div class="flex items-center text-gray-400">
                                                        <svg class="w-4 h-4 mr-1 text-green-400" fill="currentColor" viewBox="0 0 20 20">
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
                                                    @include('vizra-adk::partials.trace-span', ['span' => $rootSpan, 'level' => 0])
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
        <div class="flex flex-col h-full min-h-0 overflow-hidden">
            <div class="bg-gray-900/50 rounded-xl shadow-lg border border-gray-800/50 flex flex-col h-full min-h-0 overflow-hidden">
                <!-- Chat Header -->
                <div class="px-6 py-4 border-b border-gray-800/50 bg-gray-800/30 rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center shadow-sm">
                                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-white">
                                    @if($selectedAgent)
                                        Chat with {{ $selectedAgent }}
                                    @else
                                        Chat Interface
                                    @endif
                                </h3>
                                <div class="flex items-center space-x-2 mt-1">
                                    <div class="w-2 h-2 bg-green-400 rounded-full"></div>
                                    <p class="text-sm text-gray-400">Session: {{ $sessionId }}</p>
                                </div>
                            </div>
                        </div>
                        @if($selectedAgent && count($chatHistory) > 0)
                            <button wire:click="clearChat"
                                    class="p-2 text-gray-400 hover:text-red-400 hover:bg-red-900/20 rounded-lg transition-all duration-200">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="flex-1 overflow-y-auto custom-scrollbar bg-gray-900/30" id="chat-messages">
                    <div class="p-6 space-y-6">
                        @if($selectedAgent)
                            @if(count($chatHistory) === 0)
                                <div class="text-center py-12">
                                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-900/30 mb-4">
                                        <svg class="h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-medium text-white mb-2">Start a conversation</h3>
                                    <p class="text-gray-400 mb-1">Ready to chat with {{ $selectedAgent }}</p>
                                    <p class="text-sm text-gray-500">Type a message below to begin your conversation</p>
                                </div>
                            @else
                                @foreach($chatHistory as $index => $chatMessage)
                                    <div class="flex {{ $chatMessage['role'] === 'user' ? 'justify-end' : 'justify-start' }} chat-message">
                                        <div class="max-w-xs lg:max-w-md xl:max-w-lg {{ $chatMessage['role'] === 'user' ? 'order-2' : 'order-1' }}">
                                            @if($chatMessage['role'] === 'user')
                                                <!-- User Message -->
                                                <div class="group">
                                                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-2xl rounded-br-md px-5 py-3 shadow-md message-bubble">
                                                        <p class="text-sm leading-relaxed">{{ $chatMessage['content'] }}</p>
                                                    </div>
                                                    <div class="flex items-center justify-end mt-1 space-x-1 text-xs text-gray-500">
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
                                                        <div class="w-8 h-8 bg-gradient-to-br from-purple-600 to-purple-700 rounded-lg flex items-center justify-center">
                                                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="bg-gray-800 border border-gray-700 rounded-2xl rounded-bl-md px-5 py-3 shadow-sm message-bubble">
                                                            <p class="text-sm text-gray-200 leading-relaxed whitespace-pre-wrap">{{ $chatMessage['content'] }}</p>
                                                        </div>
                                                        <div class="flex items-center mt-1 space-x-1 text-xs text-gray-500">
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
                                                        <div class="w-8 h-8 bg-red-900/50 rounded-lg flex items-center justify-center">
                                                            <svg class="w-4 h-4 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="bg-red-900/20 border border-red-700/50 rounded-2xl rounded-bl-md px-5 py-3">
                                                            <p class="text-sm text-red-300 leading-relaxed">{{ $chatMessage['content'] }}</p>
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
                                                        <div class="w-8 h-8 {{ $isSuccess ? 'bg-green-900/50' : 'bg-orange-900/50' }} rounded-lg flex items-center justify-center">
                                                            @if($isSuccess)
                                                                <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                                </svg>
                                                            @else
                                                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                                                </svg>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="flex-1">
                                                        <div class="{{ $isSuccess ? 'bg-green-900/20 border-green-700/50' : 'bg-orange-900/20 border-orange-700/50' }} border rounded-2xl rounded-bl-md px-5 py-3">
                                                            <p class="text-sm {{ $isSuccess ? 'text-green-300' : 'text-orange-300' }} leading-relaxed">{{ $displayContent }}</p>
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

                                <!-- Streaming Response (only visible while streaming) -->
                                <div wire:loading.flex
                                     wire:target="streamAgentResponse"
                                     class="justify-start chat-message"
                                     x-data="{
                                         streamedText: '',
                                         init() {
                                             // Watch for mutations on the wire:stream element
                                             new MutationObserver(() => this.render()).observe(this.$refs.raw, {
                                                 childList: true,
                                                 characterData: true,
                                                 subtree: true
                                             });
                                             this.render();
                                         },
                                         render() {
                                             let content = this.$refs.raw.innerText.trim();
                                             if (!content) {
                                                 this.streamedText = '';
                                                 return;
                                             }
                                             try {
                                                 const data = JSON.parse(content);
                                                 this.streamedText = data.text || '';
                                             } catch (e) {
                                                 this.streamedText = content;
                                             }
                                         }
                                     }">
                                    <!-- Hidden element that receives wire:stream data -->
                                    <span x-ref="raw" class="hidden" wire:stream="streamed-message" wire:replace></span>

                                    <div class="max-w-xs lg:max-w-md xl:max-w-lg" x-show="streamedText.length > 0">
                                        <div class="group flex space-x-3">
                                            <div class="flex-shrink-0">
                                                <div class="w-8 h-8 bg-gradient-to-br from-purple-600 to-purple-700 rounded-lg flex items-center justify-center">
                                                    <svg class="w-4 h-4 text-white animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="bg-gray-800 border border-gray-700 rounded-2xl rounded-bl-md px-5 py-3 shadow-sm message-bubble">
                                                    <p class="text-sm text-gray-200 leading-relaxed whitespace-pre-wrap" x-text="streamedText"></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if($isLoading && !$enableStreaming)
                                    <!-- Enhanced Typing Indicator with Debug -->
                                    <div class="flex justify-start chat-message" id="typing-indicator">
                                        <div class="group flex space-x-3">
                                            <div class="flex-shrink-0">
                                                <div class="w-8 h-8 bg-gradient-to-br from-purple-600 to-purple-700 rounded-lg flex items-center justify-center shadow-md">
                                                    <div class="flex items-center justify-center">
                                                        <div class="w-4 h-4 relative">
                                                            <div class="absolute inset-0 rounded-full border-2 border-white/30"></div>
                                                            <div class="absolute inset-0 rounded-full border-2 border-white border-t-transparent animate-spin"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex-1">
                                                <div class="bg-gray-800 border border-gray-700 rounded-2xl rounded-bl-md px-5 py-4 shadow-sm message-bubble">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="flex space-x-1">
                                                            <div class="w-2 h-2 bg-blue-400 rounded-full typing-dot"></div>
                                                            <div class="w-2 h-2 bg-blue-400 rounded-full typing-dot"></div>
                                                            <div class="w-2 h-2 bg-blue-400 rounded-full typing-dot"></div>
                                                        </div>
                                                        <span class="text-sm text-gray-300 font-medium">typing...</span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center mt-1 space-x-1 text-xs text-gray-500">
                                                    <svg class="w-3 h-3 animate-pulse text-blue-400" fill="currentColor" viewBox="0 0 20 20">
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
                                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gray-800 mb-4">
                                    <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-medium text-white mb-2">No Agent Selected</h3>
                                <p class="text-gray-400 max-w-sm mx-auto">Choose an agent from the dropdown above to start chatting and exploring AI capabilities.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Message Input -->
                @if($selectedAgent)
                    <div class="px-6 py-4 border-t border-gray-800/50 bg-gray-800/30 rounded-b-xl">
                        <!-- Streaming Toggle -->
                        <div class="mb-3 flex items-center space-x-2">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox"
                                       wire:model.live="enableStreaming"
                                       class="w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500 focus:ring-2 focus:ring-offset-gray-900">
                                <span class="ml-2 text-sm text-gray-300">Enable streaming responses</span>
                            </label>
                            @if($enableStreaming)
                                <span class="text-xs text-blue-400 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    Streaming enabled
                                </span>
                            @endif
                        </div>

                        <form wire:submit.prevent="sendMessage" class="flex items-center space-x-3" x-data="{ messageValue: '' }" @submit="messageValue = ''">
                            <div class="flex-1">
                                <div class="relative">
                                    <input type="text"
                                           id="message-input"
                                           wire:model="message"
                                           x-model="messageValue"
                                           placeholder="Type your message..."
                                           class="w-full px-4 py-3 pr-12 border border-gray-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none bg-gray-800 text-gray-200 placeholder-gray-500 hover:bg-gray-750 transition-colors duration-200 disabled:bg-gray-900 disabled:cursor-not-allowed"
                                           @if($isLoading) disabled @endif>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                        </svg>
                                    </div>
                                </div>
                                <div class="absolute bottom-[-22px] right-0 flex items-center justify-end text-xs text-gray-500">
                                    @if(!empty($message))
                                        <div class="flex items-center space-x-1">
                                            <span>{{ strlen($message) }}</span>
                                            <span>/ 2000 characters</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <button type="submit"
                                    x-bind:disabled="!messageValue.trim() || $wire.isLoading"
                                    class="flex items-center justify-center px-4 py-3 h-[46px] bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-900 disabled:opacity-50 disabled:cursor-not-allowed disabled:from-gray-700 disabled:to-gray-800 transition-all duration-200 shadow-md hover:shadow-lg min-w-[80px]">
                                <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                <span class="font-medium">Send</span>
                            </button>
                        </form>
                    </div>
                @else
                    <!-- No Agent Message Input State -->
                    <div class="px-6 py-4 border-t border-gray-800/50 bg-gray-900/50 rounded-b-xl">
                        <div class="flex items-center justify-center py-2">
                            <div class="text-sm text-gray-500 text-center">
                                <svg class="w-5 h-5 mx-auto mb-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
    </div>

    <!-- Load Session ID Modal -->
    @if($showLoadSessionModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" wire:click="closeLoadSessionModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-900/50 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-white" id="modal-title">
                                    Load Session ID
                                </h3>
                                <div class="mt-4">
                                    <p class="text-sm text-gray-400 mb-4">
                                        Enter a session ID to load an existing conversation with its traces and context.
                                    </p>
                                    <div class="mt-2">
                                        <label for="session-id-input" class="block text-sm font-medium text-gray-300">
                                            Session ID
                                        </label>
                                        <input type="text"
                                               id="session-id-input"
                                               wire:model="loadSessionId"
                                               wire:keydown.enter="loadSessionFromModal"
                                               placeholder="e.g., chat-abc123de"
                                               class="mt-1 block w-full px-3 py-2 border border-gray-700 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm bg-gray-900 text-gray-200">
                                        <p class="mt-1 text-xs text-gray-500">Current session: {{ $sessionId }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button"
                                wire:click="loadSessionFromModal"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm"
                                @if(empty($loadSessionId)) disabled @endif>
                            Load Session
                        </button>
                        <button type="button"
                                wire:click="closeLoadSessionModal"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-700 shadow-sm px-4 py-2 bg-gray-800 text-base font-medium text-gray-300 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
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

            // Debug agent selection changes
            document.addEventListener('DOMContentLoaded', function() {
                const agentSelect = document.getElementById('agent-select');
                if (agentSelect) {
                    agentSelect.addEventListener('change', function(e) {
                        console.log('Agent select changed:', {
                            oldValue: e.target.defaultValue,
                            newValue: e.target.value,
                            timestamp: new Date().toISOString()
                        });
                    });
                }
            });

            // Listen for custom events
            document.addEventListener('agent-changed', function(e) {
                console.log('Agent changed event received:', e.detail);
            });

            document.addEventListener('chat-updated', function(e) {
                console.log('Chat updated event received:', e.detail);
                // Force scroll to bottom after chat update
                setTimeout(() => {
                    const chatMessages = document.getElementById('chat-messages');
                    if (chatMessages) {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                }, 100);
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
