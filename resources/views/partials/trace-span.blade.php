@php
    $statusColors = [
        'success' => 'bg-green-100 text-green-800',
        'error' => 'bg-red-100 text-red-800',
        'running' => 'bg-blue-100 text-blue-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
    ];

    $typeIcons = [
        'agent_run' => '🤖',
        'llm_call' => '🧠',
        'tool_call' => '🔧',
        'sub_agent_delegation' => '🔄',
    ];

    $statusColor = $statusColors[$span['status']] ?? 'bg-gray-100 text-gray-800';
    $typeIcon = $typeIcons[$span['type']] ?? '📋';
    $indent = $level * 20;
    $hasDetails = (!empty($span['input_data']) || !empty($span['output_data']) ||
                   !empty($span['error_data']) || !empty($span['metadata']));
@endphp

<div class="border-l-2 {{ $span['status'] === 'error' ? 'border-red-300' : 'border-gray-200' }}" style="margin-left: {{ $indent }}px;">
    <div class="ml-4 mb-2">
        <div class="flex items-start justify-between p-3 bg-gray-50 rounded-md">
            <div class="flex-1 min-w-0">
                <div class="flex items-center space-x-2 mb-1">
                    <span class="text-sm">{{ $typeIcon }}</span>
                    <h5 class="text-sm font-medium text-gray-900 truncate">{{ $span['name'] }}</h5>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $statusColor }}">
                        {{ ucfirst($span['status']) }}
                    </span>
                </div>

                <div class="flex items-center space-x-4 text-xs text-gray-500">
                    @if($span['start_time'])
                        <span>⏰ {{ $span['start_time'] }}</span>
                    @endif
                    @if($span['duration_ms'])
                        <span>⏱️ {{ $span['duration_ms'] }}ms</span>
                    @endif
                    <span class="px-2 py-1 bg-gray-200 rounded text-gray-700">{{ $span['type'] }}</span>
                </div>
            </div>

            @if($hasDetails)
                <button onclick="toggleSpanDetails('{{ $span['span_id'] }}')"
                        class="ml-2 text-gray-400 hover:text-gray-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            @endif
        </div>

        <!-- Span Details (Hidden by default) -->
        @if($hasDetails)
            <div id="span-details-{{ $span['span_id'] }}" class="hidden mt-2 ml-4 space-y-2">
                @if(!empty($span['input_data']))
                    <div class="p-2 bg-blue-50 rounded border">
                        <div class="text-xs font-medium text-blue-800 mb-1">Input:</div>
                        <pre class="text-xs text-blue-700 whitespace-pre-wrap overflow-auto max-h-32">@php
                            try {
                                echo json_encode($span['input_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            } catch (\Exception $e) {
                                echo "[Error displaying input data: " . $e->getMessage() . "]";
                            }
                        @endphp</pre>
                    </div>
                @endif

                @if(!empty($span['output_data']))
                    <div class="p-2 bg-green-50 rounded border">
                        <div class="text-xs font-medium text-green-800 mb-1">Output:</div>
                        <pre class="text-xs text-green-700 whitespace-pre-wrap overflow-auto max-h-32">@php
                            try {
                                echo json_encode($span['output_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            } catch (\Exception $e) {
                                echo "[Error displaying output data: " . $e->getMessage() . "]";
                            }
                        @endphp</pre>
                    </div>
                @endif

                @if(!empty($span['error_data']))
                    <div class="p-2 bg-red-50 rounded border">
                        <div class="text-xs font-medium text-red-800 mb-1">Error:</div>
                        <pre class="text-xs text-red-700 whitespace-pre-wrap overflow-auto max-h-32">@php
                            try {
                                echo json_encode($span['error_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                            } catch (\Exception $e) {
                                echo "[Error displaying error data: " . $e->getMessage() . "]";
                            }
                        @endphp</pre>
                    </div>
                @endif

                @if(!empty($span['metadata']))
                    <div class="p-2 bg-gray-50 rounded border">
                        <div class="text-xs font-medium text-gray-800 mb-1">Metadata:</div>
                        <pre class="text-xs text-gray-700 whitespace-pre-wrap overflow-auto max-h-32">@php
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

        <!-- Child Spans -->
        @if(!empty($span['children']))
            <div class="mt-2">
                @foreach($span['children'] as $child)
                    @include('agent-adk::partials.trace-span', ['span' => $child, 'level' => $level + 1])
                @endforeach
            </div>
        @endif
    </div>
</div>


