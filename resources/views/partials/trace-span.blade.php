@php
    $statusColors = [
        'success' => 'bg-green-100 text-green-800 border-green-200',
        'error' => 'bg-red-100 text-red-800 border-red-200',
        'running' => 'bg-blue-100 text-blue-800 border-blue-200',
        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    ];

    $typeConfig = [
        'agent_run' => [
            'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>',
            'color' => 'text-purple-600',
            'bg' => 'bg-purple-50',
            'border' => 'border-purple-200'
        ],
        'llm_call' => [
            'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>',
            'color' => 'text-blue-600',
            'bg' => 'bg-blue-50',
            'border' => 'border-blue-200'
        ],
        'tool_call' => [
            'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
            'color' => 'text-orange-600',
            'bg' => 'bg-orange-50',
            'border' => 'border-orange-200'
        ],
        'sub_agent_delegation' => [
            'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>',
            'color' => 'text-indigo-600',
            'bg' => 'bg-indigo-50',
            'border' => 'border-indigo-200'
        ],
    ];

    $statusColor = $statusColors[$span['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
    $typeInfo = $typeConfig[$span['type']] ?? [
        'icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>',
        'color' => 'text-gray-600',
        'bg' => 'bg-gray-50',
        'border' => 'border-gray-200'
    ];

    $indent = $level * 24;
    $hasDetails = (!empty($span['input_data']) || !empty($span['output_data']) ||
                   !empty($span['error_data']) || !empty($span['metadata']));
@endphp

<!-- Compact Trace Span Item -->
<div class="relative" style="margin-left: {{ $indent }}px;">
    <!-- Connection Lines for Tree Structure -->
    @if($level > 0)
        <div class="absolute left-0 top-0 bottom-0 w-4">
            <div class="absolute left-2 top-0 bottom-0 w-px bg-gray-200"></div>
            <div class="absolute left-2 top-3 w-2 h-px bg-gray-200"></div>
        </div>
    @endif

    <!-- Compact Span Content -->
    <div class="compact-trace-card bg-white/70 backdrop-blur-sm border border-gray-200/60 rounded-lg hover:bg-white hover:border-gray-300/80 hover:shadow-sm transition-all duration-200 mb-1.5 {{ $level > 0 ? 'ml-4' : '' }} group">
        <div class="px-3 py-2">
            <div class="flex items-center space-x-2.5">
                <!-- Compact Type Icon -->
                <div class="flex-shrink-0 w-6 h-6 {{ $typeInfo['bg'] }} rounded-md flex items-center justify-center {{ $typeInfo['color'] }} group-hover:scale-105 transition-transform duration-150">
                    <div class="w-3.5 h-3.5">
                        {!! str_replace('w-4 h-4', 'w-3.5 h-3.5', $typeInfo['icon']) !!}
                    </div>
                </div>

                <!-- Inline Span Info -->
                <div class="flex-1 min-w-0 flex items-center space-x-2.5">
                    <!-- Name and Status -->
                    <div class="flex items-center space-x-2 min-w-0 flex-1">
                        <span class="text-sm font-medium text-gray-900 truncate">{{ $span['name'] }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-xs font-medium {{ $statusColor }} border-0 flex-shrink-0">
                            {{ ucfirst($span['status']) }}
                        </span>
                    </div>

                    <!-- Compact Metadata -->
                    <div class="flex items-center space-x-2 text-xs text-gray-500 flex-shrink-0">
                        @if(!empty($span['duration_ms']))
                            <div class="flex items-center space-x-1 bg-gray-50 px-1.5 py-0.5 rounded">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <span class="font-medium">{{ $span['duration_ms'] }}ms</span>
                            </div>
                        @endif

                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 border-0">
                            {{ str_replace('_', ' ', ucwords($span['type'], '_')) }}
                        </span>

                        @if($span['type'] === 'agent_run' && !empty($span['metadata']['execution_mode']))
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700 border-0">
                                {{ ucfirst($span['metadata']['execution_mode']) }} Mode
                            </span>
                        @endif

                        @if(!empty($span['start_time']))
                            <span class="text-gray-400 font-mono text-xs">{{ $span['start_time'] }}</span>
                        @endif
                    </div>
                </div>

                <!-- Compact Expand Button -->
                @if($hasDetails)
                    <button onclick="toggleSpanDetails('{{ $span['span_id'] }}')"
                            class="flex-shrink-0 p-1 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors duration-150 opacity-0 group-hover:opacity-100">
                        <svg class="w-3.5 h-3.5 transform transition-transform duration-200" id="chevron-{{ $span['span_id'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                @endif
            </div>

            <!-- Compact Expandable Details Section -->
            @if($hasDetails)
                <div id="span-details-{{ $span['span_id'] }}" class="hidden mt-2 pt-2 border-t border-gray-100/80 space-y-2">
                    @if(!empty($span['input_data']))
                        <div class="bg-blue-50/80 border border-blue-200/60 rounded-md p-2">
                            <div class="flex items-center space-x-1.5 mb-1.5">
                                <svg class="w-3 h-3 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18" />
                                </svg>
                                <span class="text-xs font-semibold text-blue-800">Input</span>
                            </div>
                            <pre class="text-xs text-blue-700 bg-white/80 rounded border border-blue-200/60 p-1.5 overflow-auto max-h-20 whitespace-pre-wrap">@php
                                try {
                                    echo json_encode($span['input_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                } catch (\Exception $e) {
                                    echo "[Error displaying input data: " . $e->getMessage() . "]";
                                }
                            @endphp</pre>
                        </div>
                    @endif

                    @if(!empty($span['output_data']))
                        <div class="bg-green-50/80 border border-green-200/60 rounded-md p-2">
                            <div class="flex items-center space-x-1.5 mb-1.5">
                                <svg class="w-3 h-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                </svg>
                                <span class="text-xs font-semibold text-green-800">Output</span>
                            </div>
                            <pre class="text-xs text-green-700 bg-white/80 rounded border border-green-200/60 p-1.5 overflow-auto max-h-20 whitespace-pre-wrap">@php
                                try {
                                    echo json_encode($span['output_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                } catch (\Exception $e) {
                                    echo "[Error displaying output data: " . $e->getMessage() . "]";
                                }
                            @endphp</pre>
                        </div>
                    @endif

                    @if(!empty($span['error_data']))
                        <div class="bg-red-50/80 border border-red-200/60 rounded-md p-2">
                            <div class="flex items-center space-x-1.5 mb-1.5">
                                <svg class="w-3 h-3 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.268 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                                <span class="text-xs font-semibold text-red-800">Error</span>
                            </div>
                            <pre class="text-xs text-red-700 bg-white/80 rounded border border-red-200/60 p-1.5 overflow-auto max-h-20 whitespace-pre-wrap">@php
                                try {
                                    echo json_encode($span['error_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                } catch (\Exception $e) {
                                    echo "[Error displaying error data: " . $e->getMessage() . "]";
                                }
                            @endphp</pre>
                        </div>
                    @endif

                    @if(!empty($span['metadata']))
                        <div class="bg-gray-50/80 border border-gray-200/60 rounded-md p-2">
                            <div class="flex items-center space-x-1.5 mb-1.5">
                                <svg class="w-3 h-3 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-xs font-semibold text-gray-800">Metadata</span>
                            </div>
                            <pre class="text-xs text-gray-700 bg-white/80 rounded border border-gray-200/60 p-1.5 overflow-auto max-h-20 whitespace-pre-wrap">@php
                                try {
                                    echo json_encode($span['metadata'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                } catch (\Exception $e) {
                                    echo "[Error displaying metadata: " . $e->getMessage() . "]";
                                }
                            @endphp</pre>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <!-- Child Spans -->
    @if(!empty($span['children']))
        <div class="space-y-0">
            @foreach($span['children'] as $child)
                @include('vizra-adk::partials.trace-span', ['span' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    @endif
</div>


