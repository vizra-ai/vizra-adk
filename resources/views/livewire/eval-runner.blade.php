@push('scripts')
<style>
    /* Enhanced animations and transitions */
    .eval-card {
        transition: all 0.3s ease-in-out;
    }

    .eval-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
    }

    .hero-icon {
        transition: all 0.5s ease-in-out;
        animation: heroIconFloat 3s ease-in-out infinite;
    }

    @keyframes heroIconFloat {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-5px) rotate(5deg); }
    }

    .stat-card {
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease-in-out;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.6s ease-in-out;
    }

    .stat-card:hover::before {
        left: 100%;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px -8px rgba(0, 0, 0, 0.3);
    }

    .evaluation-item {
        position: relative;
        transition: all 0.3s ease-in-out;
        overflow: hidden;
    }

    .evaluation-item::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        background: linear-gradient(135deg, transparent 0%, rgba(59, 130, 246, 0.05) 100%);
        opacity: 0;
        transition: opacity 0.3s ease-in-out;
    }

    .evaluation-item:hover::after {
        opacity: 1;
    }

    .evaluation-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.3);
    }

    .gradient-text {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .progress-bar {
        transition: width 0.3s ease-in-out;
    }

    .pulse-animation {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: .5;
        }
    }

    .spinner {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }
        to {
            transform: rotate(360deg);
        }
    }

    .status-indicator {
        animation: statusPulse 2s ease-in-out infinite;
    }

    @keyframes statusPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.05); }
    }

    .results-card {
        backdrop-filter: blur(8px);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .results-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    /* Custom Scrollbar Styles */
    /* For Webkit browsers (Chrome, Safari, Edge) */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: rgba(30, 41, 59, 0.5); /* slate-800 with opacity */
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb {
        background: rgba(71, 85, 105, 0.8); /* slate-600 with opacity */
        border-radius: 4px;
        transition: background-color 0.2s ease;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: rgba(100, 116, 139, 0.9); /* slate-500 with opacity */
    }

    ::-webkit-scrollbar-thumb:active {
        background: rgba(148, 163, 184, 0.9); /* slate-400 with opacity */
    }

    /* For Firefox */
    * {
        scrollbar-width: thin;
        scrollbar-color: rgba(71, 85, 105, 0.8) rgba(30, 41, 59, 0.5);
    }

    /* Specific styling for the test results container */
    .max-h-96.overflow-y-auto::-webkit-scrollbar-track,
    .max-h-32.overflow-y-auto::-webkit-scrollbar-track {
        margin: 4px 0;
    }

    .success-glow {
        box-shadow: 0 0 20px rgba(34, 197, 94, 0.3);
    }

    .error-glow {
        box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
    }

    .result-item {
        animation: slideInResult 0.5s ease-out;
    }

    @keyframes slideInResult {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Listen for evaluation progress updates
    window.addEventListener('evaluation-progress', event => {
        console.log('Evaluation progress:', event.detail);
        
        // Update real-time stats if they exist
        const passedEl = document.getElementById('passed-count');
        const failedEl = document.getElementById('failed-count');
        const summaryPassedEl = document.getElementById('summary-passed-count');
        const summaryFailedEl = document.getElementById('summary-failed-count');
        const summaryPassRateEl = document.getElementById('summary-pass-rate');
        const passRateTextEl = document.getElementById('pass-rate-text');
        const passRateBarEl = document.getElementById('pass-rate-bar');
        
        if (passedEl && event.detail.passed !== undefined) {
            passedEl.textContent = event.detail.passed;
        }
        if (failedEl && event.detail.failed !== undefined) {
            failedEl.textContent = event.detail.failed;
        }
        if (summaryPassedEl && event.detail.passed !== undefined) {
            summaryPassedEl.textContent = event.detail.passed;
        }
        if (summaryFailedEl && event.detail.failed !== undefined) {
            summaryFailedEl.textContent = event.detail.failed;
        }
        
        // Update pass rate
        if (event.detail.total_rows && event.detail.total_rows > 0) {
            const passRate = Math.round((event.detail.passed / event.detail.total_rows) * 100 * 10) / 10;
            
            if (summaryPassRateEl) {
                summaryPassRateEl.textContent = passRate + '%';
                // Update color based on pass rate
                summaryPassRateEl.className = summaryPassRateEl.className.replace(/text-(green|red|yellow)-600/, 
                    passRate >= 80 ? 'text-green-600' : (passRate < 50 ? 'text-red-600' : 'text-yellow-600'));
            }
            if (passRateTextEl) {
                passRateTextEl.textContent = passRate + '%';
            }
            if (passRateBarEl) {
                passRateBarEl.style.width = passRate + '%';
                // Update bar color
                const colorClass = passRate >= 80 ? 'bg-gradient-to-r from-green-600 to-emerald-700' : 
                                 (passRate < 50 ? 'bg-gradient-to-r from-red-600 to-red-700' : 'bg-gradient-to-r from-yellow-600 to-orange-700');
                passRateBarEl.className = passRateBarEl.className.replace(/bg-gradient-to-r from-\w+-\d+ to-\w+-\d+/, colorClass);
            }
        }
        
        // Auto-scroll to latest result after a short delay to let Livewire update
        setTimeout(() => {
            const resultsContainer = document.querySelector('.live-results-container');
            if (resultsContainer) {
                const lastResult = resultsContainer.lastElementChild;
                if (lastResult) {
                    lastResult.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        }, 200);
    });

    // Listen for process-next-row event to continue processing
    window.addEventListener('process-next-row', event => {
        setTimeout(() => {
            @this.processNextRow();
        }, 100); // Small delay for UI updates
    });

    // Listen for evaluation completion
    window.addEventListener('evaluation-completed', event => {
        console.log('Evaluation completed:', event.detail);
        
        setTimeout(() => {
            const resultsSection = document.getElementById('results-section');
            if (resultsSection) {
                resultsSection.scrollIntoView({ behavior: 'smooth' });
            }
        }, 500);
    });

    // Auto-scroll to results when they appear
    window.addEventListener('show-results', event => {
        setTimeout(() => {
            const resultsSection = document.getElementById('results-section');
            if (resultsSection) {
                resultsSection.scrollIntoView({ behavior: 'smooth' });
            }
        }, 100);
    });
});
</script>
@endpush

<div class="min-h-screen bg-gray-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Minimal Header -->
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-white mb-2">Evaluation Runner</h1>
            <p class="text-gray-400">Test your agents with comprehensive evaluations</p>
        </div>

        <!-- Debug Section -->
        @if(session()->has('message'))
            <div class="mb-6 p-3 bg-blue-900/20 border border-blue-700/50 rounded-lg text-center">
                <p class="text-blue-300 text-sm">{{ session('message') }}</p>
            </div>
        @endif

        <!-- Progressive Disclosure Layout -->
        <div class="max-w-4xl mx-auto space-y-6">
            
            <!-- Step 1: Evaluation Selection (Always Visible) -->
            <div class="text-center">
                @if(count($availableEvaluations) > 0)
                    <div class="relative inline-block">
                        <select id="evaluation-select" 
                                wire:change="selectEvaluation($event.target.value)"
                                class="appearance-none bg-gray-800 border-2 border-gray-700 rounded-2xl px-6 py-4 pr-12 text-lg font-medium focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-200 shadow-lg hover:shadow-xl cursor-pointer min-w-96 text-gray-200">
                            <option value="">Choose an evaluation to run...</option>
                            @foreach($availableEvaluations as $evaluation)
                                <option value="{{ $evaluation['key'] }}" 
                                        @if($selectedEvaluation === $evaluation['class']) selected @endif>
                                    {{ $evaluation['name'] }} → {{ $evaluation['agent_name'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                @else
                    <div class="bg-gray-900/50 border-2 border-dashed border-gray-700 rounded-2xl p-12">
                        <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="text-xl font-bold text-white mb-2">No Evaluations Found</h3>
                        <p class="text-gray-400 mb-4">Create an evaluation to get started</p>
                        <code class="bg-gray-800 px-3 py-2 rounded text-sm text-gray-300">php artisan agent:make:eval MyEvaluation</code>
                    </div>
                @endif
            </div>

            <!-- Step 2: Evaluation Ready (When Selected) -->
            @if($selectedEvaluation && !$isRunning && count($results) == 0)
                @php
                    $selectedEval = collect($availableEvaluations)->firstWhere('class', $selectedEvaluation);
                    $csvPath = base_path($selectedEval['csv_path'] ?? '');
                    $testCount = 0;
                    if (File::exists($csvPath)) {
                        $testCount = count(file($csvPath)) - 1;
                    }
                @endphp
                
                <div class="bg-gradient-to-br from-green-900/20 to-emerald-900/20 border-2 border-green-700/50 rounded-3xl p-8 text-center shadow-xl">
                    <div class="flex items-center justify-center w-16 h-16 bg-green-600 rounded-full mx-auto mb-6 shadow-lg">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    
                    <h2 class="text-2xl font-bold text-white mb-2">{{ $selectedEval['name'] }}</h2>
                    <p class="text-gray-400 mb-6">Ready to test <strong class="text-gray-300">{{ $selectedEval['agent_name'] }}</strong> with {{ $testCount }} test cases</p>
                    
                    @if(!empty($currentStatus))
                        <div class="mb-4 p-3 bg-blue-900/20 border border-blue-700/50 rounded-lg">
                            <p class="text-blue-300 text-sm">{{ $currentStatus }}</p>
                        </div>
                    @endif
                    
                    <!-- Big Action Button -->
                    <button wire:click="runEvaluation"
                            class="inline-flex items-center px-12 py-4 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white text-xl font-bold rounded-2xl shadow-2xl hover:shadow-3xl transform hover:scale-105 transition-all duration-200 mb-6">
                        <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h1m4 0h1m6-4a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        🚀 Run Evaluation
                    </button>
                    
                    <p class="text-sm text-gray-500">Estimated time: ~{{ ceil($testCount * 2 / 60) }} minutes</p>
                    
                    <!-- Expandable Details -->
                    <div class="mt-8">
                        <button onclick="this.nextElementSibling.classList.toggle('hidden')" 
                                class="text-gray-400 hover:text-gray-200 text-sm font-medium flex items-center mx-auto">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            View evaluation details
                        </button>
                        
                        <div class="hidden mt-4 bg-gray-900/50 rounded-2xl p-6 border border-gray-800">
                            <div class="grid md:grid-cols-2 gap-6">
                                <!-- Test Criteria -->
                                <div>
                                    <h4 class="font-semibold text-white mb-3">Test Criteria</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex items-center text-gray-300">
                                            <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                                            Response completeness & length
                                        </div>
                                        <div class="flex items-center text-gray-300">
                                            <div class="w-2 h-2 bg-purple-500 rounded-full mr-2"></div>
                                            AI quality assessment
                                        </div>
                                        <div class="flex items-center text-gray-300">
                                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-2"></div>
                                            Content accuracy & relevance
                                        </div>
                                        <div class="flex items-center text-gray-300">
                                            <div class="w-2 h-2 bg-orange-500 rounded-full mr-2"></div>
                                            Sentiment & tone analysis
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sample Test -->
                                @if($selectedEval['csv_path'])
                                    @php
                                        $sampleData = [];
                                        if (File::exists($csvPath)) {
                                            $handle = fopen($csvPath, 'r');
                                            $header = fgetcsv($handle);
                                            $firstRow = fgetcsv($handle);
                                            if ($header && $firstRow && count($header) === count($firstRow)) {
                                                $sampleData = array_combine($header, $firstRow);
                                            }
                                            fclose($handle);
                                        }
                                    @endphp
                                    @if(!empty($sampleData) && isset($sampleData['prompt']))
                                        <div>
                                            <h4 class="font-semibold text-white mb-3">Sample Test</h4>
                                            <div class="bg-gray-800 rounded-lg p-3 text-sm">
                                                <p class="text-gray-300">"{{ Str::limit($sampleData['prompt'], 120) }}"</p>
                                                @if(isset($sampleData['must_contain']))
                                                    <p class="text-xs text-gray-500 mt-2">Must contain: {{ $sampleData['must_contain'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Step 3: Execution Progress -->
            @if($isRunning)
                @php
                    $contextEval = collect($availableEvaluations)->firstWhere('class', $selectedEvaluation);
                @endphp
                <div class="bg-gradient-to-br from-blue-900/20 to-indigo-900/20 border-2 border-blue-700/50 rounded-3xl p-8 shadow-xl">
                    <div class="text-center mb-6">
                        <div class="flex items-center justify-center w-16 h-16 bg-blue-600 rounded-full mx-auto mb-4 shadow-lg">
                            <svg class="w-8 h-8 text-white spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-2">Running: {{ $contextEval['name'] ?? 'Evaluation' }}</h2>
                        <p class="text-gray-400">Testing {{ $contextEval['agent_name'] ?? 'agent' }} • {{ $currentRowIndex }}/{{ $totalRows }} tests completed</p>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mb-6">
                        <div class="flex justify-between text-sm text-gray-400 mb-2">
                            <span class="font-medium">{{ $progress }}% Complete</span>
                            @php
                                $remainingRows = $totalRows - $currentRowIndex;
                                $estimatedMinutes = $remainingRows > 0 ? ceil($remainingRows * 2 / 60) : 0;
                            @endphp
                            <span>~{{ $estimatedMinutes }} min remaining</span>
                        </div>
                        <div class="w-full bg-gray-800 rounded-full h-4 shadow-inner">
                            <div class="bg-gradient-to-r from-blue-600 to-purple-600 h-4 rounded-full transition-all duration-300 shadow-lg"
                                 style="width: {{ $progress }}%"></div>
                        </div>
                        <p class="text-sm text-gray-400 mt-2 text-center">{{ $currentStatus }}</p>
                    </div>
                    
                    <!-- Live Stats -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="bg-gray-900/50 rounded-xl p-4 text-center shadow-sm border border-gray-800">
                            <p class="text-2xl font-bold text-green-400">{{ $passCount }}</p>
                            <p class="text-xs text-gray-400">Passed</p>
                        </div>
                        <div class="bg-gray-900/50 rounded-xl p-4 text-center shadow-sm border border-gray-800">
                            <p class="text-2xl font-bold text-red-400">{{ $failCount }}</p>
                            <p class="text-xs text-gray-400">Failed</p>
                        </div>
                        <div class="bg-gray-900/50 rounded-xl p-4 text-center shadow-sm border border-gray-800">
                            @php
                                $livePassRate = $totalRows > 0 ? round(($passCount / $totalRows) * 100, 1) : 0;
                            @endphp
                            <p class="text-2xl font-bold {{ $livePassRate >= 80 ? 'text-green-400' : ($livePassRate < 50 ? 'text-red-400' : 'text-yellow-400') }}">{{ $livePassRate }}%</p>
                            <p class="text-xs text-gray-400">Pass Rate</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Step 4: Results (Live and Final) -->
            @if(count($results) > 0 || ($showResults && !empty($resultSummary)))
                @php
                    $contextEval = collect($availableEvaluations)->firstWhere('class', $selectedEvaluation);
                    $finalPassRate = isset($resultSummary['pass_rate']) ? $resultSummary['pass_rate'] : ($totalRows > 0 ? round(($passCount / $totalRows) * 100, 1) : 0);
                @endphp
                
                <div class="bg-gray-900/50 border-2 border-gray-800/50 rounded-3xl p-8 shadow-xl">
                    <!-- Results Header -->
                    <div class="text-center mb-8">
                        <div class="flex items-center justify-center w-16 h-16 {{ $finalPassRate >= 80 ? 'bg-green-600' : ($finalPassRate < 50 ? 'bg-red-600' : 'bg-yellow-600') }} rounded-full mx-auto mb-4 shadow-lg">
                            @if($isRunning)
                                <svg class="w-8 h-8 text-white spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                            @else
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            @endif
                        </div>
                        <h2 class="text-2xl font-bold text-white mb-2">
                            {{ $contextEval['name'] ?? 'Evaluation' }} 
                            @if(!$isRunning)
                                - {{ $finalPassRate >= 80 ? 'Excellent!' : ($finalPassRate < 50 ? 'Needs Work' : 'Good') }}
                            @endif
                        </h2>
                        <p class="text-gray-400">
                            @if($isRunning)
                                Running live results • {{ $passCount }}/{{ $currentRowIndex }} passed so far
                            @else
                                Final results • {{ $contextEval['agent_name'] ?? 'Agent' }} achieved {{ $finalPassRate }}% pass rate
                            @endif
                        </p>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="grid grid-cols-4 gap-4 mb-8">
                        <div class="bg-gray-800 rounded-2xl p-4 text-center shadow-sm border border-gray-700">
                            <p class="text-3xl font-bold text-white">{{ $totalRows ?: (isset($resultSummary['total_rows']) ? $resultSummary['total_rows'] : 0) }}</p>
                            <p class="text-sm text-gray-500">Total</p>
                        </div>
                        <div class="bg-gray-800 rounded-2xl p-4 text-center shadow-sm border border-gray-700">
                            <p class="text-3xl font-bold text-green-400">{{ $passCount ?: (isset($resultSummary['passed']) ? $resultSummary['passed'] : 0) }}</p>
                            <p class="text-sm text-gray-500">Passed</p>
                        </div>
                        <div class="bg-gray-800 rounded-2xl p-4 text-center shadow-sm border border-gray-700">
                            <p class="text-3xl font-bold text-red-400">{{ $failCount ?: (isset($resultSummary['failed']) ? $resultSummary['failed'] : 0) }}</p>
                            <p class="text-sm text-gray-500">Failed</p>
                        </div>
                        <div class="bg-gray-800 rounded-2xl p-4 text-center shadow-sm border border-gray-700">
                            <p class="text-3xl font-bold {{ $finalPassRate >= 80 ? 'text-green-400' : ($finalPassRate < 50 ? 'text-red-400' : 'text-yellow-400') }}">{{ $finalPassRate }}%</p>
                            <p class="text-sm text-gray-500">Pass Rate</p>
                        </div>
                    </div>
                    
                    <!-- Results List -->
                    @if(count($results) > 0)
                        <div>
                            <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                                @if($isRunning)
                                    <svg class="w-5 h-5 text-blue-400 mr-2 spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    Live Test Results
                                @else
                                    <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Test Results
                                @endif
                            </h3>
                            
                            <div class="space-y-2 max-h-96 overflow-y-auto">
                                @foreach($results as $result)
                                    <div class="bg-gray-800 rounded-lg border-l-4 {{ $result['passed'] ? 'border-green-500' : 'border-red-500' }} p-4 shadow-sm cursor-pointer transition-all duration-200 hover:shadow-md hover:bg-gray-750"
                                         wire:click="toggleRowExpansion({{ $result['row_index'] }})">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-3">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold text-white {{ $result['passed'] ? 'bg-green-600' : 'bg-red-600' }}">
                                                    {{ $result['row_index'] }}
                                                </span>
                                                <div>
                                                    <span class="text-sm font-medium {{ $result['passed'] ? 'text-green-400' : 'text-red-400' }}">
                                                        {{ $result['passed'] ? 'PASSED' : 'FAILED' }}
                                                    </span>
                                                    @if(isset($result['evaluation_result']['assertions']))
                                                        @php
                                                            $assertions = $result['evaluation_result']['assertions'];
                                                            $passedAssertions = collect($assertions)->where('status', 'pass')->count();
                                                            $totalAssertions = count($assertions);
                                                        @endphp
                                                        <span class="ml-2 text-xs px-2 py-1 rounded-full {{ $result['passed'] ? 'bg-green-900/50 text-green-300' : 'bg-red-900/50 text-red-300' }}">
                                                            {{ $passedAssertions }}/{{ $totalAssertions }} checks
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 {{ in_array($result['row_index'], $expandedRows) ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                        
                                        @if(isset($result['row_data']['prompt']))
                                            <p class="text-sm text-gray-400 mt-2">{{ Str::limit($result['row_data']['prompt'], 100) }}</p>
                                        @endif
                                        
                                        <!-- Expanded Details -->
                                        @if(in_array($result['row_index'], $expandedRows))
                                            <div class="mt-4 pt-4 border-t border-gray-700">
                                                <div class="grid md:grid-cols-2 gap-4">
                                                    <!-- Full Response -->
                                                    <div>
                                                        <h5 class="text-sm font-semibold text-gray-300 mb-2">Full Response:</h5>
                                                        <div class="bg-gray-900 p-3 rounded text-sm text-gray-300 max-h-32 overflow-y-auto">
                                                            {{ $result['llm_response'] }}
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Assertion Results -->
                                                    @if(isset($result['evaluation_result']['assertions']) && count($result['evaluation_result']['assertions']) > 0)
                                                        <div>
                                                            <h5 class="text-sm font-semibold text-gray-300 mb-2">Assertion Results:</h5>
                                                            <div class="space-y-1 max-h-32 overflow-y-auto">
                                                                @foreach($result['evaluation_result']['assertions'] as $assertion)
                                                                    <div class="flex items-center text-xs {{ $assertion['status'] === 'pass' ? 'text-green-400' : 'text-red-400' }}">
                                                                        @if($assertion['status'] === 'pass')
                                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                                            </svg>
                                                                        @else
                                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                            </svg>
                                                                        @endif
                                                                        {{ $assertion['name'] ?? 'Check' }}: {{ $assertion['message'] ?? strtoupper($assertion['status']) }}
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    
                    <!-- Actions -->
                    @if(!$isRunning)
                        <div class="mt-8 pt-6 border-t border-gray-700 flex justify-center space-x-4">
                            <button wire:click="resetResults"
                                    class="px-6 py-3 bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium rounded-xl transition-all duration-200 border border-gray-700">
                                Reset & Start Over
                            </button>
                            
                            @if($selectedEvaluation)
                                <button wire:click="runEvaluation"
                                        class="px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white font-semibold rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                                    🚀 Run Again
                                </button>
                            @endif
                            
                            @if($outputPath)
                                <button wire:click="downloadResults"
                                        class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl transition-all duration-200 shadow-md hover:shadow-lg">
                                    📥 Download CSV
                                </button>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>