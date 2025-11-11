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

    public bool $hasRunningTraces = false;

    public bool $enableStreaming = false;

    // Modal properties
    public bool $showLoadSessionModal = false;

    public string $loadSessionId = '';

    // Track if agent was set from URL to prevent re-initialization

    public function mount()
    {
        $this->sessionId = $this->generateUniqueSessionId();
        $this->loadRegisteredAgents();
    }

    public function loadRegisteredAgents()
    {
        $allAgents = Agent::getAllRegisteredAgents();

        // Filter agents based on $showInChatUi property
        $this->registeredAgents = array_filter($allAgents, function ($agentClass) {
            if (class_exists($agentClass)) {
                try {
                    $agent = new $agentClass;

                    return $agent->getShowInChatUi();
                } catch (\Exception $e) {
                    // If we can't instantiate the agent, include it by default
                    return true;
                }
            }

            return true;
        });
    }

    public function selectAgent($agentName)
    {
        // Store previous agent to check if we're switching
        $previousAgent = $this->selectedAgent;
        $previousSessionId = $this->sessionId;

        logger()->info('selectAgent called', [
            'previous_agent' => $previousAgent ?: 'none',
            'new_agent' => $agentName,
            'previous_session_id' => $previousSessionId,
            'chat_history_count' => count($this->chatHistory),
        ]);

        // Update selected agent
        $this->selectedAgent = $agentName;

        // Always reset session when switching agents (including from empty to an agent)
        // This ensures clean state and prevents context bleed between agents
        if ($previousAgent !== $agentName) {
            logger()->info('Clearing data for agent switch', [
                'from' => $previousAgent ?: 'none',
                'to' => $agentName,
            ]);

            // FORCE clear all chat and context data first
            $this->chatHistory = [];
            $this->contextData = [];
            $this->memoryData = [];
            $this->sessionData = [];
            $this->longTermMemoryData = [];
            $this->contextStateData = [];
            $this->traceData = [];
            $this->hasRunningTraces = false;

            // Generate new session ID with extra uniqueness
            $newSessionId = $this->generateUniqueSessionId();
            $this->sessionId = $newSessionId;

            // Log the agent switch for debugging
            logger()->info('Agent switched successfully', [
                'previous_agent' => $previousAgent ?: 'none',
                'new_agent' => $agentName,
                'old_session_id' => $previousSessionId,
                'new_session_id' => $newSessionId,
                'chat_history_cleared' => true,
            ]);
        } else {
            logger()->info('Same agent selected, no session reset needed', [
                'agent' => $agentName,
                'session_id' => $this->sessionId,
            ]);
        }

        // Always load agent info, context, and traces with the new session
        $this->loadAgentInfo();
        $this->loadContextData();
        $this->loadTraceData();

        logger()->info('selectAgent completed', [
            'agent' => $agentName,
            'session_id' => $this->sessionId,
            'chat_history_count_after' => count($this->chatHistory),
        ]);
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
                // Tools and sub-agents are loaded automatically in the constructor
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
                    'subAgents' => collect($agent->getLoadedSubAgents())->map(function ($subAgent, $name) {
                        $instructions = $subAgent->getInstructions() ?? 'No instructions available';
                        // Get just the first line
                        $firstLine = explode("\n", $instructions)[0];

                        return [
                            'name' => $name,
                            'description' => $firstLine,
                            'class' => get_class($subAgent),
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

        // Call the appropriate method directly via $wire
        if ($this->enableStreaming) {
            $this->js('$wire.streamAgentResponse()');
        } else {
            $this->dispatch('process-agent-response', userMessage: $userMessage);
        }
    }

    public function processAgentResponse($userMessage)
    {
        if (empty($this->selectedAgent) || ! $this->isLoading) {
            return;
        }

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

            // Mark that we have running traces to enable polling
            $this->hasRunningTraces = true;

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

    public function streamAgentResponse(): void
    {
        @set_time_limit(300);

        if (empty($this->selectedAgent) || empty($this->chatHistory)) {
            return;
        }

        // Get the last user message from chat history
        $userMessage = '';
        foreach (array_reverse($this->chatHistory) as $msg) {
            if ($msg['role'] === 'user') {
                $userMessage = $msg['content'];
                break;
            }
        }

        if (empty($userMessage)) {
            return;
        }

        // Get agent class
        $agentClass = $this->registeredAgents[$this->selectedAgent] ?? null;

        if (! $agentClass || ! class_exists($agentClass)) {
            $this->chatHistory[] = [
                'role' => 'error',
                'content' => 'Agent not found',
                'timestamp' => now()->format('H:i:s'),
            ];
            $this->isLoading = false;

            return;
        }

        try {
            // Create agent and stream response
            $stream = $agentClass::run($userMessage)
                ->withSession($this->sessionId)
                ->streaming()
                ->go();

            // Accumulate text for UI display only
            // BaseLlmAgent handles ALL persistence (messages, thinking, tool calls)
            $textContent = '';

            // Stream each event to the frontend
            foreach ($stream as $event) {
                // Access chunkType property instead of type() method (Prism v0.92+)
                $eventType = $event->chunkType->value ?? 'text';

                // Accumulate text deltas for UI display
                // Use text property instead of delta (Prism v0.92+)
                if ($eventType === 'text_delta' || $eventType === 'text-delta' || $eventType === 'text') {
                    $textContent .= $event->text ?? '';
                }

                // Stream to frontend for real-time display
                $this->stream(
                    'streamed-message',
                    json_encode(['text' => $textContent, 'currentChunkType' => $eventType]),
                    true
                );
            }

            // Clear the stream
            $this->stream('streamed-message', '', false);

            // Add response to chat history for UI display only
            // All database persistence is handled automatically by BaseLlmAgent::execute()
            $this->chatHistory[] = [
                'role' => 'assistant',
                'content' => $textContent,
                'timestamp' => now()->format('H:i:s'),
            ];

            // Refresh context data
            $this->loadContextData();
            $this->loadTraceData();

            // Mark that we have running traces to enable polling
            $this->hasRunningTraces = true;

        } catch (\Exception $e) {
            $this->chatHistory[] = [
                'role' => 'error',
                'content' => 'Error: '.$e->getMessage(),
                'timestamp' => now()->format('H:i:s'),
            ];
        } finally {
            $this->isLoading = false;
            $this->loadContextData();
            $this->dispatch('chat-updated');
        }
    }

    public function clearChat()
    {
        $oldSessionId = $this->sessionId;

        // Clear all chat and context data
        $this->chatHistory = [];
        $this->contextData = [];
        $this->memoryData = [];
        $this->sessionData = [];
        $this->longTermMemoryData = [];
        $this->contextStateData = [];
        $this->traceData = [];
        $this->hasRunningTraces = false;

        // Generate new session ID
        $this->sessionId = $this->generateUniqueSessionId();

        // Log the chat clear for debugging
        logger()->info('Chat cleared', [
            'agent' => $this->selectedAgent,
            'old_session_id' => $oldSessionId,
            'new_session_id' => $this->sessionId,
        ]);

        // Reload context data with new session
        $this->loadContextData();
        $this->loadTraceData();

        // Dispatch event to reset scroll position
        $this->dispatch('chat-updated');
    }

    public function openLoadSessionModal()
    {
        logger()->info('openLoadSessionModal called at '.now());
        $this->showLoadSessionModal = true;
        $this->loadSessionId = '';
        logger()->info('showLoadSessionModal set to: '.($this->showLoadSessionModal ? 'true' : 'false'));
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
            $this->hasRunningTraces = false;

            return;
        }

        try {
            // Get trace spans for this session
            $spans = TraceSpan::where('session_id', $this->sessionId)
                ->orderBy('start_time', 'asc')
                ->get();

            if ($spans->isEmpty()) {
                $this->traceData = [];
                $this->hasRunningTraces = false;

                return;
            }

            // Check if any spans are still running
            $this->hasRunningTraces = $spans->contains(function ($span) {
                return $span->status === 'running';
            });

            // Build hierarchical structure
            $this->traceData = $this->buildTraceTree($spans);
        } catch (\Exception $e) {
            $this->traceData = ['error' => $e->getMessage()];
            $this->hasRunningTraces = false;
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
        logger()->info('updatedSelectedAgent called', [
            'old_agent' => $this->selectedAgent ?? 'none',
            'new_value' => $value ?? 'empty',
            'session_id_before' => $this->sessionId,
        ]);

        if (! empty($value)) {
            $this->selectAgent($value);
        } else {
            // Clear all data when no agent is selected
            $this->selectedAgent = '';
            $this->agentInfo = [];
            $this->contextData = [];
            $this->memoryData = [];
            $this->sessionData = [];
            $this->longTermMemoryData = [];
            $this->contextStateData = [];
            $this->traceData = [];
            $this->chatHistory = [];
            $this->hasRunningTraces = false;

            // Generate a new session ID even when deselecting to ensure clean state
            $this->sessionId = $this->generateUniqueSessionId();

            logger()->info('Agent deselected', [
                'new_session_id' => $this->sessionId,
            ]);
        }

        // Force a re-render to ensure UI updates
        $this->dispatch('chat-updated');
    }

    /**
     * Manual agent selection method that can be called from the UI
     * This provides an alternative to the wire:model.live binding
     */
    public function changeAgent($agentName)
    {
        logger()->info('changeAgent called manually', [
            'agent' => $agentName,
            'current_agent' => $this->selectedAgent,
            'current_session' => $this->sessionId,
            'current_chat_count' => count($this->chatHistory),
        ]);

        // COMPLETELY RESET THE COMPONENT STATE
        if ($this->selectedAgent !== $agentName) {
            logger()->info('Forcing complete component reset');

            // Step 1: Reset ALL component state to initial values
            $this->reset([
                'chatHistory',
                'contextData',
                'memoryData',
                'sessionData',
                'longTermMemoryData',
                'contextStateData',
                'traceData',
                'agentInfo'
            ]);

            // Step 2: Force boolean and other primitive resets
            $this->hasRunningTraces = false;
            $this->isLoading = false;
            $this->activeTab = 'agent-info';

            // Step 3: Generate completely new session
            $oldSession = $this->sessionId;
            $this->sessionId = $this->generateUniqueSessionId();

            // Step 4: Set the new agent
            $this->selectedAgent = $agentName;

            logger()->info('Component state completely reset', [
                'old_session' => $oldSession,
                'new_session' => $this->sessionId,
                'new_agent' => $agentName,
                'chat_history_count' => count($this->chatHistory),
                'context_data_empty' => empty($this->contextData),
            ]);

            // Step 5: Load fresh data for new agent
            $this->loadAgentInfo();
            $this->loadContextData();
            $this->loadTraceData();

        } else {
            // Same agent selected, just set it
            $this->selectedAgent = $agentName;
        }

        // Force complete UI refresh
        $this->dispatch('agent-changed', $agentName);
        $this->dispatch('chat-updated');

        logger()->info('changeAgent completed', [
            'final_agent' => $this->selectedAgent,
            'final_session' => $this->sessionId,
            'final_chat_count' => count($this->chatHistory),
        ]);
    }

    #[On('setSessionId')]
    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
        $this->loadContextData();
        $this->loadTraceData();
    }

    #[On('refresh-trace-data')]
    public function refreshTraceData()
    {
        $this->loadTraceData();
    }

    public function getMessageCharacterCount()
    {
        return strlen($this->message);
    }

    /**
     * Generate a unique session ID that doesn't already exist in the database
     */
    private function generateUniqueSessionId(): string
    {
        $attempts = 0;
        $maxAttempts = 10;

        do {
            try {
                // Use ULID for better uniqueness (time-ordered + random)
                $sessionId = 'chat-'.Str::ulid()->toString();

                // Check if this session ID already exists
                $exists = AgentSession::where('session_id', $sessionId)->exists();

                if (! $exists) {
                    return $sessionId;
                }

                $attempts++;

                // Log if we're having collision issues
                if ($attempts > 3) {
                    logger()->warning('Session ID collision detected', [
                        'attempt' => $attempts,
                        'colliding_session_id' => $sessionId,
                    ]);
                }
            } catch (\Exception $e) {
                logger()->error('Error generating session ID', [
                    'attempt' => $attempts,
                    'error' => $e->getMessage(),
                ]);
                $attempts++;
            }
        } while ($attempts < $maxAttempts);

        // Fallback: use timestamp + microseconds + random for ultimate uniqueness
        $microtime = microtime(true);
        $fallbackSessionId = 'chat-'.now()->timestamp.'-'.str_replace('.', '', $microtime).'-'.Str::random(6);

        logger()->warning('Using fallback session ID generation', [
            'fallback_session_id' => $fallbackSessionId,
            'max_attempts_reached' => $maxAttempts,
        ]);

        return $fallbackSessionId;
    }

    public function render()
    {
        return view('vizra-adk::livewire.chat-interface');
    }
}
