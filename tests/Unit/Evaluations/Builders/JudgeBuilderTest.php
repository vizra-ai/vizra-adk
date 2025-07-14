<?php

use Vizra\VizraADK\Evaluations\Builders\JudgeBuilder;
use Vizra\VizraADK\Evaluations\BaseEvaluation;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\Facades\Agent;
use Illuminate\Support\Facades\App;

// Test agent classes for use in tests
class TestPassFailJudgeAgent {
    protected string $name = 'test_pass_fail_judge';
}

class TestQualityJudgeAgent {
    protected string $name = 'test_quality_judge';
}

class TestComprehensiveJudgeAgent {
    protected string $name = 'test_comprehensive_judge';
}

beforeEach(function () {
    // Create a mock evaluation class
    $this->evaluation = Mockery::mock(BaseEvaluation::class)->makePartial();
    $this->evaluation->shouldReceive('recordAssertion')->andReturn([
        'assertion_method' => 'judge()->expectPass',
        'status' => 'pass',
        'message' => 'Test passed',
        'expected' => 'pass',
        'actual' => 'pass'
    ]);
    
    // Create the JudgeBuilder instance
    $this->judgeBuilder = new JudgeBuilder('Test response content', $this->evaluation);
});

it('can be instantiated with response and evaluation', function () {
    expect($this->judgeBuilder)->toBeInstanceOf(JudgeBuilder::class);
});

it('can specify judge agent using fluent interface', function () {
    $result = $this->judgeBuilder->using(TestPassFailJudgeAgent::class);
    
    expect($result)->toBe($this->judgeBuilder); // Should return self for chaining
});

it('throws exception when expectPass is called without specifying agent', function () {
    expect(fn() => $this->judgeBuilder->expectPass())
        ->toThrow(InvalidArgumentException::class, 'No judge agent specified');
});

it('throws exception when expectMinimumScore is called without specifying agent', function () {
    expect(fn() => $this->judgeBuilder->expectMinimumScore(7.5))
        ->toThrow(InvalidArgumentException::class, 'No judge agent specified');
});

it('can handle pass/fail evaluation', function () {
    // Mock the Agent facade
    Agent::shouldReceive('run')
        ->once()
        ->andReturn(json_encode(['pass' => true, 'reasoning' => 'Good response']));
    
    $result = $this->judgeBuilder
        ->using(TestPassFailJudgeAgent::class)
        ->expectPass();
    
    expect($result)->toBeArray();
    expect($result['status'])->toBe('pass');
});

it('can handle quality score evaluation', function () {
    // Mock the Agent facade
    Agent::shouldReceive('run')
        ->once()
        ->andReturn(json_encode(['score' => 8.5, 'reasoning' => 'High quality response']));
    
    $result = $this->judgeBuilder
        ->using(TestQualityJudgeAgent::class)
        ->expectMinimumScore(7.0);
    
    expect($result)->toBeArray();
    expect($result['status'])->toBe('pass');
});

it('can handle multi-dimensional score evaluation', function () {
    // Mock the Agent facade
    Agent::shouldReceive('run')
        ->once()
        ->andReturn(json_encode([
            'scores' => [
                'accuracy' => 9,
                'helpfulness' => 8,
                'clarity' => 7
            ],
            'reasoning' => 'Well-balanced response'
        ]));
    
    $result = $this->judgeBuilder
        ->using(TestComprehensiveJudgeAgent::class)
        ->expectMinimumScore([
            'accuracy' => 8,
            'helpfulness' => 7,
            'clarity' => 7
        ]);
    
    expect($result)->toBeArray();
    expect($result['status'])->toBe('pass');
});

it('handles invalid score parameter types', function () {
    // The actual implementation catches the exception and returns an error result
    // So we need to mock the evaluation's recordAssertion differently
    $evaluation = Mockery::mock(BaseEvaluation::class)->makePartial();
    $builder = new JudgeBuilder('Test response', $evaluation);
    
    // Mock evaluation to expect the error recording
    $evaluation->shouldReceive('recordAssertion')
        ->once()
        ->andReturn([
            'assertion_method' => 'judge()->expectMinimumScore',
            'status' => 'fail',
            'message' => 'Judge evaluation failed: expectMinimumScore accepts either a number or an array of scores',
            'expected' => 'invalid',
            'actual' => 'error'
        ]);
    
    $builder->using(TestQualityJudgeAgent::class);
    
    // The actual implementation converts string to float, so we need a truly invalid type
    $invalidObject = new stdClass();
    
    $result = $builder->expectMinimumScore($invalidObject);
    
    expect($result['status'])->toBe('fail');
    expect($result['actual'])->toBe('error');
});

it('can parse pass/fail judgment from various response formats', function () {
    $builder = new JudgeBuilder('Test response', $this->evaluation);
    
    // Test valid JSON response
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('parsePassFailJudgment');
    $method->setAccessible(true);
    
    $result = $method->invoke($builder, '{"pass": true, "reasoning": "Good"}');
    expect($result['pass'])->toBeTrue();
    expect($result['reasoning'])->toBe('Good');
    
    // Test response with extra text
    $result = $method->invoke($builder, 'Here is my judgment: {"pass": false, "reasoning": "Bad"}');
    expect($result['pass'])->toBeFalse();
    expect($result['reasoning'])->toBe('Bad');
    
    // Test malformed response
    $result = $method->invoke($builder, 'Invalid response');
    expect($result['pass'])->toBeFalse();
    expect($result['reasoning'])->toBe('Could not parse judgment');
});

it('can parse quality scores from various response formats', function () {
    $builder = new JudgeBuilder('Test response', $this->evaluation);
    
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('parseQualityScore');
    $method->setAccessible(true);
    
    // Test valid JSON response
    $result = $method->invoke($builder, '{"score": 8.5}');
    expect($result)->toBe(8.5);
    
    // Test response with extra text
    $result = $method->invoke($builder, 'The score is: {"score": 7.2}');
    expect($result)->toBe(7.2);
    
    // Test fallback pattern
    $result = $method->invoke($builder, 'I give this a "score": 9.0 out of 10');
    expect($result)->toBe(9.0);
    
    // Test malformed response
    $result = $method->invoke($builder, 'Invalid response');
    expect($result)->toBe(0.0);
});

it('can parse multi-dimensional scores', function () {
    $builder = new JudgeBuilder('Test response', $this->evaluation);
    
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('parseMultiDimensionalScores');
    $method->setAccessible(true);
    
    // Test valid JSON response
    $json = '{"scores": {"accuracy": 9, "helpfulness": 8, "clarity": 7}}';
    $result = $method->invoke($builder, $json);
    expect($result)->toBeArray();
    expect($result['accuracy'])->toBe(9.0);
    expect($result['helpfulness'])->toBe(8.0);
    expect($result['clarity'])->toBe(7.0);
    
    // Test malformed response
    $result = $method->invoke($builder, 'Invalid response');
    expect($result)->toBe([]);
});

it('derives agent name from class when not registered', function () {
    $builder = new JudgeBuilder('Test response', $this->evaluation);
    
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('getAgentName');
    $method->setAccessible(true);
    
    // Set the agent class
    $property = $reflection->getProperty('agentClass');
    $property->setAccessible(true);
    $property->setValue($builder, TestPassFailJudgeAgent::class);
    
    // When agent is not in registry, it should derive name from class
    $result = $method->invoke($builder);
    expect($result)->toBe('test_pass_fail_judge');
});

it('gets agent name from class property when available', function () {
    $builder = new JudgeBuilder('Test response', $this->evaluation);
    
    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('getAgentName');
    $method->setAccessible(true);
    
    // Set the agent class to our test class that has a name property
    $property = $reflection->getProperty('agentClass');
    $property->setAccessible(true);
    $property->setValue($builder, TestQualityJudgeAgent::class);
    
    // It should derive the name from the class
    $result = $method->invoke($builder);
    expect($result)->toBe('test_quality_judge');
});

it('handles exceptions gracefully', function () {
    // Create a fresh mock for this test
    $evaluation = Mockery::mock(BaseEvaluation::class)->makePartial();
    $builder = new JudgeBuilder('Test response', $evaluation);
    
    // Mock Agent to throw exception
    Agent::shouldReceive('run')
        ->once()
        ->andThrow(new Exception('Agent error'));
    
    // Mock evaluation to record failure
    $evaluation->shouldReceive('recordAssertion')
        ->once()
        ->with(
            'judge()->expectPass',
            false,
            Mockery::pattern('/Judge evaluation failed:.*Agent error/'),
            'pass',
            'error'
        )
        ->andReturn([
            'assertion_method' => 'judge()->expectPass',
            'status' => 'fail',
            'message' => 'Judge evaluation failed: Agent error',
            'expected' => 'pass',
            'actual' => 'error'
        ]);
    
    $result = $builder
        ->using(TestPassFailJudgeAgent::class)
        ->expectPass();
    
    expect($result['status'])->toBe('fail');
    expect($result['actual'])->toBe('error');
});

it('handles failed minimum score comparisons', function () {
    // Mock the Agent facade to return a low score
    Agent::shouldReceive('run')
        ->once()
        ->andReturn(json_encode(['score' => 5.0, 'reasoning' => 'Average quality']));
    
    // Create a fresh mock for this test
    $evaluation = Mockery::mock(BaseEvaluation::class)->makePartial();
    $evaluation->shouldReceive('recordAssertion')
        ->once()
        ->with(
            'judge()->expectMinimumScore',
            false,
            Mockery::any(),
            '>= 7',
            5.0
        )
        ->andReturn([
            'assertion_method' => 'judge()->expectMinimumScore',
            'status' => 'fail',
            'message' => 'Quality score should meet minimum threshold Score: 5/7',
            'expected' => '>= 7',
            'actual' => 5.0
        ]);
    
    $builder = new JudgeBuilder('Test response', $evaluation);
    
    $result = $builder
        ->using(TestQualityJudgeAgent::class)
        ->expectMinimumScore(7.0);
    
    expect($result['status'])->toBe('fail');
    expect($result['actual'])->toBe(5.0);
});

afterEach(function () {
    Mockery::close();
});