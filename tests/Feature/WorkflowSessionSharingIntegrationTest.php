<?php

namespace Vizra\VizraADK\Tests\Feature;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Agents\SequentialWorkflow;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Facades\Workflow;
use Vizra\VizraADK\Models\AgentSession;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
// Test agents for workflow
class WorkflowFirstAgent extends BaseLlmAgent
{
    protected string $name = 'workflow_first';
    protected string $description = 'First agent in workflow';
    protected string $instructions = 'You are the first agent in a workflow test.';
    
    public function run($input, AgentContext $context): mixed
    {
        // Simulate agent behavior without actual LLM call
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input ?: '']);
        
        $response = 'First agent response';
        
        $context->addMessage([
            'role' => 'assistant',
            'content' => $response,
        ]);
        
        return $response;
    }
}

class WorkflowSecondAgent extends BaseLlmAgent
{
    protected string $name = 'workflow_second';
    protected string $description = 'Second agent in workflow';
    protected string $instructions = 'You are the second agent in a workflow test.';
    
    public function run($input, AgentContext $context): mixed
    {
        // Simulate agent behavior without actual LLM call
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input ?: '']);
        
        $response = 'Second agent response';
        
        $context->addMessage([
            'role' => 'assistant',
            'content' => $response,
        ]);
        
        return $response;
    }
}

class WorkflowThirdAgent extends BaseLlmAgent
{
    protected string $name = 'workflow_third';
    protected string $description = 'Third agent in workflow';
    protected string $instructions = 'You are the third agent in a workflow test.';
    
    public function run($input, AgentContext $context): mixed
    {
        // Simulate agent behavior without actual LLM call
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input ?: '']);
        
        $response = 'Third agent response';
        
        $context->addMessage([
            'role' => 'assistant',
            'content' => $response,
        ]);
        
        return $response;
    }
}

class WorkflowSessionSharingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        
        // Register test agents
        Agent::build(WorkflowFirstAgent::class)->register();
        Agent::build(WorkflowSecondAgent::class)->register();
        Agent::build(WorkflowThirdAgent::class)->register();
    }

    public function test_sequential_workflow_creates_multiple_sessions_with_same_id()
    {
        // Create context with known session ID
        $sessionId = 'test-workflow-' . uniqid();
        $context = new AgentContext($sessionId);
        
        // Create and execute workflow
        $workflow = Workflow::sequential()
            ->then(WorkflowFirstAgent::class)
            ->then(WorkflowSecondAgent::class)
            ->then(WorkflowThirdAgent::class);
            
        $result = $workflow->execute('Initial input', $context);
        
        // Verify workflow executed successfully
        $this->assertEquals('Third agent response', $result['final_result']);
        
        // Verify multiple agent sessions exist with the same session_id
        $sessions = AgentSession::where('session_id', $sessionId)->get();
        
        // Should have 3 sessions, one for each agent
        $this->assertCount(3, $sessions);
        
        // Verify each agent has its own session record
        $agentNames = $sessions->pluck('agent_name')->sort()->values()->toArray();
        $this->assertEquals(['workflow_first', 'workflow_second', 'workflow_third'], $agentNames);
        
        // Verify all sessions share the same session_id
        $sessionIds = $sessions->pluck('session_id')->unique();
        $this->assertCount(1, $sessionIds);
        $this->assertEquals($sessionId, $sessionIds->first());
    }

    public function test_workflow_with_explicit_session_id()
    {
        $sessionId = 'test-workflow-session-' . uniqid();
        
        // Create workflow with explicit context
        $context = new AgentContext($sessionId);
        
        $workflow = Workflow::sequential()
            ->then(WorkflowFirstAgent::class)
            ->then(WorkflowSecondAgent::class);
            
        $result = $workflow->execute('Test input', $context);
        
        // Verify sessions were created with our explicit session ID
        $sessions = AgentSession::where('session_id', $sessionId)->get();
        
        $this->assertCount(2, $sessions);
        
        // Verify agent names
        $agentNames = $sessions->pluck('agent_name')->sort()->values()->toArray();
        $this->assertEquals(['workflow_first', 'workflow_second'], $agentNames);
    }

    public function test_no_unique_constraint_violation_in_real_workflow()
    {
        // This test ensures the database constraint fix actually works
        
        $sessionId = 'constraint-test-' . uniqid();
        
        // Execute workflow - this should not throw a constraint violation
        $workflow = Workflow::sequential()
            ->then(WorkflowFirstAgent::class)
            ->then(WorkflowSecondAgent::class)
            ->then(WorkflowThirdAgent::class);
            
        $result = $workflow->execute('Test', new AgentContext($sessionId));
        
        // If we got here without an exception, the constraint is working
        $this->assertNotNull($result);
        
        // Verify all agents have their sessions
        $firstSession = AgentSession::where('session_id', $sessionId)
            ->where('agent_name', 'workflow_first')
            ->first();
            
        $secondSession = AgentSession::where('session_id', $sessionId)
            ->where('agent_name', 'workflow_second')
            ->first();
            
        $thirdSession = AgentSession::where('session_id', $sessionId)
            ->where('agent_name', 'workflow_third')
            ->first();
        
        $this->assertNotNull($firstSession);
        $this->assertNotNull($secondSession);
        $this->assertNotNull($thirdSession);
    }

    public function test_database_constraint_allows_same_session_different_agents()
    {
        // Direct database test to ensure constraint works
        $sessionId = 'db-constraint-test-' . uniqid();
        
        // Create first agent session
        $session1 = AgentSession::create([
            'session_id' => $sessionId,
            'agent_name' => 'agent_one',
            'state_data' => ['test' => 'data1'],
        ]);
        
        // Create second agent session with same session_id
        // This should succeed with the composite unique constraint
        $session2 = AgentSession::create([
            'session_id' => $sessionId,
            'agent_name' => 'agent_two',
            'state_data' => ['test' => 'data2'],
        ]);
        
        $this->assertNotNull($session1);
        $this->assertNotNull($session2);
        $this->assertEquals($sessionId, $session1->session_id);
        $this->assertEquals($sessionId, $session2->session_id);
        
        // Verify we can retrieve both
        $sessions = AgentSession::where('session_id', $sessionId)->get();
        $this->assertCount(2, $sessions);
    }

    public function test_duplicate_session_agent_combination_updates_not_duplicates()
    {
        $sessionId = 'update-test-' . uniqid();
        
        // Create initial session
        $session1 = AgentSession::create([
            'session_id' => $sessionId,
            'agent_name' => 'test_agent',
            'state_data' => ['version' => 1],
        ]);
        
        $originalId = $session1->id;
        
        // Try to create duplicate - should update instead
        $session2 = AgentSession::updateOrCreate(
            [
                'session_id' => $sessionId,
                'agent_name' => 'test_agent',
            ],
            [
                'state_data' => ['version' => 2],
            ]
        );
        
        // Should be same record ID
        $this->assertEquals($originalId, $session2->id);
        $this->assertEquals(['version' => 2], $session2->state_data);
        
        // Only one record should exist
        $count = AgentSession::where('session_id', $sessionId)->count();
        $this->assertEquals(1, $count);
    }
}