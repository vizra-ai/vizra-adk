<div class="max-w-7xl mx-auto" x-data="{}">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Agent Chat Interface</h1>
                <p class="text-gray-600">Select an agent and start chatting</p>
            </div>
            <div class="flex space-x-2">
                <button wire:click="refreshData"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Refresh
                </button>
                <button wire:click="toggleDetails"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $showDetails ? 'Hide Details' : 'Show Details' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Agent Selection -->
    <div class="mb-6">
        <div class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center space-x-4">
                <label for="agent-select" class="text-sm font-medium text-gray-700 whitespace-nowrap">Select Agent:</label>
                @if(count($registeredAgents) > 0)
                    <div class="flex-1">
                        <select id="agent-select"
                                wire:change="selectAgent($event.target.value)"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">Choose an agent...</option>
                            @foreach($registeredAgents as $name => $class)
                                <option value="{{ $name }}" {{ $selectedAgent === $name ? 'selected' : '' }}>
                                    {{ $name }} ({{ class_basename($class) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="flex-1 text-gray-500">
                        <p class="text-sm">No agents registered. Create an agent first.</p>
                    </div>
                @endif
            </div>

            <!-- Custom Session ID Form -->
            @if($selectedAgent)
            <div class="mt-4 flex items-center space-x-2">
                <div class="flex-1 flex space-x-2">
                    <input type="text"
                           id="custom-session-id"
                           placeholder="Enter session ID to load..."
                           class="flex-1 px-3 py-1 text-xs border border-gray-300 rounded-md"
                           x-ref="sessionInput">
                    <button @click="$wire.setSessionId($refs.sessionInput.value)"
                            class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-md">
                        Load Session
                    </button>
                </div>
                <div class="text-xs text-gray-500">
                    Session: <span class="font-mono">{{ $sessionId }}</span>
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 {{ $showDetails ? 'lg:grid-cols-3' : 'lg:grid-cols-1' }} gap-6">
        <!-- Chat Area -->
        <div class="{{ $showDetails ? 'lg:col-span-2' : 'lg:col-span-1' }}">
            <div class="bg-white shadow rounded-lg flex flex-col h-[600px]">
                <!-- Chat Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            @if($selectedAgent)
                                <h3 class="text-lg font-medium text-gray-900">{{ $selectedAgent }}</h3>
                                <p class="text-sm text-gray-500">Session: {{ $sessionId }}</p>
                            @else
                                <h3 class="text-lg font-medium text-gray-500">Select an agent to start chatting</h3>
                            @endif
                        </div>
                        @if($selectedAgent && count($chatHistory) > 0)
                            <button wire:click="clearChat"
                                    class="text-sm text-gray-500 hover:text-gray-700">
                                Clear Chat
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="flex-1 overflow-y-auto p-6 space-y-4" id="chat-messages">
                    @if($selectedAgent)
                        @if(count($chatHistory) === 0)
                            <div class="text-center text-gray-500 py-8">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                                <p>Start a conversation with {{ $selectedAgent }}</p>
                                <p class="text-sm text-gray-400">Type a message below to begin</p>
                            </div>
                        @else
                            @foreach($chatHistory as $index => $message)
                                <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-xs lg:max-w-md xl:max-w-lg">
                                        @if($message['role'] === 'user')
                                            <div class="bg-blue-500 text-white rounded-lg px-4 py-2">
                                                <p class="text-sm">{{ $message['content'] }}</p>
                                                <p class="text-xs text-blue-200 mt-1">{{ $message['timestamp'] }}</p>
                                            </div>
                                        @elseif($message['role'] === 'assistant')
                                            <div class="bg-gray-100 text-gray-900 rounded-lg px-4 py-2">
                                                <p class="text-sm whitespace-pre-wrap">{{ $message['content'] }}</p>
                                                <p class="text-xs text-gray-500 mt-1">{{ $message['timestamp'] }}</p>
                                            </div>
                                        @elseif($message['role'] === 'error')
                                            <div class="bg-red-100 text-red-900 rounded-lg px-4 py-2">
                                                <p class="text-sm">{{ $message['content'] }}</p>
                                                <p class="text-xs text-red-600 mt-1">{{ $message['timestamp'] }}</p>
                                            </div>
                                        @else
                                            @php
                                                $isSuccess = false;
                                                $displayContent = $message['content'];
                                                try {
                                                    $jsonData = json_decode($message['content'], true);
                                                    $isSuccess = isset($jsonData['status']) && $jsonData['status'] === 'success';
                                                    if ($isSuccess && isset($jsonData['message'])) {
                                                        $displayContent = $jsonData['message'];
                                                    }
                                                } catch (\Exception $e) {}
                                            @endphp
                                            <div class="{{ $isSuccess ? 'bg-green-100 text-green-900' : 'bg-red-100 text-red-900' }} rounded-lg px-4 py-2">
                                                <p class="text-sm">{{ $displayContent }}</p>
                                                <p class="text-xs {{ $isSuccess ? 'text-green-600' : 'text-red-600' }} mt-1">{{ $message['timestamp'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            @if($isLoading)
                                <div class="flex justify-start">
                                    <div class="bg-gray-100 text-gray-900 rounded-lg px-4 py-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-gray-600"></div>
                                            <span class="text-sm">{{ $selectedAgent }} is thinking...</span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endif
                </div>

                <!-- Message Input -->
                @if($selectedAgent)
                    <div class="px-6 py-4 border-t border-gray-200">
                        <form wire:submit.prevent="sendMessage" class="flex space-x-2">
                            <div class="flex-1">
                                <input type="text"
                                       wire:model="message"
                                       wire:keydown.enter="sendMessage"
                                       placeholder="Type your message..."
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       @if($isLoading) disabled @endif>
                            </div>
                            <button type="submit"
                                    @if($isLoading || empty($message)) disabled @endif
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                                @if($isLoading)
                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                @else
                                    Send
                                @endif
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <!-- Details Panel -->
        @if($showDetails)
            <div class="lg:col-span-1">
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <!-- Tab Navigation -->
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8 px-6" aria-label="Tabs">
                            <button wire:click="setActiveTab('agent-info')"
                                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'agent-info' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                Agent Info
                            </button>
                            <button wire:click="setActiveTab('session-memory')"
                                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'session-memory' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                Session/Memory
                            </button>
                            <button wire:click="setActiveTab('traces')"
                                    class="py-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'traces' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                                Traces
                            </button>
                        </nav>
                    </div>

                    <!-- Tab Content -->
                    <div class="p-6">
                        <!-- Agent Info Tab -->
                        @if($activeTab === 'agent-info' && $selectedAgent && $agentInfo)
                            <div class="space-y-6">
                                <!-- Basic Agent Info -->
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Agent Info</h3>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="text-sm font-medium text-gray-700">Name</label>
                                            <p class="text-sm text-gray-600">{{ $agentInfo['name'] ?? 'Unknown' }}</p>
                                        </div>
                                        @if(isset($agentInfo['tools']) && count($agentInfo['tools']) > 0)
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Tools</label>
                                                <p class="text-sm text-gray-600">{{ count($agentInfo['tools']) }} available</p>
                                            </div>
                                        @endif
                                        @if(isset($agentInfo['error']))
                                            <div>
                                                <label class="text-sm font-medium text-red-700">Error</label>
                                                <p class="text-sm text-red-600">{{ $agentInfo['error'] }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Agent Details -->
                                <div>
                                    <h4 class="text-base font-medium text-gray-900 mb-3">Agent Details</h4>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="text-sm font-medium text-gray-700">Instructions</label>
                                            <div class="mt-1 text-sm text-gray-600 bg-gray-50 p-3 rounded-md max-h-32 overflow-y-auto">
                                                {{ $agentInfo['instructions'] ?? 'No instructions available' }}
                                            </div>
                                        </div>

                                        @if(isset($agentInfo['tools']) && count($agentInfo['tools']) > 0)
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Available Tools</label>
                                                <div class="mt-1 space-y-2 max-h-48 overflow-y-auto">
                                                    @foreach($agentInfo['tools'] as $tool)
                                                        <div class="bg-gray-50 p-2 rounded-md">
                                                            <div class="font-medium text-sm">{{ $tool['name'] }}</div>
                                                            <div class="text-xs text-gray-600">{{ $tool['description'] }}</div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @elseif($activeTab === 'agent-info')
                            <div class="text-center text-gray-500 py-8">
                                <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm">Select an agent to view its information</p>
                            </div>
                        @endif

                        <!-- Session/Memory Tab -->
                        @if($activeTab === 'session-memory')
                            <div class="space-y-6">
                                @if($selectedAgent)
                                    <!-- Context Data -->
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900 mb-4">Session Data</h3>
                                        <div class="space-y-4">
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Context</label>
                                                <div class="mt-1 text-xs text-gray-600 bg-gray-50 p-3 rounded-md">
                                                    @if(isset($contextData['error']))
                                                        <p class="text-red-600">{{ $contextData['error'] }}</p>
                                                    @else
                                                        <p><strong>Session ID:</strong> {{ $contextData['session_id'] ?? 'N/A' }}</p>
                                                        <p><strong>Messages:</strong> {{ $contextData['messages_count'] ?? 0 }}</p>
                                                        <p><strong>State Keys:</strong> {{ count($contextData['state_keys'] ?? []) }}</p>
                                                    @endif
                                                </div>
                                            </div>

                                            <!-- Memory Data -->
                                            @if(count($memoryData) > 0)
                                                <div>
                                                    <label class="text-sm font-medium text-gray-700">Memory</label>
                                                    <div class="mt-1 text-xs text-gray-600 bg-gray-50 p-3 rounded-md max-h-32 overflow-y-auto">
                                                        @foreach($memoryData as $key => $value)
                                                            <p><strong>{{ $key }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}</p>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center text-gray-500 py-8">
                                        <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p class="text-sm">Select an agent to view session and memory data</p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        <!-- Traces Tab -->
                        @if($activeTab === 'traces')
                            <div class="space-y-6">
                                @if(isset($traceData['error']))
                                    <div class="bg-red-50 border border-red-200 rounded-md p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-red-800">Error loading trace data</h3>
                                                <p class="mt-1 text-sm text-red-700">{{ $traceData['error'] }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @elseif(empty($traceData))
                                    <div class="text-center text-gray-500 py-8">
                                        <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                        <p class="text-sm">No trace data available</p>
                                        <p class="text-xs text-gray-400 mt-1">Traces will appear here after running the agent</p>
                                    </div>
                                @else
                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <h4 class="text-sm font-medium text-gray-900">Execution Traces</h4>
                                            <span class="text-xs text-gray-500">{{ count($traceData) }} trace(s)</span>
                                        </div>

                                        <div class="space-y-2 max-h-96 overflow-y-auto">
                                            @foreach($traceData as $trace)
                                                @include('agent-adk::partials.trace-span', ['span' => $trace, 'level' => 0])
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

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
        if (details) {
            details.classList.toggle('hidden');
        }
    }
</script>
