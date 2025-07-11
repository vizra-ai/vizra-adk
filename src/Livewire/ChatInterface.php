<?php

namespace Vizra\VizraADK\Livewire;

use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Models\AgentSession;
use Vizra\VizraADK\Models\TraceSpan;
use Vizra\VizraADK\Services\StateManager;

#[Layout('vizra-adk::layouts.app')]
class ChatInterface extends Component
{
    public string $selectedAgent = '';

    public array $registeredAgents = [];

    public string $message = '';

    public array $chatHistory = [];

    public string $sessionId = '';

    public bool $isLoading = false;

    public bool $showDetails = true; // Always show details by default

    public string $activeTab = 'agent-info';

    public array $sessionData = [];

    public array $contextData = [];

    public array $memoryData = [];

    public array $longTermMemoryData = [];

    public array $contextStateData = [];

    public array $agentInfo = [];

    public array $traceData = [];

    // Modal properties
    public bool $showLoadSessionModal = false;

    public string $loadSessionId = '';

    // Track if agent was set from URL to prevent re-initialization

    public function mount()
    {
        $this->sessionId = 'chat-'.Str::random(8);
        $this->loadRegisteredAgents();
    }

    public function loadRegisteredAgents()
    {
        $this->registeredAgents = Agent::getAllRegisteredAgents();
    }

    public function selectAgent($agentName)
    {
        // Store previous agent to check if we're switching
        $previousAgent = $this->selectedAgent;

        // Update selected agent
        $this->selectedAgent = $agentName;

        // Only reset chat if switching to a different agent
        if ($previousAgent !== $agentName) {
            $this->chatHistory = [];
            $this->sessionId = 'chat-'.Str::random(8);
        }

        // Always load agent info, context, and traces
        $this->loadAgentInfo();
        $this->loadContextData();
        $this->loadTraceData();
    }

    private function loadAgentInfo()
    {
        if (empty($this->selectedAgent)) {
            $this->agentInfo = [];

            return;
        }

        try {
            // Get agent class information from registry
            $agentClass = $this->registeredAgents[$this->selectedAgent] ?? null;
            if ($agentClass && class_exists($agentClass)) {
                $agent = new $agentClass;
                // Load tools first to ensure they're available
                $agent->loadTools();
                $this->agentInfo = [
                    'name' => $agent->getName(),
                    'class' => $agentClass,
                    'instructions' => $agent->getInstructions(),
                    'tools' => collect($agent->getLoadedTools())->map(function ($tool) {
                        return [
                            'name' => $tool->definition()['name'] ?? 'Unknown',
                            'description' => $tool->definition()['description'] ?? 'No description',
                            'class' => get_class($tool),
                        ];
                    })->toArray(),
                ];
            } else {
                // Agent class not found
                $this->agentInfo = [
                    'name' => $this->selectedAgent,
                    'class' => 'Not found',
                    'error' => 'Agent class not found in registry',
                ];
            }
        } catch (\Exception $e) {
            $this->agentInfo = [
                'name' => $this->selectedAgent,
                'class' => $this->registeredAgents[$this->selectedAgent] ?? 'Unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    public function loadContextData()
    {
        if (empty($this->selectedAgent) || empty($this->sessionId)) {
            $this->contextData = [];
            $this->memoryData = [];
            $this->sessionData = [];
            $this->longTermMemoryData = [];
            $this->contextStateData = [];

            return;
        }

        try {
            // Load session and context data
            $stateManager = app(StateManager::class);
            $context = $stateManager->loadContext($this->selectedAgent, $this->sessionId);

            // Skip loading conversation history - we'll manage it in the component
            // This prevents the chat from being overwritten

            // Get context data for details panel
            $this->contextData = [
                'session_id' => $context->getSessionId(),
                'state' => $context->getAllState(),
                'user_input' => $context->getUserInput(),
                'messages_count' => count($this->chatHistory),
                'state_keys' => array_keys($context->getAllState() ?? []),
            ];

            // Get session data
            $session = AgentSession::where('session_id', $this->sessionId)
                ->where('agent_name', $this->selectedAgent)
                ->with(['messages', 'memory'])
                ->first();

            if ($session) {
                // Build comprehensive session data (short-term)
                $this->sessionData = [
                    'session_id' => $session->session_id,
                    'agent_name' => $session->agent_name,
                    'user_id' => $session->user_id,
                    'messages' => $session->messages->map(function ($msg) {
                        return [
                            'role' => $msg->role,
                            'content' => $msg->content,
                            'tool_calls' => $msg->tool_calls,
                            'tool_results' => $msg->tool_results,
                            'created_at' => $msg->created_at->toIso8601String(),
                        ];
                    })->toArray(),
                    'state_data' => $session->state_data,
                    'context_data' => $session->context_data,
                    'created_at' => $session->created_at->toIso8601String(),
                    'updated_at' => $session->updated_at->toIso8601String(),
                    'message_count' => $session->messages->count(),
                ];

                // Extract state_data for separate Context State card
                $stateData = $session->state_data ?? [];
                // Remove execution_mode from display
                unset($stateData['execution_mode']);
                $this->contextStateData = $stateData;

                // Keep old memoryData for backward compatibility
                $this->memoryData = [
                    'id' => $session->id,
                    'created_at' => $session->created_at,
                    'updated_at' => $session->updated_at,
                    'user_id' => $session->user_id,
                    'state_data' => $session->state_data,
                ];

                // Get memory data (long-term)
                $memory = $session->memory;
                if ($memory) {
                    $this->longTermMemoryData = [
                        'agent_name' => $memory->agent_name,
                        'user_id' => $memory->user_id,
                        'memory_summary' => $memory->memory_summary,
                        'key_learnings' => $memory->key_learnings,
                        'memory_data' => $memory->memory_data,
                        'total_sessions' => $memory->total_sessions,
                        'last_session_at' => $memory->last_session_at ? $memory->last_session_at->toIso8601String() : null,
                        'memory_updated_at' => $memory->memory_updated_at ? $memory->memory_updated_at->toIso8601String() : null,
                        'created_at' => $memory->created_at->toIso8601String(),
                        'updated_at' => $memory->updated_at->toIso8601String(),
                    ];

                    // Keep for backward compatibility
                    $this->memoryData['memory'] = [
                        'id' => $memory->id,
                        'summary' => $memory->memory_summary,
                        'key_learnings' => $memory->key_learnings,
                        'memory_data' => $memory->memory_data,
                        'total_sessions' => $memory->total_sessions,
                        'last_session_at' => $memory->last_session_at,
                    ];
                } else {
                    $this->longTermMemoryData = [
                        'agent_name' => $this->selectedAgent,
                        'message' => 'No long-term memory data available yet',
                    ];
                }
            } else {
                $this->sessionData = [
                    'session_id' => $this->sessionId,
                    'agent_name' => $this->selectedAgent,
                    'message' => 'No session data available',
                ];
                $this->longTermMemoryData = [
                    'agent_name' => $this->selectedAgent,
                    'message' => 'No long-term memory data available',
                ];
                $this->contextStateData = [];
            }
        } catch (\Exception $e) {
            $this->contextData = ['error' => $e->getMessage()];
            $this->memoryData = [];
            $this->sessionData = ['error' => $e->getMessage()];
            $this->longTermMemoryData = ['error' => $e->getMessage()];
            $this->contextStateData = ['error' => $e->getMessage()];
        }
    }

    public function sendMessage()
    {
        if (empty(trim($this->message)) || empty($this->selectedAgent)) {
            return;
        }

        $userMessage = trim($this->message);
        $this->message = ''; // Clear the message
        $this->isLoading = true;

        // Add user message to chat history
        $this->chatHistory[] = [
            'role' => 'user',
            'content' => $userMessage,
            'timestamp' => now()->format('H:i:s'),
        ];

        try {
            // Call the agent
            $response = Agent::run($this->selectedAgent, $userMessage, $this->sessionId);

            // Add agent response to chat history
            $this->chatHistory[] = [
                'role' => 'assistant',
                'content' => $response,
                'timestamp' => now()->format('H:i:s'),
            ];

            // Refresh context data
            $this->loadContextData();
            $this->loadTraceData();

        } catch (\Exception $e) {
            $this->chatHistory[] = [
                'role' => 'error',
                'content' => 'Error: '.$e->getMessage(),
                'timestamp' => now()->format('H:i:s'),
            ];
        }

        $this->isLoading = false;
        
        // Ensure context data is refreshed even after errors
        $this->loadContextData();
        
        // Dispatch event to scroll chat to bottom
        $this->dispatch('chat-updated');
    }

    public function clearChat()
    {
        $this->chatHistory = [];
        $this->sessionId = 'chat-'.Str::random(8);
        $this->loadContextData();
        $this->loadTraceData();
        
        // Dispatch event to reset scroll position
        $this->dispatch('chat-updated');
    }

    public function openLoadSessionModal()
    {
        \Log::info('openLoadSessionModal called at '.now());
        $this->showLoadSessionModal = true;
        $this->loadSessionId = '';
        \Log::info('showLoadSessionModal set to: '.($this->showLoadSessionModal ? 'true' : 'false'));
    }

    public function closeLoadSessionModal()
    {
        $this->showLoadSessionModal = false;
        $this->loadSessionId = '';
    }

    public function loadSessionFromModal()
    {
        if (empty($this->loadSessionId)) {
            $this->closeLoadSessionModal();

            return;
        }

        $this->sessionId = $this->loadSessionId;
        $this->loadContextData();
        $this->loadTraceData();
        $this->closeLoadSessionModal();
        
        // Dispatch event to scroll chat to bottom after loading session
        $this->dispatch('chat-updated');
    }

    public function refreshData()
    {
        $this->loadRegisteredAgents();
        if ($this->selectedAgent) {
            $this->loadAgentInfo();
            $this->loadContextData();
            $this->loadTraceData();
        }
    }

    public function loadTraceData()
    {
        if (empty($this->sessionId)) {
            $this->traceData = [];

            return;
        }

        try {
            // Get trace spans for this session
            $spans = TraceSpan::where('session_id', $this->sessionId)
                ->orderBy('start_time', 'asc')
                ->get();

            if ($spans->isEmpty()) {
                $this->traceData = [];

                return;
            }

            // Build hierarchical structure
            $this->traceData = $this->buildTraceTree($spans);
        } catch (\Exception $e) {
            $this->traceData = ['error' => $e->getMessage()];
        }
    }

    private function buildTraceTree($spans)
    {
        $spansByParent = $spans->groupBy('parent_span_id');
        $rootSpans = $spansByParent->get(null) ?? collect();

        return $rootSpans->map(function ($span) use ($spansByParent) {
            return $this->buildSpanNode($span, $spansByParent);
        })->toArray();
    }

    private function buildSpanNode($span, $spansByParent)
    {
        $children = $spansByParent->get($span->span_id) ?? collect();

        // Calculate duration from decimal timestamps
        $duration = null;
        $startFormatted = null;
        $endFormatted = null;

        if ($span->start_time) {
            $startTime = \Carbon\Carbon::createFromTimestamp($span->start_time);
            $startFormatted = $startTime->format('H:i:s.v');

            if ($span->end_time) {
                $endTime = \Carbon\Carbon::createFromTimestamp($span->end_time);
                $endFormatted = $endTime->format('H:i:s.v');
                $duration = round(($span->end_time - $span->start_time) * 1000, 2); // Convert to milliseconds
            }
        }

        // Helper function to safely handle data that could be strings, arrays, or null
        $processData = function ($data) {
            if (is_null($data)) {
                return null;
            }
            if (is_array($data)) {
                return $data;
            }
            if (is_string($data)) {
                // Try to decode if it's a JSON string
                $decoded = json_decode($data, true);

                return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $data;
            }

            // For any other type, convert to string for safe display
            return (string) $data;
        };

        // Initialize context variables
        $contextState = null;
        $contextChanges = null;
        $extractedJson = null;

        // First, check if we have context_state captured in the span's metadata
        $metadata = $processData($span->metadata);
        if (is_array($metadata) && isset($metadata['context_state'])) {
            // Use the captured context state from the span metadata
            $contextState = $metadata['context_state'];
        } else {
            // Fallback: Get actual context data from AgentSession (for older traces)
            try {
                // Get the actual session data
                $session = AgentSession::where('session_id', $span->session_id)->first();

                if ($session) {
                    // Show the actual state_data from the session
                    $stateData = $session->state_data;
                    if ($stateData) {
                        // Ensure it's an array
                        if (is_string($stateData)) {
                            $stateData = json_decode($stateData, true);
                        }
                        if (is_array($stateData)) {
                            $contextState = $stateData;
                        }
                    }

                    // Show context_data if it exists
                    $contextData = $session->context_data;
                    if ($contextData) {
                        // Ensure it's an array
                        if (is_string($contextData)) {
                            $contextData = json_decode($contextData, true);
                        }
                        if (is_array($contextData)) {
                            $contextChanges = ['session_context' => $contextData];
                        }
                    }
                } else {
                    // Debug: Show that no session was found
                    $contextState = [
                        '_debug' => [
                            'session_id' => $span->session_id,
                            'session_found' => 'no',
                            'error' => 'No session found with this ID',
                        ],
                    ];
                }
            } catch (\Exception $e) {
                // Handle any errors in fallback
            }
        }

        // Get memory data for this session
        try {
            $memories = \Vizra\VizraADK\Models\AgentMemory::where('session_id', $span->session_id)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($memories->isNotEmpty()) {
                $memoryData = [];
                foreach ($memories as $memory) {
                    $memoryData[] = [
                        'type' => $memory->type,
                        'key' => $memory->key,
                        'content' => $memory->content,
                        'scope' => $memory->scope,
                        'created_at' => $memory->created_at->format('Y-m-d H:i:s'),
                    ];
                }

                // Show memories in context changes
                if (! empty($memoryData)) {
                    if (! $contextChanges) {
                        $contextChanges = [];
                    }
                    $contextChanges['memories'] = $memoryData;
                    $contextChanges['memory_count'] = count($memoryData);
                }
            }

            // Also extract any JSON from agent output for debugging purposes
            $outputData = $processData($span->output);
            if (is_array($outputData) && isset($outputData['response']) && is_string($outputData['response'])) {
                // Look for JSON code blocks in the response
                if (preg_match('/```json\s*(\{.*?\})\s*```/s', $outputData['response'], $matches)) {
                    try {
                        $jsonData = json_decode($matches[1], true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                            $extractedJson = $jsonData;
                        }
                    } catch (\Exception $e) {
                        // Ignore JSON parsing errors
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore errors when fetching context data
        }

        return [
            'span_id' => $span->span_id,
            'trace_id' => $span->trace_id,
            'name' => $span->name,
            'type' => $span->type,
            'status' => $span->status,
            'start_time' => $startFormatted,
            'end_time' => $endFormatted,
            'duration_ms' => $duration,
            'input_data' => $processData($span->input),
            'output_data' => $processData($span->output),
            'error_data' => $span->error_message ? ['message' => $span->error_message] : null,
            'metadata' => $processData($span->metadata),
            'context_state' => $contextState,
            'context_changes' => $contextChanges,
            'extracted_json' => $extractedJson,
            'children' => $children->map(function ($child) use ($spansByParent) {
                return $this->buildSpanNode($child, $spansByParent);
            })->toArray(),
        ];
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function updatedSelectedAgent($value)
    {
        if (! empty($value)) {
            $this->selectAgent($value);
        } else {
            // Clear agent info when no agent is selected
            $this->selectedAgent = '';
            $this->agentInfo = [];
            $this->contextData = [];
            $this->memoryData = [];
            $this->contextStateData = [];
            $this->traceData = [];
            $this->chatHistory = [];
        }
    }

    #[On('setSessionId')]
    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
        $this->loadContextData();
        $this->loadTraceData();
    }

    public function getMessageCharacterCount()
    {
        return strlen($this->message);
    }

    public function render()
    {
        return view('vizra-adk::livewire.chat-interface');
    }
}
