@push('scripts')
<style>
    /* Enhanced animations and transitions */
    .eval-card {
        transition: all 0.3s ease-in-out;
    }

    .eval-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
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
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.6s ease-in-out;
    }

    .stat-card:hover::before {
        left: 100%;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px -8px rgba(0, 0, 0, 0.15);
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
        box-shadow: 0 15px 35px -5px rgba(0, 0, 0, 0.12);
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
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .success-glow {
        box-shadow: 0 0 20px rgba(34, 197, 94, 0.3);
    }

    .error-glow {
        box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Listen for evaluation progress updates
    window.addEventListener('evaluation-progress', event => {
        console.log('Evaluation progress:', event.detail);
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

<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Enhanced Header -->
        <div class="eval-card bg-gradient-to-br from-white via-purple-50/30 to-indigo-50/40 overflow-hidden shadow-xl rounded-2xl mb-8 border border-slate-200/60">
            <div class="bg-gradient-to-r from-purple-600/5 via-indigo-600/5 to-blue-600/5 px-8 py-8">
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 mb-6 shadow-lg shadow-purple-500/25">
                        <svg class="h-8 w-8 text-white hero-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h1 class="text-4xl font-bold gradient-text mb-3">Evaluation Runner</h1>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto leading-relaxed">
                        Run comprehensive evaluations to test agent quality, performance, and reliability using your configured evaluation frameworks.
                    </p>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Available Evaluations -->
            <div class="stat-card bg-gradient-to-br from-white to-purple-50/30 overflow-hidden shadow-lg rounded-xl border border-purple-100/50">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-lg">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Available Evaluations</p>
                            <p class="text-2xl font-bold text-gray-900">{{ count($availableEvaluations) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Status -->
            <div class="stat-card bg-gradient-to-br from-white to-green-50/30 overflow-hidden shadow-lg rounded-xl border border-green-100/50">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 text-white shadow-lg">
                                @if($isRunning)
                                    <svg class="h-6 w-6 spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                @else
                                    <svg class="h-6 w-6 status-indicator" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                @endif
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Status</p>
                            <p class="text-sm font-bold text-gray-900">
                                @if($isRunning)
                                    <span class="text-orange-600">Running</span>
                                @elseif($showResults)
                                    <span class="text-green-600">Completed</span>
                                @else
                                    <span class="text-gray-600">Ready</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="stat-card bg-gradient-to-br from-white to-blue-50/30 overflow-hidden shadow-lg rounded-xl border border-blue-100/50">
                <div class="p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-gradient-to-br from-blue-500 to-cyan-600 text-white shadow-lg">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Progress</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $progress }}%</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
            <!-- Left Column: Evaluation Selection -->
            <div class="space-y-6">
                <!-- Available Evaluations -->
                <div class="eval-card bg-gradient-to-br from-white via-slate-50/30 to-gray-50/40 overflow-hidden shadow-xl rounded-2xl border border-slate-200/60">
                    <div class="bg-gradient-to-r from-slate-600/5 via-gray-600/5 to-zinc-600/5 px-6 py-5">
                        <div class="flex items-center">
                            <div class="flex items-center justify-center h-10 w-10 rounded-xl bg-gradient-to-br from-slate-500 to-gray-600 text-white shadow-lg mr-4">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold gradient-text">Available Evaluations</h3>
                        </div>
                    </div>
                    <div class="p-6">
                        @if(count($availableEvaluations) > 0)
                            <div class="space-y-4">
                                @foreach($availableEvaluations as $evaluation)
                                    <div class="evaluation-item bg-white rounded-xl border border-gray-200/60 p-4 cursor-pointer transition-all duration-200 hover:border-blue-300 {{ $selectedEvaluation === $evaluation['class'] ? 'border-blue-500 bg-blue-50/50' : '' }}"
                                         wire:click="selectEvaluation({{ $evaluation['key'] }})">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h4 class="text-lg font-semibold text-gray-900 mb-1">{{ $evaluation['name'] }}</h4>
                                                <p class="text-sm text-gray-600 mb-2">{{ $evaluation['description'] }}</p>
                                                <div class="flex items-center space-x-4 text-xs text-gray-500">
                                                    <span class="flex items-center">
                                                        <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                        </svg>
                                                        Agent: {{ $evaluation['agent_name'] }}
                                                    </span>
                                                    @if($evaluation['csv_path'])
                                                        <span class="flex items-center">
                                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                            </svg>
                                                            {{ basename($evaluation['csv_path']) }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($selectedEvaluation === $evaluation['class'])
                                                <div class="flex items-center justify-center h-6 w-6 rounded-full bg-blue-500 text-white">
                                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Run Evaluation Button -->
                            @if($selectedEvaluation && !$isRunning)
                                <div class="mt-6 pt-6 border-t border-gray-200">
                                    <button wire:click="runEvaluation"
                                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-200 shadow-lg hover:shadow-xl transform hover:scale-105">
                                        <div class="flex items-center justify-center">
                                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h1m4 0h1m6-4a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Run Evaluation
                                        </div>
                                    </button>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-12">
                                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-2xl bg-gradient-to-br from-gray-400 to-gray-500 mb-6 shadow-lg">
                                    <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">No Evaluations Found</h3>
                                <p class="text-gray-600 mb-4">Create an evaluation to get started with testing your agents.</p>
                                <div class="bg-gradient-to-r from-gray-50 to-blue-50/50 border border-gray-200/50 rounded-lg p-3 font-mono text-sm text-gray-800 shadow-inner">
                                    php artisan agent:make:eval MyEvaluation
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Right Column: Status & Results -->
            <div class="space-y-6">
                <!-- Current Status -->
                @if($isRunning || $currentStatus)
                    <div class="eval-card bg-gradient-to-br from-white via-orange-50/30 to-yellow-50/40 overflow-hidden shadow-xl rounded-2xl border border-orange-200/60">
                        <div class="bg-gradient-to-r from-orange-600/5 via-yellow-600/5 to-amber-600/5 px-6 py-5">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center h-10 w-10 rounded-xl bg-gradient-to-br from-orange-500 to-yellow-600 text-white shadow-lg mr-4">
                                    @if($isRunning)
                                        <svg class="h-5 w-5 spinner" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                    @else
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    @endif
                                </div>
                                <h3 class="text-xl font-bold gradient-text">
                                    @if($isRunning) Running Evaluation @else Status @endif
                                </h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <p class="text-gray-700 mb-4">{{ $currentStatus }}</p>

                            @if($isRunning && $totalRows > 0)
                                <div class="mb-4">
                                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                                        <span>Progress</span>
                                        <span>{{ $progress }}% ({{ $totalRows > 0 ? intval(($progress / 100) * $totalRows) : 0 }}/{{ $totalRows }})</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3 shadow-inner">
                                        <div class="progress-bar bg-gradient-to-r from-blue-500 to-purple-600 h-3 rounded-full transition-all duration-300 shadow-lg"
                                             style="width: {{ $progress }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Results Summary -->
                @if($showResults && !empty($resultSummary))
                    <div id="results-section" class="results-card bg-gradient-to-br from-white via-green-50/30 to-emerald-50/40 overflow-hidden shadow-xl rounded-2xl border border-green-200/60 {{ $resultSummary['pass_rate'] >= 80 ? 'success-glow' : ($resultSummary['pass_rate'] < 50 ? 'error-glow' : '') }}">
                        <div class="bg-gradient-to-r from-green-600/5 via-emerald-600/5 to-teal-600/5 px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="flex items-center justify-center h-10 w-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 text-white shadow-lg mr-4">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-xl font-bold gradient-text">Evaluation Results</h3>
                                </div>
                                @if($outputPath)
                                    <button wire:click="downloadResults"
                                            class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105">
                                        <div class="flex items-center">
                                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            Download CSV
                                        </div>
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="p-6">
                            <!-- Summary Stats -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                                <div class="text-center p-4 bg-white/50 rounded-xl border border-gray-200/50">
                                    <p class="text-2xl font-bold text-gray-900">{{ $resultSummary['total_rows'] }}</p>
                                    <p class="text-sm text-gray-600">Total Tests</p>
                                </div>
                                <div class="text-center p-4 bg-green-50/50 rounded-xl border border-green-200/50">
                                    <p class="text-2xl font-bold text-green-600">{{ $resultSummary['passed'] }}</p>
                                    <p class="text-sm text-gray-600">Passed</p>
                                </div>
                                <div class="text-center p-4 bg-red-50/50 rounded-xl border border-red-200/50">
                                    <p class="text-2xl font-bold text-red-600">{{ $resultSummary['failed'] }}</p>
                                    <p class="text-sm text-gray-600">Failed</p>
                                </div>
                                <div class="text-center p-4 bg-blue-50/50 rounded-xl border border-blue-200/50">
                                    <p class="text-2xl font-bold {{ $resultSummary['pass_rate'] >= 80 ? 'text-green-600' : ($resultSummary['pass_rate'] < 50 ? 'text-red-600' : 'text-yellow-600') }}">{{ $resultSummary['pass_rate'] }}%</p>
                                    <p class="text-sm text-gray-600">Pass Rate</p>
                                </div>
                            </div>

                            <!-- Pass Rate Visualization -->
                            <div class="mb-6">
                                <div class="flex justify-between text-sm text-gray-600 mb-2">
                                    <span>Overall Pass Rate</span>
                                    <span>{{ $resultSummary['pass_rate'] }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-4 shadow-inner">
                                    <div class="h-4 rounded-full transition-all duration-500 shadow-lg {{ $resultSummary['pass_rate'] >= 80 ? 'bg-gradient-to-r from-green-500 to-emerald-600' : ($resultSummary['pass_rate'] < 50 ? 'bg-gradient-to-r from-red-500 to-red-600' : 'bg-gradient-to-r from-yellow-500 to-orange-600') }}"
                                         style="width: {{ $resultSummary['pass_rate'] }}%"></div>
                                </div>
                            </div>

                            <!-- Recent Results Preview -->
                            @if(!empty($results))
                                <div class="space-y-3">
                                    <h4 class="font-semibold text-gray-900 mb-3">Recent Results (Latest 5)</h4>
                                    @foreach(array_slice($results, -5) as $result)
                                        <div class="flex items-center justify-between p-3 bg-white/60 rounded-lg border border-gray-200/50">
                                            <div class="flex items-center">
                                                <div class="flex items-center justify-center h-6 w-6 rounded-full mr-3 {{ $result['passed'] ? 'bg-green-500' : 'bg-red-500' }}">
                                                    @if($result['passed'])
                                                        <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @else
                                                        <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">Row {{ $result['row_index'] }}</p>
                                                    <p class="text-xs text-gray-500">{{ Str::limit($result['llm_response'], 50) }}</p>
                                                </div>
                                            </div>
                                            <span class="text-xs font-medium {{ $result['passed'] ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $result['passed'] ? 'PASS' : 'FAIL' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Actions -->
                            <div class="mt-6 pt-6 border-t border-gray-200 flex space-x-3">
                                <button wire:click="resetResults"
                                        class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg">
                                    <div class="flex items-center justify-center">
                                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                        </svg>
                                        Reset
                                    </div>
                                </button>
                                @if($selectedEvaluation)
                                    <button wire:click="runEvaluation"
                                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-all duration-200 shadow-md hover:shadow-lg">
                                        <div class="flex items-center justify-center">
                                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                            Run Again
                                        </div>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
