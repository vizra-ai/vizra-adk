# Vizra ADK Gap Analysis: What's Missing Compared to Other Agent Frameworks

> **Generated**: February 2026
> **Compared Against**: LangGraph, CrewAI, AutoGen, LlamaIndex, Phidata/Agno, OpenAI Assistants

Based on a thorough investigation of the Vizra ADK codebase and comparison with leading AI agent frameworks, this document identifies gaps and provides tailored recommendations.

---

## Table of Contents

1. [Checkpointing & Durable Execution](#1-checkpointing--durable-execution)
2. [Human-in-the-Loop (HITL) Native Support](#2-human-in-the-loop-hitl-native-support)
3. [Guardrails & Safety Layer](#3-guardrails--safety-layer)
4. [Planning & Reasoning Agents](#4-planning--reasoning-agents)
5. [Tool Permission & Authorization Framework](#5-tool-permission--authorization-framework)
6. [OpenTelemetry Integration](#6-opentelemetry-integration)
7. [Role-Based Agent Collaboration](#7-role-based-agent-collaboration-crewai-style)
8. [Advanced RAG Patterns](#8-advanced-rag-patterns)
9. [Streaming Enhancements](#9-streaming-enhancements)
10. [Cost Tracking & Budget Controls](#10-cost-tracking--budget-controls)
11. [Priority Summary](#summary-priority-recommendations)
12. [Sources](#sources)

---

## Current Strengths

Vizra ADK already has a **strong foundation**:

- ✅ Comprehensive tracing and observability (`Tracer`, `TraceSpan`, 12 events)
- ✅ Flexible workflow orchestration (sequential, parallel, conditional, loop)
- ✅ Multi-provider LLM support via Prism PHP
- ✅ Vector memory with multiple backends (pgvector, Meilisearch)
- ✅ Evaluation framework with LLM-as-judge
- ✅ MCP (Model Context Protocol) support
- ✅ Session-based memory persistence
- ✅ Sub-agent delegation
- ✅ Streaming support with thinking tokens

---

## 1. Checkpointing & Durable Execution

### What You Have
- `StateManager` persists context to the database
- `AgentContext` maintains conversation history
- Sessions can be resumed via session IDs

### What's Missing
**LangGraph-style durable execution** - the ability to checkpoint at every node/step and resume from any point after failures:

| Feature | Vizra ADK | LangGraph |
|---------|-----------|-----------|
| Session persistence | ✅ | ✅ |
| Step-level checkpointing | ❌ | ✅ |
| Time-travel debugging | ❌ | ✅ |
| Resume mid-workflow after crash | ❌ | ✅ |

### Recommendation

Add a `Checkpoint` model and `CheckpointManager` service that saves workflow state at each step in `BaseWorkflowAgent`. The existing `TraceSpan` model already has the structure - extend it with serialized state:

```php
// src/Services/CheckpointManager.php
class CheckpointManager
{
    public function save(string $workflowId, string $step, AgentContext $context): Checkpoint
    {
        return Checkpoint::create([
            'workflow_id' => $workflowId,
            'step_name' => $step,
            'state' => $context->toArray(),
            'status' => 'saved',
        ]);
    }

    public function restore(string $workflowId, ?string $step = null): AgentContext
    {
        $checkpoint = Checkpoint::where('workflow_id', $workflowId)
            ->when($step, fn($q) => $q->where('step_name', $step))
            ->latest()
            ->firstOrFail();

        return AgentContext::fromArray($checkpoint->state);
    }

    public function listCheckpoints(string $workflowId): Collection
    {
        return Checkpoint::where('workflow_id', $workflowId)
            ->orderBy('created_at')
            ->get();
    }
}

// Integration in BaseWorkflowAgent
protected function executeStep($step, $input, $context): mixed
{
    $checkpoint = $this->checkpointManager->save($this->workflowId, $step, $context);
    try {
        $result = $this->runStep($step, $input, $context);
        $this->checkpointManager->markComplete($checkpoint);
        return $result;
    } catch (Throwable $e) {
        $this->checkpointManager->markFailed($checkpoint, $e);
        throw $e;
    }
}
```

### Database Migration

```php
// database/migrations/xxxx_create_agent_checkpoints_table.php
Schema::create('agent_checkpoints', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('workflow_id')->index();
    $table->string('step_name');
    $table->json('state');
    $table->string('status')->default('saved'); // saved, completed, failed
    $table->text('error_message')->nullable();
    $table->timestamps();

    $table->index(['workflow_id', 'step_name']);
});
```

---

## 2. Human-in-the-Loop (HITL) Native Support

### What You Have
- `ParallelWorkflow` supports async execution via Laravel Queue (`src/Jobs/AgentJob.php`)
- Hooks exist (`beforeToolCall`, `afterToolResult`) that could intercept
- No native breakpoint or interrupt mechanism

### What's Missing
**Native interrupt/approval system** like LangGraph's `interrupt()` function:

```python
# LangGraph example - you don't have this
@node
def sensitive_action(state):
    if state["requires_approval"]:
        interrupt({"action": "delete_user", "user_id": 123})
    # Execution pauses here until human approves
```

### Recommendation

Create an `InterruptManager` service and `Interrupt` model:

```php
// src/Services/InterruptManager.php
class InterruptManager
{
    public function interrupt(AgentContext $context, string $reason, array $data): void
    {
        AgentInterrupt::create([
            'session_id' => $context->getSessionId(),
            'workflow_id' => $context->getState('workflow_id'),
            'step_name' => $context->getState('current_step'),
            'reason' => $reason,
            'data' => $data,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        event(new InterruptRequested($context, $reason, $data));

        throw new InterruptException($reason);
    }

    public function approve(string $interruptId, ?array $modifications = null, ?int $userId = null): AgentInterrupt
    {
        $interrupt = AgentInterrupt::findOrFail($interruptId);

        $interrupt->update([
            'status' => 'approved',
            'modifications' => $modifications,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        event(new InterruptApproved($interrupt));

        return $interrupt;
    }

    public function reject(string $interruptId, string $reason, ?int $userId = null): AgentInterrupt
    {
        $interrupt = AgentInterrupt::findOrFail($interruptId);

        $interrupt->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        event(new InterruptRejected($interrupt));

        return $interrupt;
    }

    public function resume(string $interruptId): AgentContext
    {
        $interrupt = AgentInterrupt::where('id', $interruptId)
            ->where('status', 'approved')
            ->firstOrFail();

        // Restore context and apply any modifications
        $context = $this->checkpointManager->restore($interrupt->workflow_id, $interrupt->step_name);

        if ($interrupt->modifications) {
            foreach ($interrupt->modifications as $key => $value) {
                $context->setState($key, $value);
            }
        }

        return $context;
    }

    public function getPending(?string $sessionId = null): Collection
    {
        return AgentInterrupt::where('status', 'pending')
            ->when($sessionId, fn($q) => $q->where('session_id', $sessionId))
            ->where('expires_at', '>', now())
            ->get();
    }
}

// src/Exceptions/InterruptException.php
class InterruptException extends Exception
{
    public function __construct(
        public string $reason,
        public array $data = [],
    ) {
        parent::__construct("Execution interrupted: {$reason}");
    }
}
```

Add to `BaseLlmAgent`:

```php
// src/Agents/BaseLlmAgent.php

/**
 * Pause execution and require human approval before continuing.
 */
protected function requireApproval(string $reason, array $data = []): void
{
    app(InterruptManager::class)->interrupt($this->context, $reason, $data);
}

/**
 * Check if a tool call requires approval based on configuration.
 */
protected function toolRequiresApproval(string $toolName): bool
{
    $config = config("vizra-adk.tool_permissions.{$toolName}", []);
    return $config['require_approval'] ?? false;
}
```

### Database Migration

```php
// database/migrations/xxxx_create_agent_interrupts_table.php
Schema::create('agent_interrupts', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('session_id')->index();
    $table->string('workflow_id')->nullable()->index();
    $table->string('step_name')->nullable();
    $table->string('reason');
    $table->json('data');
    $table->string('status')->default('pending'); // pending, approved, rejected, expired
    $table->json('modifications')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->foreignId('resolved_by')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamp('expires_at');
    $table->timestamps();

    $table->index(['status', 'expires_at']);
});
```

### API Endpoints

```php
// routes/api.php
Route::prefix('interrupts')->group(function () {
    Route::get('/', [InterruptController::class, 'index']);
    Route::get('/{id}', [InterruptController::class, 'show']);
    Route::post('/{id}/approve', [InterruptController::class, 'approve']);
    Route::post('/{id}/reject', [InterruptController::class, 'reject']);
    Route::post('/{id}/resume', [InterruptController::class, 'resume']);
});
```

---

## 3. Guardrails & Safety Layer

### What You Have
- Best practices documentation recommending validation
- `CONTROL_PARAMS` filtering in `BaseLlmAgent` (line 50-66)
- Evaluation assertions like `assertNotToxic()`, `assertNoPII()` in `BaseEvaluation.php`
- No runtime input/output filtering

### What's Missing
**Runtime guardrails** like OpenAI Guardrails, NeMo Guardrails, or Guardrails AI:

| Feature | Vizra ADK | Industry Standard |
|---------|-----------|-------------------|
| Prompt injection detection | ❌ | ✅ |
| Input validation/sanitization | Manual | Automated |
| Output moderation | ❌ | ✅ |
| Topic/content rails | ❌ | ✅ |
| PII redaction at runtime | ❌ | ✅ |

### Recommendation

Create a `GuardrailsManager` with configurable validators:

```php
// src/Guardrails/GuardrailsManager.php
class GuardrailsManager
{
    protected array $inputGuards = [];
    protected array $outputGuards = [];

    public function __construct()
    {
        $this->loadGuardsFromConfig();
    }

    protected function loadGuardsFromConfig(): void
    {
        $config = config('vizra-adk.guardrails', []);

        foreach ($config['input'] ?? [] as $guardClass => $options) {
            if ($options['enabled'] ?? true) {
                $this->inputGuards[] = app($guardClass, $options);
            }
        }

        foreach ($config['output'] ?? [] as $guardClass => $options) {
            if ($options['enabled'] ?? true) {
                $this->outputGuards[] = app($guardClass, $options);
            }
        }
    }

    public function validateInput(string $input, AgentContext $context): GuardrailResult
    {
        foreach ($this->inputGuards as $guard) {
            $result = $guard->check($input, $context);

            if ($result->blocked) {
                $this->logViolation('input', $guard, $input, $result, $context);
                return $result;
            }

            if ($result->modified) {
                $input = $result->modifiedContent;
            }
        }

        return GuardrailResult::pass($input);
    }

    public function validateOutput(string $output, AgentContext $context): GuardrailResult
    {
        foreach ($this->outputGuards as $guard) {
            $result = $guard->check($output, $context);

            if ($result->blocked) {
                $this->logViolation('output', $guard, $output, $result, $context);
                return $result;
            }

            if ($result->modified) {
                $output = $result->modifiedContent;
            }
        }

        return GuardrailResult::pass($output);
    }

    protected function logViolation(
        string $type,
        GuardInterface $guard,
        string $content,
        GuardrailResult $result,
        AgentContext $context
    ): void {
        Log::warning("[VizraADK:guardrails] {$type} violation", [
            'guard' => get_class($guard),
            'reason' => $result->reason,
            'session_id' => $context->getSessionId(),
            'user_id' => $context->getState('user_id'),
        ]);

        event(new GuardrailViolation($type, $guard, $result, $context));
    }
}

// src/Guardrails/GuardrailResult.php
class GuardrailResult
{
    public function __construct(
        public bool $blocked = false,
        public bool $modified = false,
        public ?string $reason = null,
        public ?string $modifiedContent = null,
        public ?string $userMessage = null,
        public array $metadata = [],
    ) {}

    public static function pass(?string $content = null): static
    {
        return new static(modifiedContent: $content);
    }

    public static function block(string $reason, ?string $userMessage = null): static
    {
        return new static(
            blocked: true,
            reason: $reason,
            userMessage: $userMessage ?? "I cannot process that request.",
        );
    }

    public static function modify(string $content, string $reason): static
    {
        return new static(
            modified: true,
            reason: $reason,
            modifiedContent: $content,
        );
    }
}

// src/Guardrails/Contracts/GuardInterface.php
interface GuardInterface
{
    public function check(string $content, AgentContext $context): GuardrailResult;
    public function getName(): string;
}
```

### Built-in Guards

```php
// src/Guardrails/Guards/PromptInjectionGuard.php
class PromptInjectionGuard implements GuardInterface
{
    protected array $patterns = [
        '/ignore\s+(all\s+)?(previous|above|prior)\s+(instructions|prompts)/i',
        '/disregard\s+(all\s+)?(previous|above|prior)/i',
        '/you\s+are\s+now\s+(?:a|an|in)\s+/i',
        '/\bsystem:\s*/i',
        '/\[INST\]/i',
        '/<<SYS>>/i',
        '/\bforget\s+(everything|all|your\s+instructions)/i',
        '/new\s+instructions:/i',
        '/override\s+(previous|system)/i',
    ];

    public function check(string $content, AgentContext $context): GuardrailResult
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return GuardrailResult::block(
                    "Potential prompt injection detected",
                    "I cannot process that request."
                );
            }
        }

        // Optional: Use LLM-based detection for sophisticated attacks
        if (config('vizra-adk.guardrails.prompt_injection.use_llm', false)) {
            return $this->llmCheck($content, $context);
        }

        return GuardrailResult::pass();
    }

    public function getName(): string
    {
        return 'prompt_injection';
    }
}

// src/Guardrails/Guards/PIIDetectionGuard.php
class PIIDetectionGuard implements GuardInterface
{
    protected array $patterns = [
        'ssn' => '/\b\d{3}-\d{2}-\d{4}\b/',
        'credit_card' => '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        'phone' => '/\b(\+\d{1,2}\s?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/',
        'ip_address' => '/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/',
    ];

    protected string $mode = 'redact'; // 'block' or 'redact'

    public function check(string $content, AgentContext $context): GuardrailResult
    {
        $detected = [];
        $redacted = $content;

        foreach ($this->patterns as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                $detected[$type] = count($matches[0]);

                if ($this->mode === 'redact') {
                    $redacted = preg_replace($pattern, "[REDACTED:{$type}]", $redacted);
                }
            }
        }

        if (empty($detected)) {
            return GuardrailResult::pass();
        }

        if ($this->mode === 'block') {
            return GuardrailResult::block(
                "PII detected: " . implode(', ', array_keys($detected)),
                "I cannot process content containing personal information."
            );
        }

        return GuardrailResult::modify(
            $redacted,
            "PII redacted: " . implode(', ', array_keys($detected))
        );
    }

    public function getName(): string
    {
        return 'pii_detection';
    }
}

// src/Guardrails/Guards/TopicGuard.php
class TopicGuard implements GuardInterface
{
    protected array $blockedTopics = [];
    protected array $allowedTopics = [];

    public function __construct(array $config = [])
    {
        $this->blockedTopics = $config['blocked'] ?? [];
        $this->allowedTopics = $config['allowed'] ?? [];
    }

    public function check(string $content, AgentContext $context): GuardrailResult
    {
        // Use embeddings to check topic similarity
        $embedding = app(EmbeddingService::class)->embed($content);

        foreach ($this->blockedTopics as $topic => $topicEmbedding) {
            $similarity = $this->cosineSimilarity($embedding, $topicEmbedding);

            if ($similarity > 0.85) {
                return GuardrailResult::block(
                    "Blocked topic detected: {$topic}",
                    "I cannot discuss that topic."
                );
            }
        }

        return GuardrailResult::pass();
    }

    public function getName(): string
    {
        return 'topic';
    }
}

// src/Guardrails/Guards/ToxicityGuard.php
class ToxicityGuard implements GuardInterface
{
    public function check(string $content, AgentContext $context): GuardrailResult
    {
        // Use moderation API (OpenAI, Perspective API, etc.)
        $result = app(ModerationService::class)->check($content);

        if ($result->flagged) {
            return GuardrailResult::block(
                "Toxic content detected: " . implode(', ', $result->categories),
                "I cannot process harmful content."
            );
        }

        return GuardrailResult::pass();
    }

    public function getName(): string
    {
        return 'toxicity';
    }
}
```

### Integration in BaseLlmAgent

```php
// src/Agents/BaseLlmAgent.php - modify execute() method

public function execute(mixed $input, AgentContext $context): string
{
    // ADD: Input guardrails
    $guardrails = app(GuardrailsManager::class);
    $inputResult = $guardrails->validateInput($input, $context);

    if ($inputResult->blocked) {
        $this->tracer->addEvent('guardrail_blocked', [
            'type' => 'input',
            'reason' => $inputResult->reason,
        ]);
        return $inputResult->userMessage;
    }

    // Use potentially modified input
    if ($inputResult->modified) {
        $input = $inputResult->modifiedContent;
    }

    // ... existing execution logic ...

    // ADD: Output guardrails (before returning)
    $outputResult = $guardrails->validateOutput($response, $context);

    if ($outputResult->blocked) {
        $this->tracer->addEvent('guardrail_blocked', [
            'type' => 'output',
            'reason' => $outputResult->reason,
        ]);
        return $outputResult->userMessage ?? "I cannot provide that response.";
    }

    if ($outputResult->modified) {
        $response = $outputResult->modifiedContent;
    }

    return $response;
}
```

### Configuration

```php
// config/vizra-adk.php

'guardrails' => [
    'enabled' => env('VIZRA_GUARDRAILS_ENABLED', true),

    'input' => [
        \Vizra\VizraADK\Guardrails\Guards\PromptInjectionGuard::class => [
            'enabled' => true,
            'use_llm' => false,
        ],
        \Vizra\VizraADK\Guardrails\Guards\PIIDetectionGuard::class => [
            'enabled' => true,
            'mode' => 'redact', // 'block' or 'redact'
        ],
        \Vizra\VizraADK\Guardrails\Guards\ToxicityGuard::class => [
            'enabled' => true,
        ],
    ],

    'output' => [
        \Vizra\VizraADK\Guardrails\Guards\PIIDetectionGuard::class => [
            'enabled' => true,
            'mode' => 'redact',
        ],
        \Vizra\VizraADK\Guardrails\Guards\ToxicityGuard::class => [
            'enabled' => true,
        ],
    ],
],
```

---

## 4. Planning & Reasoning Agents

### What You Have
- Support for reasoning models (o1) via `$model = 'o1'`
- Streaming support that passes through thinking tokens
- No framework-level planning or reflection

### What's Missing
**Explicit planning/re-planning patterns** like LangGraph's reflection agents:

| Pattern | Vizra ADK | LangGraph/Phidata |
|---------|-----------|-------------------|
| Plan-then-execute | ❌ | ✅ |
| Reflection loops | ❌ | ✅ |
| Self-critique | ❌ | ✅ |
| Dynamic re-planning | ❌ | ✅ |

### Recommendation

Create planning agent patterns:

```php
// src/Agents/Patterns/PlanningAgent.php
abstract class PlanningAgent extends BaseLlmAgent
{
    protected int $maxReplanAttempts = 3;
    protected float $satisfactionThreshold = 0.8;

    protected string $plannerInstructions = <<<PROMPT
    You are a planning assistant. Given a task, create a detailed step-by-step plan.

    Output your plan as JSON:
    {
        "goal": "The main objective",
        "steps": [
            {"id": 1, "action": "Description", "dependencies": [], "tools": ["tool_name"]},
            ...
        ],
        "success_criteria": ["Criterion 1", "Criterion 2"]
    }
    PROMPT;

    protected string $reflectionInstructions = <<<PROMPT
    Evaluate the result against the original goal and success criteria.

    Output your evaluation as JSON:
    {
        "satisfactory": true/false,
        "score": 0.0-1.0,
        "strengths": ["What went well"],
        "weaknesses": ["What could be improved"],
        "suggestions": ["Specific improvements"]
    }
    PROMPT;

    public function execute(mixed $input, AgentContext $context): string
    {
        // Step 1: Generate plan
        $plan = $this->generatePlan($input, $context);
        $context->setState('current_plan', $plan);

        $this->tracer->addEvent('plan_generated', ['plan' => $plan]);

        for ($attempt = 0; $attempt < $this->maxReplanAttempts; $attempt++) {
            try {
                // Step 2: Execute plan steps
                $result = $this->executePlan($plan, $context);

                // Step 3: Reflect on result
                $reflection = $this->reflect($input, $result, $plan, $context);

                $this->tracer->addEvent('reflection_completed', [
                    'attempt' => $attempt + 1,
                    'score' => $reflection->score,
                    'satisfactory' => $reflection->satisfactory,
                ]);

                if ($reflection->satisfactory || $reflection->score >= $this->satisfactionThreshold) {
                    return $result;
                }

                // Step 4: Re-plan based on reflection
                $plan = $this->replan($input, $result, $reflection, $context);
                $context->setState('current_plan', $plan);

                $this->tracer->addEvent('replanned', [
                    'attempt' => $attempt + 1,
                    'new_plan' => $plan,
                ]);

            } catch (PlanExecutionException $e) {
                $this->tracer->addEvent('plan_execution_failed', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);

                $plan = $this->replan($input, null, $e->getMessage(), $context);
            }
        }

        return $result ?? "Unable to complete task after {$this->maxReplanAttempts} attempts.";
    }

    protected function generatePlan(mixed $input, AgentContext $context): Plan
    {
        $response = $this->callLlm(
            $this->plannerInstructions,
            "Create a plan for: {$input}",
            $context
        );

        return Plan::fromJson($response);
    }

    protected function executePlan(Plan $plan, AgentContext $context): string
    {
        $results = [];

        foreach ($plan->steps as $step) {
            // Check dependencies
            foreach ($step->dependencies as $depId) {
                if (!isset($results[$depId])) {
                    throw new PlanExecutionException("Dependency {$depId} not completed");
                }
            }

            // Execute step
            $stepResult = $this->executeStep($step, $results, $context);
            $results[$step->id] = $stepResult;

            $context->setState("step_{$step->id}_result", $stepResult);
        }

        return $this->synthesizeResults($plan, $results, $context);
    }

    protected function reflect(
        mixed $input,
        string $result,
        Plan $plan,
        AgentContext $context
    ): Reflection {
        $prompt = <<<PROMPT
        Original Task: {$input}

        Plan: {$plan->toJson()}

        Result: {$result}

        {$this->reflectionInstructions}
        PROMPT;

        $response = $this->callLlm($this->reflectionInstructions, $prompt, $context);

        return Reflection::fromJson($response);
    }

    protected function replan(
        mixed $input,
        ?string $previousResult,
        mixed $feedback,
        AgentContext $context
    ): Plan {
        $feedbackText = $feedback instanceof Reflection
            ? "Weaknesses: " . implode(", ", $feedback->weaknesses) .
              "\nSuggestions: " . implode(", ", $feedback->suggestions)
            : $feedback;

        $prompt = <<<PROMPT
        Original Task: {$input}

        Previous Result: {$previousResult}

        Feedback: {$feedbackText}

        Create an improved plan that addresses the feedback.
        PROMPT;

        $response = $this->callLlm($this->plannerInstructions, $prompt, $context);

        return Plan::fromJson($response);
    }

    abstract protected function executeStep(
        PlanStep $step,
        array $previousResults,
        AgentContext $context
    ): string;

    abstract protected function synthesizeResults(
        Plan $plan,
        array $results,
        AgentContext $context
    ): string;
}

// src/Agents/Patterns/ReflectionAgent.php
abstract class ReflectionAgent extends BaseLlmAgent
{
    protected int $maxReflections = 3;
    protected float $acceptanceThreshold = 0.8;

    protected string $criticInstructions = <<<PROMPT
    You are a critical reviewer. Evaluate the response for:
    1. Accuracy and correctness
    2. Completeness
    3. Clarity and coherence
    4. Relevance to the original request

    Output as JSON:
    {
        "score": 0.0-1.0,
        "issues": ["Issue 1", "Issue 2"],
        "suggestions": ["Suggestion 1", "Suggestion 2"]
    }
    PROMPT;

    public function execute(mixed $input, AgentContext $context): string
    {
        $response = parent::execute($input, $context);

        for ($i = 0; $i < $this->maxReflections; $i++) {
            $critique = $this->critique($input, $response, $context);

            $this->tracer->addEvent('critique_completed', [
                'iteration' => $i + 1,
                'score' => $critique->score,
                'issues_count' => count($critique->issues),
            ]);

            if ($critique->score >= $this->acceptanceThreshold) {
                break;
            }

            $response = $this->refine($input, $response, $critique, $context);

            $this->tracer->addEvent('response_refined', [
                'iteration' => $i + 1,
            ]);
        }

        return $response;
    }

    protected function critique(string $input, string $response, AgentContext $context): Critique
    {
        $prompt = <<<PROMPT
        Original Request: {$input}

        Response to Evaluate: {$response}

        {$this->criticInstructions}
        PROMPT;

        $result = $this->callLlmAsJson($prompt, $context);

        return new Critique(
            score: $result['score'],
            issues: $result['issues'],
            suggestions: $result['suggestions']
        );
    }

    protected function refine(
        string $input,
        string $response,
        Critique $critique,
        AgentContext $context
    ): string {
        $prompt = <<<PROMPT
        Original Request: {$input}

        Your Previous Response: {$response}

        Critique:
        - Issues: {implode(", ", $critique->issues)}
        - Suggestions: {implode(", ", $critique->suggestions)}

        Please provide an improved response that addresses these issues.
        PROMPT;

        return parent::execute($prompt, $context);
    }
}
```

### Data Classes

```php
// src/Agents/Patterns/Data/Plan.php
class Plan implements JsonSerializable
{
    public function __construct(
        public string $goal,
        public array $steps,
        public array $successCriteria,
    ) {}

    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);

        return new static(
            goal: $data['goal'],
            steps: array_map(fn($s) => PlanStep::fromArray($s), $data['steps']),
            successCriteria: $data['success_criteria'],
        );
    }

    public function toJson(): string
    {
        return json_encode($this);
    }

    public function jsonSerialize(): array
    {
        return [
            'goal' => $this->goal,
            'steps' => $this->steps,
            'success_criteria' => $this->successCriteria,
        ];
    }
}

// src/Agents/Patterns/Data/Reflection.php
class Reflection
{
    public function __construct(
        public bool $satisfactory,
        public float $score,
        public array $strengths,
        public array $weaknesses,
        public array $suggestions,
    ) {}

    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true);

        return new static(
            satisfactory: $data['satisfactory'],
            score: $data['score'],
            strengths: $data['strengths'] ?? [],
            weaknesses: $data['weaknesses'] ?? [],
            suggestions: $data['suggestions'] ?? [],
        );
    }
}
```

---

## 5. Tool Permission & Authorization Framework

### What You Have
- Tools receive `AgentContext` with user info via `$context->getState('user_id')`
- Manual permission checks recommended in best practices
- No built-in authorization layer

### What's Missing
**Framework-level tool authorization** with declarative permissions:

### Recommendation

```php
// src/Services/ToolAuthorizer.php
class ToolAuthorizer
{
    protected array $policies = [];

    public function __construct()
    {
        $this->policies = config('vizra-adk.tool_permissions', []);
    }

    public function authorize(
        ToolInterface $tool,
        array $arguments,
        AgentContext $context
    ): AuthorizationResult {
        $toolName = $tool->definition()['name'];
        $policy = $this->policies[$toolName] ?? $this->policies['*'] ?? [];
        $user = $context->getUser();

        // Check if tool is enabled
        if (isset($policy['enabled']) && !$policy['enabled']) {
            return AuthorizationResult::denied("Tool {$toolName} is disabled");
        }

        // Check role-based permission
        if (!$this->checkRoles($user, $policy['roles'] ?? ['*'])) {
            return AuthorizationResult::denied("User role not authorized for {$toolName}");
        }

        // Check rate limiting
        if (isset($policy['rate_limit'])) {
            if (!$this->checkRateLimit($user, $toolName, $policy['rate_limit'])) {
                return AuthorizationResult::denied("Rate limit exceeded for {$toolName}");
            }
        }

        // Check argument-level restrictions
        foreach ($policy['argument_policies'] ?? [] as $arg => $argPolicy) {
            if (isset($arguments[$arg])) {
                $argResult = $this->checkArgumentPolicy($user, $arguments[$arg], $argPolicy);
                if (!$argResult->allowed) {
                    return $argResult;
                }
            }
        }

        // Check if approval is required
        if ($policy['require_approval'] ?? false) {
            return AuthorizationResult::requiresApproval(
                "Tool {$toolName} requires human approval",
                ['tool' => $toolName, 'arguments' => $arguments]
            );
        }

        // Log if audit is enabled
        if ($policy['audit'] ?? false) {
            $this->auditLog($toolName, $arguments, $context);
        }

        return AuthorizationResult::allowed();
    }

    protected function checkRoles(?object $user, array $allowedRoles): bool
    {
        if (in_array('*', $allowedRoles)) {
            return true;
        }

        if (!$user) {
            return in_array('guest', $allowedRoles);
        }

        $userRoles = method_exists($user, 'getRoles')
            ? $user->getRoles()
            : [$user->role ?? 'user'];

        return !empty(array_intersect($userRoles, $allowedRoles));
    }

    protected function checkRateLimit(?object $user, string $toolName, string $limit): bool
    {
        // Parse limit like "10/hour" or "100/day"
        [$count, $period] = explode('/', $limit);

        $key = "tool_rate:{$user?->id}:{$toolName}";
        $window = match($period) {
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            default => 3600,
        };

        $current = Cache::get($key, 0);

        if ($current >= (int)$count) {
            return false;
        }

        Cache::put($key, $current + 1, $window);

        return true;
    }

    protected function auditLog(string $toolName, array $arguments, AgentContext $context): void
    {
        ToolAuditLog::create([
            'tool_name' => $toolName,
            'arguments' => $arguments,
            'user_id' => $context->getState('user_id'),
            'session_id' => $context->getSessionId(),
            'agent_name' => $context->getState('agent_name'),
            'ip_address' => request()->ip(),
        ]);
    }
}

// src/Services/AuthorizationResult.php
class AuthorizationResult
{
    public function __construct(
        public bool $allowed,
        public bool $requiresApproval = false,
        public ?string $reason = null,
        public array $approvalData = [],
    ) {}

    public static function allowed(): static
    {
        return new static(allowed: true);
    }

    public static function denied(string $reason): static
    {
        return new static(allowed: false, reason: $reason);
    }

    public static function requiresApproval(string $reason, array $data): static
    {
        return new static(
            allowed: false,
            requiresApproval: true,
            reason: $reason,
            approvalData: $data,
        );
    }
}
```

### Integration

```php
// In BaseLlmAgent::executeToolCall() around line 610

protected function executeToolCall(
    ToolInterface $tool,
    array $arguments,
    AgentContext $context
): string {
    // ADD: Authorization check
    $authResult = app(ToolAuthorizer::class)->authorize($tool, $arguments, $context);

    if (!$authResult->allowed) {
        if ($authResult->requiresApproval) {
            // Trigger human-in-the-loop
            app(InterruptManager::class)->interrupt(
                $context,
                $authResult->reason,
                $authResult->approvalData
            );
        }

        return json_encode(['error' => $authResult->reason]);
    }

    // Continue with existing execution...
    return $tool->execute($arguments, $context);
}
```

### Configuration

```php
// config/vizra-adk.php

'tool_permissions' => [
    // Default policy for all tools
    '*' => [
        'roles' => ['*'],
        'rate_limit' => '100/hour',
        'audit' => false,
    ],

    // Specific tool policies
    'database_query' => [
        'roles' => ['admin', 'analyst'],
        'require_approval' => false,
        'rate_limit' => '50/hour',
        'audit' => true,
        'argument_policies' => [
            'query' => [
                'blocked_patterns' => ['/DROP\s+TABLE/i', '/DELETE\s+FROM/i'],
            ],
        ],
    ],

    'send_email' => [
        'roles' => ['*'],
        'rate_limit' => '10/hour',
        'audit' => true,
    ],

    'delete_record' => [
        'roles' => ['admin'],
        'require_approval' => true,
        'audit' => true,
    ],

    'execute_code' => [
        'enabled' => false, // Completely disabled
    ],
],
```

---

## 6. OpenTelemetry Integration

### What You Have
- Comprehensive custom `Tracer` service (`src/Services/Tracer.php`)
- `TraceSpan` model with parent-child relationships
- Token usage tracking
- Custom event system (12 events)

### What's Missing
**OpenTelemetry semantic conventions** for industry-standard observability:

| Feature | Vizra ADK | OTel Standard |
|---------|-----------|---------------|
| Custom trace format | ✅ | - |
| OTel GenAI conventions | ❌ | ✅ |
| Export to Datadog/Jaeger/etc | ❌ | ✅ |
| Standardized LLM spans | ❌ | ✅ |

### Recommendation

```php
// src/Services/OpenTelemetryExporter.php
class OpenTelemetryExporter
{
    protected ?TracerInterface $otelTracer = null;

    public function __construct()
    {
        if (!config('vizra-adk.observability.opentelemetry.enabled')) {
            return;
        }

        $this->initializeTracer();
    }

    protected function initializeTracer(): void
    {
        $exporter = match(config('vizra-adk.observability.opentelemetry.exporter')) {
            'otlp' => new OtlpHttpExporter(
                config('vizra-adk.observability.opentelemetry.endpoint')
            ),
            'jaeger' => new JaegerExporter(
                config('vizra-adk.observability.opentelemetry.endpoint')
            ),
            'zipkin' => new ZipkinExporter(
                config('vizra-adk.observability.opentelemetry.endpoint')
            ),
            default => new OtlpHttpExporter('http://localhost:4318'),
        };

        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor($exporter)
        );

        $this->otelTracer = $tracerProvider->getTracer(
            'vizra-adk',
            config('vizra-adk.version', '0.0.1')
        );
    }

    public function exportSpan(TraceSpan $span): void
    {
        if (!$this->otelTracer) {
            return;
        }

        $otelSpan = $this->otelTracer->spanBuilder($span->name)
            ->setSpanKind($this->mapSpanKind($span->type))
            ->setStartTimestamp($this->toNanoseconds($span->start_time))
            ->startSpan();

        // Set parent context if exists
        if ($span->parent_span_id) {
            // Link to parent span
        }

        // Common attributes
        $otelSpan->setAttribute('vizra.session_id', $span->session_id);
        $otelSpan->setAttribute('vizra.agent_name', $span->agent_name);
        $otelSpan->setAttribute('vizra.span_type', $span->type);

        // Type-specific attributes using GenAI semantic conventions
        match ($span->type) {
            'llm_call' => $this->addLlmAttributes($otelSpan, $span),
            'tool_call' => $this->addToolAttributes($otelSpan, $span),
            'agent_run' => $this->addAgentAttributes($otelSpan, $span),
            default => null,
        };

        // Set status
        if ($span->status === 'error') {
            $otelSpan->setStatus(StatusCode::STATUS_ERROR, $span->error_message);
        } else {
            $otelSpan->setStatus(StatusCode::STATUS_OK);
        }

        $otelSpan->end($this->toNanoseconds($span->end_time));
    }

    protected function addLlmAttributes(SpanInterface $otelSpan, TraceSpan $span): void
    {
        $metadata = $span->metadata ?? [];
        $output = $span->output ?? [];

        // GenAI semantic conventions
        $otelSpan->setAttribute('gen_ai.system', $this->mapProvider($metadata['provider'] ?? 'openai'));
        $otelSpan->setAttribute('gen_ai.request.model', $metadata['model'] ?? 'unknown');
        $otelSpan->setAttribute('gen_ai.request.temperature', $metadata['temperature'] ?? 1.0);
        $otelSpan->setAttribute('gen_ai.request.max_tokens', $metadata['max_tokens'] ?? null);

        // Usage metrics
        if (isset($output['usage'])) {
            $otelSpan->setAttribute('gen_ai.usage.input_tokens', $output['usage']['input_tokens'] ?? 0);
            $otelSpan->setAttribute('gen_ai.usage.output_tokens', $output['usage']['output_tokens'] ?? 0);
            $otelSpan->setAttribute('gen_ai.usage.total_tokens', $output['usage']['total_tokens'] ?? 0);
        }

        // Response info
        $otelSpan->setAttribute('gen_ai.response.finish_reason', $output['finish_reason'] ?? 'unknown');
    }

    protected function addToolAttributes(SpanInterface $otelSpan, TraceSpan $span): void
    {
        $otelSpan->setAttribute('tool.name', $span->name);
        $otelSpan->setAttribute('tool.input', json_encode($span->input ?? []));

        if ($span->duration_ms) {
            $otelSpan->setAttribute('tool.duration_ms', $span->duration_ms);
        }
    }

    protected function addAgentAttributes(SpanInterface $otelSpan, TraceSpan $span): void
    {
        $otelSpan->setAttribute('agent.name', $span->agent_name);
        $otelSpan->setAttribute('agent.type', $span->metadata['agent_type'] ?? 'llm');

        if (isset($span->metadata['tools'])) {
            $otelSpan->setAttribute('agent.tools', json_encode($span->metadata['tools']));
        }
    }

    protected function mapSpanKind(string $type): int
    {
        return match ($type) {
            'llm_call' => SpanKind::KIND_CLIENT,
            'tool_call' => SpanKind::KIND_INTERNAL,
            'agent_run' => SpanKind::KIND_SERVER,
            default => SpanKind::KIND_INTERNAL,
        };
    }

    protected function mapProvider(string $provider): string
    {
        return match (strtolower($provider)) {
            'openai' => 'openai',
            'anthropic' => 'anthropic',
            'google', 'gemini' => 'vertex_ai',
            'ollama' => 'ollama',
            default => $provider,
        };
    }

    protected function toNanoseconds(float $timestamp): int
    {
        return (int)($timestamp * 1_000_000_000);
    }
}
```

### Event Listener Integration

```php
// src/Listeners/OpenTelemetryListener.php
class OpenTelemetryListener
{
    public function __construct(
        protected OpenTelemetryExporter $exporter
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            AgentExecutionFinished::class,
            [$this, 'handleExecutionFinished']
        );

        $events->listen(
            LlmResponseReceived::class,
            [$this, 'handleLlmResponse']
        );

        $events->listen(
            ToolCallCompleted::class,
            [$this, 'handleToolCompleted']
        );
    }

    public function handleExecutionFinished(AgentExecutionFinished $event): void
    {
        $span = TraceSpan::where('trace_id', $event->context->getState('trace_id'))
            ->where('type', 'agent_run')
            ->first();

        if ($span) {
            $this->exporter->exportSpan($span);
        }
    }
}
```

### Configuration

```php
// config/vizra-adk.php

'observability' => [
    'opentelemetry' => [
        'enabled' => env('VIZRA_OTEL_ENABLED', false),
        'exporter' => env('VIZRA_OTEL_EXPORTER', 'otlp'), // otlp, jaeger, zipkin
        'endpoint' => env('VIZRA_OTEL_ENDPOINT', 'http://localhost:4318'),
        'service_name' => env('VIZRA_OTEL_SERVICE_NAME', 'vizra-adk'),
        'headers' => [
            // 'Authorization' => 'Bearer ' . env('OTEL_AUTH_TOKEN'),
        ],
    ],
],
```

---

## 7. Role-Based Agent Collaboration (CrewAI-style)

### What You Have
- `DelegateToSubAgentTool` for basic delegation
- Workflows for orchestration
- No role/persona assignment system

### What's Missing
**Explicit role-based agent teams** like CrewAI

### Recommendation

```php
// src/Traits/HasRole.php
trait HasRole
{
    protected ?string $role = null;
    protected ?string $goal = null;
    protected ?string $backstory = null;

    public function withRole(string $role): static
    {
        $clone = clone $this;
        $clone->role = $role;
        return $clone;
    }

    public function withGoal(string $goal): static
    {
        $clone = clone $this;
        $clone->goal = $goal;
        return $clone;
    }

    public function withBackstory(string $backstory): static
    {
        $clone = clone $this;
        $clone->backstory = $backstory;
        return $clone;
    }

    protected function getRoleContext(): string
    {
        if (!$this->role) {
            return '';
        }

        $context = "## Your Role\n";
        $context .= "You are acting as: **{$this->role}**\n\n";

        if ($this->goal) {
            $context .= "## Your Goal\n{$this->goal}\n\n";
        }

        if ($this->backstory) {
            $context .= "## Background\n{$this->backstory}\n\n";
        }

        return $context;
    }

    protected function getInstructionsWithRole(): string
    {
        return $this->getRoleContext() . $this->instructions;
    }
}

// src/Workflows/CrewWorkflow.php
class CrewWorkflow extends BaseWorkflowAgent
{
    protected array $crew = [];
    protected array $tasks = [];
    protected string $process = 'sequential'; // sequential, hierarchical, parallel
    protected ?BaseLlmAgent $manager = null;

    public function addMember(
        BaseLlmAgent $agent,
        string $role,
        string $goal,
        ?string $backstory = null
    ): static {
        $configuredAgent = $agent
            ->withRole($role)
            ->withGoal($goal);

        if ($backstory) {
            $configuredAgent = $configuredAgent->withBackstory($backstory);
        }

        $this->crew[$role] = [
            'agent' => $configuredAgent,
            'role' => $role,
            'goal' => $goal,
        ];

        return $this;
    }

    public function assignTask(
        string $role,
        string $description,
        ?string $expectedOutput = null,
        array $dependencies = []
    ): static {
        $this->tasks[] = [
            'id' => count($this->tasks) + 1,
            'role' => $role,
            'description' => $description,
            'expected_output' => $expectedOutput,
            'dependencies' => $dependencies,
        ];

        return $this;
    }

    public function withManager(BaseLlmAgent $manager): static
    {
        $this->manager = $manager->withRole(
            'Project Manager',
            'Coordinate team members and ensure task completion'
        );
        $this->process = 'hierarchical';

        return $this;
    }

    public function execute(mixed $input, AgentContext $context): string
    {
        $results = [];

        match ($this->process) {
            'sequential' => $results = $this->executeSequential($input, $context),
            'hierarchical' => $results = $this->executeHierarchical($input, $context),
            'parallel' => $results = $this->executeParallel($input, $context),
        };

        return $this->synthesizeResults($results, $context);
    }

    protected function executeSequential(mixed $input, AgentContext $context): array
    {
        $results = [];

        foreach ($this->tasks as $task) {
            $member = $this->crew[$task['role']] ?? null;

            if (!$member) {
                throw new \RuntimeException("No crew member with role: {$task['role']}");
            }

            // Build context from dependencies
            $taskContext = $this->buildTaskContext($task, $results, $input);

            // Execute task
            $result = $member['agent']->execute($taskContext, $context);

            $results[$task['id']] = [
                'task' => $task,
                'result' => $result,
                'role' => $task['role'],
            ];

            $this->tracer->addEvent('crew_task_completed', [
                'task_id' => $task['id'],
                'role' => $task['role'],
            ]);
        }

        return $results;
    }

    protected function executeHierarchical(mixed $input, AgentContext $context): array
    {
        // Manager decides task order and assignments
        $plan = $this->manager->execute(
            $this->buildManagerPrompt($input),
            $context
        );

        // Execute based on manager's plan
        // ...

        return [];
    }

    protected function executeParallel(mixed $input, AgentContext $context): array
    {
        $jobs = [];

        foreach ($this->tasks as $task) {
            $member = $this->crew[$task['role']];
            $taskContext = $this->buildTaskContext($task, [], $input);

            $jobs[] = new AgentJob(
                $member['agent'],
                $taskContext,
                $context
            );
        }

        // Dispatch all jobs
        $batch = Bus::batch($jobs)->dispatch();

        // Wait for completion
        $batch->wait();

        return $batch->results();
    }

    protected function buildTaskContext(array $task, array $results, mixed $input): string
    {
        $context = "## Task\n{$task['description']}\n\n";

        if ($task['expected_output']) {
            $context .= "## Expected Output\n{$task['expected_output']}\n\n";
        }

        if (!empty($task['dependencies'])) {
            $context .= "## Context from Previous Tasks\n";

            foreach ($task['dependencies'] as $depId) {
                if (isset($results[$depId])) {
                    $dep = $results[$depId];
                    $context .= "### From {$dep['role']}\n{$dep['result']}\n\n";
                }
            }
        }

        $context .= "## Original Request\n{$input}";

        return $context;
    }

    protected function synthesizeResults(array $results, AgentContext $context): string
    {
        $synthesis = "## Crew Results\n\n";

        foreach ($results as $result) {
            $synthesis .= "### {$result['role']}\n";
            $synthesis .= "{$result['result']}\n\n";
        }

        return $synthesis;
    }
}
```

### Usage Example

```php
$crew = new CrewWorkflow();

$crew->addMember(
    new ResearchAgent(),
    role: 'Senior Researcher',
    goal: 'Find accurate and comprehensive information on the topic',
    backstory: 'You have 20 years of experience in academic research...'
);

$crew->addMember(
    new WriterAgent(),
    role: 'Technical Writer',
    goal: 'Create clear, engaging content from research',
    backstory: 'You specialize in making complex topics accessible...'
);

$crew->addMember(
    new EditorAgent(),
    role: 'Editor',
    goal: 'Ensure content quality and accuracy',
    backstory: 'You have a keen eye for detail and clarity...'
);

$crew->assignTask('Senior Researcher', 'Research the topic thoroughly');
$crew->assignTask('Technical Writer', 'Write an article based on research', dependencies: [1]);
$crew->assignTask('Editor', 'Review and polish the article', dependencies: [2]);

$result = $crew->execute('Write about quantum computing', $context);
```

---

## 8. Advanced RAG Patterns

### What You Have
- `VectorMemoryManager` with search
- `VectorMemoryTool` for agent access
- `DocumentChunker` for splitting
- Basic similarity search

### What's Missing
**Advanced RAG strategies** from LlamaIndex's Agentic RAG:

| Pattern | Vizra ADK | LlamaIndex |
|---------|-----------|------------|
| Basic RAG | ✅ | ✅ |
| Hybrid search (BM25 + vector) | ❌ | ✅ |
| Self-RAG (decide when to retrieve) | ❌ | ✅ |
| CRAG (corrective RAG) | ❌ | ✅ |
| Query decomposition | ❌ | ✅ |
| Document agents | ❌ | ✅ |
| Reranking | ❌ | ✅ |

### Recommendation

```php
// src/Services/AdvancedRagManager.php
class AdvancedRagManager
{
    public function __construct(
        protected VectorMemoryManager $vectorMemory,
        protected ?RerankerInterface $reranker = null,
        protected ?EmbeddingService $embeddings = null,
    ) {}

    /**
     * Hybrid search: combine BM25 (keyword) + vector (semantic)
     */
    public function hybridSearch(
        string $query,
        array $options = []
    ): array {
        $vectorResults = $this->vectorMemory->search($query, array_merge($options, [
            'limit' => ($options['limit'] ?? 5) * 2, // Get more for fusion
        ]));

        $bm25Results = $this->bm25Search($query, $options);

        return $this->reciprocalRankFusion($vectorResults, $bm25Results, $options['limit'] ?? 5);
    }

    /**
     * Self-RAG: let agent decide if retrieval is needed
     */
    public function selfRag(
        string $query,
        AgentContext $context,
        callable $llmCall
    ): SelfRagResult {
        // Step 1: Assess if retrieval is needed
        $assessmentPrompt = <<<PROMPT
        Determine if external knowledge retrieval is needed to answer this query.
        Query: {$query}

        Consider:
        - Is this factual information you might not have?
        - Does this require recent/specific data?
        - Can you answer confidently without retrieval?

        Respond with JSON: {"needs_retrieval": true/false, "reason": "explanation"}
        PROMPT;

        $assessment = json_decode($llmCall($assessmentPrompt), true);

        if (!$assessment['needs_retrieval']) {
            return new SelfRagResult(
                retrieved: false,
                reason: $assessment['reason'],
                documents: [],
            );
        }

        // Step 2: Retrieve
        $results = $this->vectorMemory->search($query);

        // Step 3: Assess relevance
        $relevancePrompt = <<<PROMPT
        Assess if these retrieved documents are relevant to the query.

        Query: {$query}

        Documents:
        {$this->formatDocuments($results)}

        Respond with JSON: {"relevant": true/false, "score": 0.0-1.0, "reason": "explanation"}
        PROMPT;

        $relevance = json_decode($llmCall($relevancePrompt), true);

        // Step 4: If not relevant, try reformulation
        if (!$relevance['relevant'] || $relevance['score'] < 0.5) {
            $reformulated = $this->reformulateQuery($query, $results, $llmCall);
            $results = $this->vectorMemory->search($reformulated);
        }

        return new SelfRagResult(
            retrieved: true,
            reason: $relevance['reason'],
            documents: $results,
            relevanceScore: $relevance['score'],
        );
    }

    /**
     * Corrective RAG (CRAG): validate and correct retrieved info
     */
    public function correctiveRag(
        string $query,
        AgentContext $context,
        callable $llmCall
    ): CorrectiveRagResult {
        $results = $this->vectorMemory->search($query, ['limit' => 10]);

        // Grade each document
        $gradedDocs = [];
        foreach ($results as $doc) {
            $gradePrompt = <<<PROMPT
            Grade this document's relevance to the query.

            Query: {$query}
            Document: {$doc['content']}

            Respond with JSON: {"grade": "correct|incorrect|ambiguous", "confidence": 0.0-1.0}
            PROMPT;

            $grade = json_decode($llmCall($gradePrompt), true);
            $gradedDocs[] = array_merge($doc, ['grade' => $grade]);
        }

        // Filter to correct documents
        $correctDocs = array_filter($gradedDocs, fn($d) => $d['grade']['grade'] === 'correct');

        // If not enough correct docs, supplement with web search (if available)
        if (count($correctDocs) < 3) {
            // Could trigger web search here
        }

        // Refine knowledge
        $refinedKnowledge = $this->refineKnowledge($query, $correctDocs, $llmCall);

        return new CorrectiveRagResult(
            documents: $correctDocs,
            refinedKnowledge: $refinedKnowledge,
            confidence: $this->calculateConfidence($gradedDocs),
        );
    }

    /**
     * Query decomposition for complex questions
     */
    public function decomposeAndSearch(
        string $complexQuery,
        callable $llmCall
    ): array {
        $decompositionPrompt = <<<PROMPT
        Break down this complex query into simpler sub-queries that can be answered independently.

        Query: {$complexQuery}

        Respond with JSON: {"sub_queries": ["query1", "query2", ...]}
        PROMPT;

        $decomposition = json_decode($llmCall($decompositionPrompt), true);

        $allResults = [];
        foreach ($decomposition['sub_queries'] as $subQuery) {
            $results = $this->vectorMemory->search($subQuery);
            $allResults[$subQuery] = $results;
        }

        return [
            'original_query' => $complexQuery,
            'sub_queries' => $decomposition['sub_queries'],
            'results' => $allResults,
        ];
    }

    /**
     * Search with reranking for better precision
     */
    public function searchWithRerank(
        string $query,
        int $initialK = 20,
        int $finalK = 5
    ): array {
        // Get more candidates initially
        $candidates = $this->vectorMemory->search($query, ['limit' => $initialK]);

        if (!$this->reranker) {
            return array_slice($candidates, 0, $finalK);
        }

        // Rerank using cross-encoder or LLM
        return $this->reranker->rerank($query, $candidates, $finalK);
    }

    /**
     * BM25 keyword search (using database full-text search)
     */
    protected function bm25Search(string $query, array $options = []): array
    {
        $limit = $options['limit'] ?? 10;
        $namespace = $options['namespace'] ?? 'default';
        $agentName = $options['agent_name'] ?? null;

        return VectorMemory::query()
            ->when($agentName, fn($q) => $q->where('agent_name', $agentName))
            ->where('namespace', $namespace)
            ->whereRaw("to_tsvector('english', content) @@ plainto_tsquery('english', ?)", [$query])
            ->orderByRaw("ts_rank(to_tsvector('english', content), plainto_tsquery('english', ?)) DESC", [$query])
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'content' => $r->content,
                'metadata' => $r->metadata,
                'score' => $r->rank ?? 0,
                'source' => 'bm25',
            ])
            ->toArray();
    }

    /**
     * Reciprocal Rank Fusion for combining search results
     */
    protected function reciprocalRankFusion(
        array $vectorResults,
        array $bm25Results,
        int $limit,
        int $k = 60
    ): array {
        $scores = [];

        // Score vector results
        foreach ($vectorResults as $rank => $result) {
            $id = md5($result['content']);
            $scores[$id] = ($scores[$id] ?? 0) + 1 / ($k + $rank + 1);
            $scores[$id . '_data'] = $result;
        }

        // Score BM25 results
        foreach ($bm25Results as $rank => $result) {
            $id = md5($result['content']);
            $scores[$id] = ($scores[$id] ?? 0) + 1 / ($k + $rank + 1);
            $scores[$id . '_data'] = $result;
        }

        // Sort by fused score
        $dataKeys = array_filter(array_keys($scores), fn($k) => !str_ends_with($k, '_data'));
        usort($dataKeys, fn($a, $b) => $scores[$b] <=> $scores[$a]);

        // Return top results
        $results = [];
        foreach (array_slice($dataKeys, 0, $limit) as $id) {
            $results[] = array_merge($scores[$id . '_data'], [
                'fused_score' => $scores[$id],
            ]);
        }

        return $results;
    }
}
```

### Reranker Interface

```php
// src/Contracts/RerankerInterface.php
interface RerankerInterface
{
    public function rerank(string $query, array $documents, int $topK): array;
}

// src/Services/Rerankers/CohereReranker.php
class CohereReranker implements RerankerInterface
{
    public function rerank(string $query, array $documents, int $topK): array
    {
        $response = Http::withToken(config('services.cohere.key'))
            ->post('https://api.cohere.ai/v1/rerank', [
                'model' => 'rerank-english-v3.0',
                'query' => $query,
                'documents' => array_column($documents, 'content'),
                'top_n' => $topK,
            ]);

        $reranked = [];
        foreach ($response->json('results') as $result) {
            $reranked[] = array_merge($documents[$result['index']], [
                'rerank_score' => $result['relevance_score'],
            ]);
        }

        return $reranked;
    }
}

// src/Services/Rerankers/LlmReranker.php
class LlmReranker implements RerankerInterface
{
    public function rerank(string $query, array $documents, int $topK): array
    {
        // Use LLM to score relevance
        // ...
    }
}
```

---

## 9. Streaming Enhancements

### What You Have
- Streaming support via Prism generators
- SSE format in API controllers
- Basic event accumulation

### What's Missing
**Granular streaming events** like LangGraph provides:

| Event Type | Vizra ADK | LangGraph |
|------------|-----------|-----------|
| Token stream | ✅ | ✅ |
| Tool call start/end | ❌ | ✅ |
| State updates | ❌ | ✅ |
| Node transitions | ❌ | ✅ |
| Thinking tokens | ✅ | ✅ |

### Recommendation

```php
// src/Streaming/StreamEvent.php
class StreamEvent implements JsonSerializable
{
    public function __construct(
        public string $type,
        public mixed $data,
        public ?string $spanId = null,
        public ?float $timestamp = null,
    ) {
        $this->timestamp ??= microtime(true);
    }

    public static function token(string $content, ?string $spanId = null): static
    {
        return new static('token', ['content' => $content], $spanId);
    }

    public static function toolStart(string $name, array $args, ?string $spanId = null): static
    {
        return new static('tool_start', [
            'name' => $name,
            'arguments' => $args,
        ], $spanId);
    }

    public static function toolEnd(string $name, string $result, ?string $spanId = null): static
    {
        return new static('tool_end', [
            'name' => $name,
            'result' => $result,
        ], $spanId);
    }

    public static function stateUpdate(string $key, mixed $value): static
    {
        return new static('state_update', ['key' => $key, 'value' => $value]);
    }

    public static function workflowStep(string $step, string $status): static
    {
        return new static('workflow_step', ['step' => $step, 'status' => $status]);
    }

    public static function thinking(string $content): static
    {
        return new static('thinking', ['content' => $content]);
    }

    public static function agentSwitch(string $from, string $to): static
    {
        return new static('agent_switch', ['from' => $from, 'to' => $to]);
    }

    public static function error(string $message, ?string $code = null): static
    {
        return new static('error', ['message' => $message, 'code' => $code]);
    }

    public static function done(?array $metadata = null): static
    {
        return new static('done', $metadata ?? []);
    }

    public function toSse(): string
    {
        return "event: {$this->type}\ndata: " . json_encode($this) . "\n\n";
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'data' => $this->data,
            'span_id' => $this->spanId,
            'timestamp' => $this->timestamp,
        ];
    }
}

// src/Streaming/StreamingExecutor.php
class StreamingExecutor
{
    public function executeStreaming(
        BaseLlmAgent $agent,
        mixed $input,
        AgentContext $context
    ): Generator {
        $spanId = $context->getState('span_id');

        yield StreamEvent::stateUpdate('status', 'starting');

        try {
            foreach ($agent->executeStreaming($input, $context) as $chunk) {
                if ($chunk instanceof ToolCallChunk) {
                    if ($chunk->isStart) {
                        yield StreamEvent::toolStart(
                            $chunk->name,
                            $chunk->arguments,
                            $spanId
                        );
                    } elseif ($chunk->isEnd) {
                        yield StreamEvent::toolEnd(
                            $chunk->name,
                            $chunk->result,
                            $spanId
                        );
                    }
                } elseif ($chunk instanceof ThinkingChunk) {
                    yield StreamEvent::thinking($chunk->content);
                } elseif ($chunk instanceof TextChunk) {
                    yield StreamEvent::token($chunk->content, $spanId);
                } elseif ($chunk instanceof StateUpdateChunk) {
                    yield StreamEvent::stateUpdate($chunk->key, $chunk->value);
                }
            }

            yield StreamEvent::done([
                'usage' => $context->getState('usage'),
            ]);

        } catch (Throwable $e) {
            yield StreamEvent::error($e->getMessage());
        }
    }
}
```

### Controller Integration

```php
// src/Http/Controllers/AgentController.php

public function stream(Request $request, string $agentName)
{
    $agent = $this->agentRegistry->get($agentName);
    $context = $this->buildContext($request);
    $executor = new StreamingExecutor();

    return response()->stream(function () use ($executor, $agent, $request, $context) {
        foreach ($executor->executeStreaming($agent, $request->input('message'), $context) as $event) {
            echo $event->toSse();
            ob_flush();
            flush();
        }
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'Connection' => 'keep-alive',
        'X-Accel-Buffering' => 'no',
    ]);
}
```

---

## 10. Cost Tracking & Budget Controls

### What You Have
- Token usage tracked in `Tracer` (`getUsageForSession()`)
- Placeholder cost values in `AnalyticsService`
- No budget enforcement

### What's Missing
**Active cost management** and **budget limits**

### Recommendation

```php
// src/Services/CostManager.php
class CostManager
{
    protected array $pricing = [
        // OpenAI (per 1M tokens)
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4-turbo' => ['input' => 10.00, 'output' => 30.00],
        'o1' => ['input' => 15.00, 'output' => 60.00],
        'o1-mini' => ['input' => 3.00, 'output' => 12.00],

        // Anthropic (per 1M tokens)
        'claude-3.5-sonnet' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-opus' => ['input' => 15.00, 'output' => 75.00],
        'claude-3-haiku' => ['input' => 0.25, 'output' => 1.25],

        // Google (per 1M tokens)
        'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],

        // Embeddings (per 1M tokens)
        'text-embedding-3-small' => ['input' => 0.02, 'output' => 0],
        'text-embedding-3-large' => ['input' => 0.13, 'output' => 0],

        // Default fallback
        'default' => ['input' => 1.00, 'output' => 2.00],
    ];

    public function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $rate = $this->pricing[$model] ?? $this->pricing['default'];

        return (($inputTokens / 1_000_000) * $rate['input']) +
               (($outputTokens / 1_000_000) * $rate['output']);
    }

    public function trackUsage(
        string $model,
        int $inputTokens,
        int $outputTokens,
        AgentContext $context
    ): void {
        $cost = $this->calculateCost($model, $inputTokens, $outputTokens);

        UsageRecord::create([
            'user_id' => $context->getState('user_id'),
            'session_id' => $context->getSessionId(),
            'agent_name' => $context->getState('agent_name'),
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => $cost,
        ]);

        // Update running totals
        $this->updateUserSpend($context->getState('user_id'), $cost);
    }

    public function checkBudget(AgentContext $context): BudgetCheck
    {
        $userId = $context->getState('user_id');
        $budget = $this->getUserBudget($userId);

        if (!$budget) {
            return BudgetCheck::unlimited();
        }

        $spent = $this->getUserSpend($userId, $budget->period_start);
        $remaining = $budget->limit - $spent;

        if ($remaining <= 0) {
            return BudgetCheck::exceeded($budget->limit, $spent);
        }

        if ($remaining < ($budget->limit * 0.1)) {
            return BudgetCheck::warning($budget->limit, $spent, $remaining);
        }

        return BudgetCheck::ok($budget->limit, $spent, $remaining);
    }

    public function enforceLimit(AgentContext $context): void
    {
        $check = $this->checkBudget($context);

        if ($check->exceeded) {
            throw new BudgetExceededException(
                "Budget exceeded. Limit: \${$check->limit}, Spent: \${$check->spent}"
            );
        }
    }

    public function getUserSpend(int $userId, ?Carbon $since = null): float
    {
        return UsageRecord::where('user_id', $userId)
            ->when($since, fn($q) => $q->where('created_at', '>=', $since))
            ->sum('cost');
    }

    public function getSessionCost(string $sessionId): float
    {
        return UsageRecord::where('session_id', $sessionId)->sum('cost');
    }

    public function getAgentCostBreakdown(string $agentName, Carbon $since): array
    {
        return UsageRecord::where('agent_name', $agentName)
            ->where('created_at', '>=', $since)
            ->selectRaw('model, SUM(input_tokens) as input_tokens, SUM(output_tokens) as output_tokens, SUM(cost) as total_cost')
            ->groupBy('model')
            ->get()
            ->toArray();
    }

    public function setUserBudget(int $userId, float $limit, string $period = 'monthly'): void
    {
        UserBudget::updateOrCreate(
            ['user_id' => $userId],
            [
                'limit' => $limit,
                'period' => $period,
                'period_start' => match($period) {
                    'daily' => now()->startOfDay(),
                    'weekly' => now()->startOfWeek(),
                    'monthly' => now()->startOfMonth(),
                    default => now()->startOfMonth(),
                },
            ]
        );
    }
}

// src/Services/BudgetCheck.php
class BudgetCheck
{
    public function __construct(
        public bool $exceeded,
        public bool $warning,
        public ?float $limit,
        public float $spent,
        public ?float $remaining,
    ) {}

    public static function unlimited(): static
    {
        return new static(false, false, null, 0, null);
    }

    public static function exceeded(float $limit, float $spent): static
    {
        return new static(true, false, $limit, $spent, 0);
    }

    public static function warning(float $limit, float $spent, float $remaining): static
    {
        return new static(false, true, $limit, $spent, $remaining);
    }

    public static function ok(float $limit, float $spent, float $remaining): static
    {
        return new static(false, false, $limit, $spent, $remaining);
    }
}
```

### Integration

```php
// In BaseLlmAgent, after LLM call
protected function afterLlmCall(array $usage, AgentContext $context): void
{
    app(CostManager::class)->trackUsage(
        $this->model,
        $usage['input_tokens'],
        $usage['output_tokens'],
        $context
    );
}

// At start of execution
public function execute(mixed $input, AgentContext $context): string
{
    // Check budget before expensive operations
    app(CostManager::class)->enforceLimit($context);

    // ... rest of execution
}
```

### Dashboard Integration

```php
// Add to AnalyticsService
public function getCostAnalytics(): array
{
    $costManager = app(CostManager::class);

    return [
        'today' => UsageRecord::whereDate('created_at', today())->sum('cost'),
        'this_week' => UsageRecord::where('created_at', '>=', now()->startOfWeek())->sum('cost'),
        'this_month' => UsageRecord::where('created_at', '>=', now()->startOfMonth())->sum('cost'),
        'by_model' => UsageRecord::where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('model, SUM(cost) as cost')
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get(),
        'by_agent' => UsageRecord::where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('agent_name, SUM(cost) as cost')
            ->groupBy('agent_name')
            ->orderByDesc('cost')
            ->get(),
    ];
}
```

---

## Summary: Priority Recommendations

Based on what would provide the most value to your Laravel-based framework:

### High Priority (Core Agent Capabilities)

| Feature | Effort | Impact | Section |
|---------|--------|--------|---------|
| **Human-in-the-Loop** | Medium | High | [Section 2](#2-human-in-the-loop-hitl-native-support) |
| **Guardrails Layer** | Medium | High | [Section 3](#3-guardrails--safety-layer) |
| **Tool Authorization** | Low | High | [Section 5](#5-tool-permission--authorization-framework) |
| **Checkpointing** | Medium | High | [Section 1](#1-checkpointing--durable-execution) |

### Medium Priority (Advanced Patterns)

| Feature | Effort | Impact | Section |
|---------|--------|--------|---------|
| **Planning Agents** | Medium | Medium | [Section 4](#4-planning--reasoning-agents) |
| **Advanced RAG** | Medium | Medium | [Section 8](#8-advanced-rag-patterns) |
| **Cost Controls** | Low | Medium | [Section 10](#10-cost-tracking--budget-controls) |
| **Role-Based Crews** | Medium | Medium | [Section 7](#7-role-based-agent-collaboration-crewai-style) |

### Lower Priority (Observability & Standards)

| Feature | Effort | Impact | Section |
|---------|--------|--------|---------|
| **OpenTelemetry Export** | Low | Medium | [Section 6](#6-opentelemetry-integration) |
| **Granular Streaming** | Low | Low | [Section 9](#9-streaming-enhancements) |

---

## Sources

### Frameworks Researched
- [LangGraph Multi-Agent Orchestration Guide](https://latenode.com/blog/ai-frameworks-technical-infrastructure/langgraph-multi-agent-orchestration/langgraph-multi-agent-orchestration-complete-framework-guide-architecture-analysis-2025)
- [Building LangGraph from First Principles](https://www.blog.langchain.com/building-langgraph/)
- [CrewAI Framework 2025 Review](https://latenode.com/blog/ai-frameworks-technical-infrastructure/crewai-framework/crewai-framework-2025-complete-review-of-the-open-source-multi-agent-ai-platform)
- [CrewAI Documentation](https://docs.crewai.com/en/introduction)
- [Microsoft AutoGen GitHub](https://github.com/microsoft/autogen)
- [LlamaIndex Agentic RAG](https://www.llamaindex.ai/blog/agentic-rag-with-llamaindex-2721b8a49ff6)
- [Phidata Reasoning Documentation](https://docs.phidata.com/agents/reasoning)

### Safety & Guardrails
- [OpenAI Guardrails Python](https://openai.github.io/openai-guardrails-python/)
- [OpenAI Agent Safety Guide](https://platform.openai.com/docs/guides/agent-builder-safety)
- [LangChain Guardrails](https://docs.langchain.com/oss/python/langchain/guardrails)
- [Patronus AI Guardrails Tutorial](https://www.patronus.ai/ai-reliability/ai-guardrails)

### Human-in-the-Loop
- [LangGraph Interrupts Documentation](https://docs.langchain.com/oss/python/langgraph/interrupts)
- [Permit.io HITL Best Practices](https://www.permit.io/blog/human-in-the-loop-for-ai-agents-best-practices-frameworks-use-cases-and-demo)
- [LangGraph Static Breakpoints](https://langchain-ai.github.io/langgraph/cloud/how-tos/human_in_the_loop_breakpoint/)

### Authorization & Security
- [Permit.io AI Access Control](https://www.permit.io/ai-access-control)
- [Cerbos Permission Management for AI Agents](https://www.cerbos.dev/blog/permission-management-for-ai-agents)
- [Oso AI Agent Permissions](https://www.osohq.com/learn/ai-agent-permissions-delegated-access)

### Observability
- [OpenTelemetry AI Agent Observability](https://opentelemetry.io/blog/2025/ai-agent-observability/)
- [Datadog OTel LLM Support](https://www.datadoghq.com/blog/llm-otel-semantic-convention/)
- [Agenta OTel Guide](https://agenta.ai/blog/the-ai-engineer-s-guide-to-llm-observability-with-opentelemetry)
