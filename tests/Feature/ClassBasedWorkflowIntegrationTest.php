<?php

namespace Vizra\VizraADK\Tests\Feature;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Facades\Workflow;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tests\TestCase;

// Define test agents for the integration test
class DataCollectorAgent extends BaseLlmAgent
{
    protected string $name = 'data_collector';

    protected string $description = 'Collects data from various sources';

    protected string $instructions = 'You collect data.';
}

class DataProcessorAgent extends BaseLlmAgent
{
    protected string $name = 'data_processor';

    protected string $description = 'Processes collected data';

    protected string $instructions = 'You process data.';
}

class ReportGeneratorAgent extends BaseLlmAgent
{
    protected string $name = 'report_generator';

    protected string $description = 'Generates reports from processed data';

    protected string $instructions = 'You generate reports.';
}

class ClassBasedWorkflowIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register the test agents
        Agent::build(DataCollectorAgent::class)->register();
        Agent::build(DataProcessorAgent::class)->register();
        Agent::build(ReportGeneratorAgent::class)->register();
    }

    public function test_sequential_workflow_with_class_based_agents()
    {
        // Mock the Agent facade to simulate agent execution
        Agent::shouldReceive('run')
            ->with('data_collector', 'Initial data', \Mockery::type('string'))
            ->once()
            ->andReturn('Collected data');

        Agent::shouldReceive('run')
            ->with('data_processor', 'Collected data', \Mockery::type('string'))
            ->once()
            ->andReturn('Processed data');

        Agent::shouldReceive('run')
            ->with('report_generator', 'Processed data', \Mockery::type('string'))
            ->once()
            ->andReturn('Final report');

        // Create a workflow using class names
        $workflow = Workflow::sequential()
            ->then(DataCollectorAgent::class)
            ->then(DataProcessorAgent::class)
            ->then(ReportGeneratorAgent::class);

        // Execute the workflow
        $context = new AgentContext('test-workflow-'.uniqid());
        $result = $workflow->execute('Initial data', $context);

        // Assert the result
        $this->assertIsArray($result);
        $this->assertEquals('Final report', $result['final_result']);
        $this->assertEquals('sequential', $result['workflow_type']);
        $this->assertArrayHasKey('step_results', $result);
        $this->assertArrayHasKey(DataCollectorAgent::class, $result['step_results']);
        $this->assertArrayHasKey(DataProcessorAgent::class, $result['step_results']);
        $this->assertArrayHasKey(ReportGeneratorAgent::class, $result['step_results']);
    }

    public function test_can_access_agent_by_class()
    {
        // Get agent instance by class
        $agent = Agent::byClass(DataCollectorAgent::class);

        $this->assertInstanceOf(DataCollectorAgent::class, $agent);
        $this->assertEquals('data_collector', $agent->getName());
    }

    public function test_can_run_agent_by_class()
    {
        // Mock the agent execution - the AgentManager will resolve the class internally
        Agent::shouldReceive('run')
            ->withArgs(function ($agentNameOrClass, $input, $sessionId = null) {
                // The AgentManager's run method accepts either a name or class
                return $agentNameOrClass === DataCollectorAgent::class
                    && $input === 'Test input';
            })
            ->once()
            ->andReturn('Test output');

        // Run agent using class name
        $result = Agent::run(DataCollectorAgent::class, 'Test input');

        $this->assertEquals('Test output', $result);
    }

    public function test_parallel_workflow_with_class_based_agents()
    {
        // Mock parallel agent execution
        Agent::shouldReceive('run')
            ->with('data_collector', 'Input', \Mockery::type('string'))
            ->once()
            ->andReturn('Data 1');

        Agent::shouldReceive('run')
            ->with('data_processor', 'Input', \Mockery::type('string'))
            ->once()
            ->andReturn('Data 2');

        // Create parallel workflow
        $workflow = Workflow::parallel([
            DataCollectorAgent::class,
            DataProcessorAgent::class,
        ]);

        $context = new AgentContext('test-parallel-'.uniqid());
        $result = $workflow->execute('Input', $context);

        $this->assertIsArray($result);
        $this->assertEquals('parallel', $result['workflow_type']);
        $this->assertArrayHasKey(DataCollectorAgent::class, $result['results']);
        $this->assertArrayHasKey(DataProcessorAgent::class, $result['results']);
    }

    public function test_workflow_with_dynamic_parameters_using_classes()
    {
        // Mock agent execution
        Agent::shouldReceive('run')
            ->with('data_collector', 'Start', \Mockery::type('string'))
            ->once()
            ->andReturn(['data' => 'collected']);

        Agent::shouldReceive('run')
            ->with('data_processor', ['source' => 'collected'], \Mockery::type('string'))
            ->once()
            ->andReturn('Processed result');

        // Create workflow with dynamic parameters
        $workflow = Workflow::sequential()
            ->then(DataCollectorAgent::class)
            ->then(DataProcessorAgent::class, function ($input, $results) {
                return ['source' => $results[DataCollectorAgent::class]['data']];
            });

        $context = new AgentContext('test-dynamic-'.uniqid());
        $result = $workflow->execute('Start', $context);

        $this->assertEquals('Processed result', $result['final_result']);
    }

    public function test_workflow_step_results_use_class_names_as_keys()
    {
        // Mock agent execution
        Agent::shouldReceive('run')
            ->with('data_collector', 'Input', \Mockery::type('string'))
            ->once()
            ->andReturn('Step 1 result');

        Agent::shouldReceive('run')
            ->with('data_processor', 'Step 1 result', \Mockery::type('string'))
            ->once()
            ->andReturn('Step 2 result');

        // Create and execute workflow
        $workflow = Workflow::sequential()
            ->then(DataCollectorAgent::class)
            ->then(DataProcessorAgent::class);

        $context = new AgentContext('test-step-results-'.uniqid());
        $result = $workflow->execute('Input', $context);

        // Get step results using class names
        $collectorResult = $workflow->getStepResult(DataCollectorAgent::class);
        $processorResult = $workflow->getStepResult(DataProcessorAgent::class);

        $this->assertEquals('Step 1 result', $collectorResult);
        $this->assertEquals('Step 2 result', $processorResult);
    }

    public function test_workflow_from_array_with_class_names()
    {
        // Mock agent execution
        Agent::shouldReceive('run')
            ->with('data_collector', 'Collect this', \Mockery::type('string'))
            ->once()
            ->andReturn('Collected');

        Agent::shouldReceive('run')
            ->with('data_processor', 'Collected', \Mockery::type('string'))
            ->once()
            ->andReturn('Processed');

        // Define workflow as array with class names
        $definition = [
            'type' => 'sequential',
            'steps' => [
                [
                    'agent' => DataCollectorAgent::class,
                    'params' => 'Collect this',
                ],
                [
                    'agent' => DataProcessorAgent::class,
                ],
            ],
        ];

        $workflow = Workflow::fromArray($definition);
        $context = new AgentContext('test-from-array-'.uniqid());
        $result = $workflow->execute('Start', $context);

        $this->assertEquals('Processed', $result['final_result']);
    }

    protected function tearDown(): void
    {
        // Clean up
        parent::tearDown();
    }
}
