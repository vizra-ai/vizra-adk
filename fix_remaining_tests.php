<?php

/**
 * Script to identify and fix remaining test issues
 */

// Check for remaining issues that could cause test failures

echo "=== Laravel AI ADK Test Fix Analysis ===\n";

// 1. Check for remaining assertStringContains issues
$testFiles = glob(__DIR__ . '/tests/**/*Test.php');

foreach ($testFiles as $file) {
    $content = file_get_contents($file);
    
    if (strpos($content, 'assertStringContains(') !== false && strpos($content, 'assertStringContainsString(') === false) {
        echo "Found assertStringContains issue in: " . basename($file) . "\n";
    }
    
    if (strpos($content, 'assertStringNotContains(') !== false && strpos($content, 'assertStringNotContainsString(') === false) {
        echo "Found assertStringNotContains issue in: " . basename($file) . "\n";
    }
}

// 2. Check for old namespace references
$allFiles = glob(__DIR__ . '/{src,tests,config}/**/*.php', GLOB_BRACE);

foreach ($allFiles as $file) {
    $content = file_get_contents($file);
    
    if (strpos($content, 'LaravelAgentADK') !== false) {
        echo "Found old namespace reference in: " . str_replace(__DIR__ . '/', '', $file) . "\n";
    }
}

// 3. Check for missing setLaravel calls in command tests
$commandTestFiles = glob(__DIR__ . '/tests/Unit/Console/Commands/*Test.php');

foreach ($commandTestFiles as $file) {
    $content = file_get_contents($file);
    
    if (strpos($content, 'new Make') !== false && strpos($content, 'setLaravel') === false) {
        echo "Missing setLaravel() call in: " . basename($file) . "\n";
    }
}

// 4. Check for AgentContext constructor issues
$workflowTestFiles = glob(__DIR__ . '/tests/Unit/Agents/*WorkflowTest.php');

foreach ($workflowTestFiles as $file) {
    $content = file_get_contents($file);
    
    if (strpos($content, 'new AgentContext()') !== false) {
        echo "AgentContext constructor issue in: " . basename($file) . "\n";
    }
}

echo "\n=== Analysis Complete ===\n";

// Now try to run a simple syntax check
echo "\n=== Syntax Check ===\n";
exec('find ' . __DIR__ . ' -name "*.php" -exec php -l {} \; 2>&1', $output, $returnCode);

$syntaxErrors = array_filter($output, function($line) {
    return strpos($line, 'Parse error') !== false || strpos($line, 'Fatal error') !== false;
});

if (!empty($syntaxErrors)) {
    echo "Syntax errors found:\n";
    foreach ($syntaxErrors as $error) {
        echo $error . "\n";
    }
} else {
    echo "No syntax errors found.\n";
}