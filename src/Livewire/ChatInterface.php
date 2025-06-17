<?php

namespace Vizra\VizraAdk\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Layout;
use Vizra\VizraAdk\Facades\Agent;
use Vizra\VizraAdk\System\AgentContext;
use Vizra\VizraAdk\Services\StateManager;
use Vizra\VizraAdk\Models\AgentSession;
use Vizra\VizraAdk\Models\TraceSpan;
use Vizra\VizraAdk\Services\Tracer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

#[Layout('agent-adk::layouts.app')]
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
    public array $agentInfo = [];
    public array $traceData = [];

    // Modal properties
    public bool $showLoadSessionModal = false;
    public string $loadSessionId = '';

    public function mount()
    {
        $this->sessionId = 'chat-' . Str::random(8);
        $this->loadRegisteredAgents();
    }

    public function loadRegisteredAgents()
    {
        $this->registeredAgents = Agent::getAllRegisteredAgents();
    }

    public function selectAgent($agentName)
    {
        $this->selectedAgent = $agentName;
        $this->chatHistory = [];
        $this->sessionId = 'chat-' . Str::random(8);
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
                $agent = new $agentClass();
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
            return;
        }

        try {
            // Load session and context data
            $stateManager = app(StateManager::class);
            $context = $stateManager->loadContext($this->selectedAgent, $this->sessionId);                // Extract messages from context conversation history
            $conversationHistory = $context->getConversationHistory();
            if ($conversationHistory && $conversationHistory->isNotEmpty()) {
                $this->chatHistory = $conversationHistory->map(function ($message) {
                    return [
                        'role' => $message['role'] ?? 'unknown',
                        'content' => $message['content'] ?? '',
                        'timestamp' => isset($message['timestamp']) ? $message['timestamp'] : now()->format('H:i:s'),
                        'tool_name' => $message['tool_name'] ?? null
                    ];
                })->toArray();
            }

            // Get context data for details panel
            $this->contextData = [
                'session_id' => $context->getSessionId(),
                'state' => $context->getAllState(),
                'user_input' => $context->getUserInput()
            ];

            // Get session data
            $session = AgentSession::where('session_id', $this->sessionId)
                ->where('agent_name', $this->selectedAgent)
                ->first();

            if ($session) {
                $this->memoryData = [
                    'id' => $session->id,
                    'created_at' => $session->created_at,
                    'updated_at' => $session->updated_at,
                    'user_id' => $session->user_id,
                    'state_data' => $session->state_data
                ];

                // Get memory data
                $memory = $session->memory;
                if ($memory) {
                    $this->memoryData['memory'] = [
                        'id' => $memory->id,
                        'summary' => $memory->memory_summary,
                        'key_learnings' => $memory->key_learnings,
                        'memory_data' => $memory->memory_data,
                        'total_sessions' => $memory->total_sessions,
                        'last_session_at' => $memory->last_session_at
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->contextData = ['error' => $e->getMessage()];
            $this->memoryData = [];
        }
    }

    public function sendMessage()
    {
        if (empty($this->message) || empty($this->selectedAgent)) {
            return;
        }

        $userMessage = trim($this->message);
        $this->message = '';
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
                'content' => 'Error: ' . $e->getMessage(),
                'timestamp' => now()->format('H:i:s'),
            ];
        }

        $this->isLoading = false;
    }

    public function clearChat()
    {
        $this->chatHistory = [];
        $this->sessionId = 'chat-' . Str::random(8);
        $this->loadContextData();
        $this->loadTraceData();
    }

    public function openLoadSessionModal()
    {
        \Log::info('openLoadSessionModal called at ' . now());
        $this->showLoadSessionModal = true;
        $this->loadSessionId = '';
        \Log::info('showLoadSessionModal set to: ' . ($this->showLoadSessionModal ? 'true' : 'false'));
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
            if (is_null($data)) return null;
            if (is_array($data)) return $data;
            if (is_string($data)) {
                // Try to decode if it's a JSON string
                $decoded = json_decode($data, true);
                return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $data;
            }
            // For any other type, convert to string for safe display
            return (string)$data;
        };

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
            'children' => $children->map(function ($child) use ($spansByParent) {
                return $this->buildSpanNode($child, $spansByParent);
            })->toArray()
        ];
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
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
        return is_string($this->message) ? strlen($this->message) : 0;
    }

    public function updatedMessage($value)
    {
        // Ensure message is always a string
        $this->message = is_string($value) ? $value : '';
    }

    public function render()
    {
        return view('agent-adk::livewire.chat-interface');
    }
}
