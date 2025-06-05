# Trace Visualization Implementation

## Overview

This document describes the implementation of the trace visualization system in the Laravel Agent ADK chat interface.

## Features

- **Tabbed Interface**: Added a "Traces" tab alongside "Agent Info" and "Session/Memory"
- **Hierarchical Trace View**: Displays nested spans showing the execution flow
- **Timing Information**: Shows start/end times and duration for each operation
- **Metadata Display**: Collapsible sections for input/output data
- **Error Visibility**: Special highlighting for errors
- **Session Loading**: Support for loading trace data from any session ID

## Key Components

### 1. ChatInterface Component

The Livewire component in `src/Livewire/ChatInterface.php` manages trace data and rendering:

```php
public function loadTraceData()
{
    if (empty($this->sessionId)) {
        $this->traceData = [];
        return;
    }

    try {
        // Get trace spans for this session
        $spans = TraceSpan::where('session_id', $this->sessionId)
            ->orderBy('start_time', 'asc')
            ->get();

        if ($spans->isEmpty()) {
            $this->traceData = [];
            return;
        }

        // Build hierarchical structure
        $this->traceData = $this->buildTraceTree($spans);
    } catch (\Exception $e) {
        $this->traceData = ['error' => $e->getMessage()];
    }
}
```

### 2. Trace Span Partial

The `resources/views/partials/trace-span.blade.php` partial provides the recursive rendering of traces:

```blade
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

            <!-- Toggle button for details -->
            @if($span['input_data'] || $span['output_data'] || $span['error_data'])
                <button onclick="toggleSpanDetails('{{ $span['span_id'] }}')"
                        class="ml-2 text-gray-400 hover:text-gray-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            @endif
        </div>

        <!-- Collapsible details -->
        @if($span['input_data'] || $span['output_data'] || $span['error_data'])
            <div id="span-details-{{ $span['span_id'] }}" class="hidden mt-2 ml-4 space-y-2">
                <!-- Input, output, error data sections -->
            </div>
        @endif

        <!-- Child spans (recursive) -->
        @if(!empty($span['children']))
            <div class="mt-2">
                @foreach($span['children'] as $child)
                    @include('agent-adk::partials.trace-span', ['span' => $child, 'level' => $level + 1])
                @endforeach
            </div>
        @endif
    </div>
</div>
```

## Technical Details

### Timestamp Handling

Trace timestamps are stored as decimal timestamps in the database. We convert them to formatted times for display:

```php
if ($span->start_time) {
    $startTime = \Carbon\Carbon::createFromTimestamp($span->start_time);
    $startFormatted = $startTime->format('H:i:s.v');

    if ($span->end_time) {
        $endTime = \Carbon\Carbon::createFromTimestamp($span->end_time);
        $endFormatted = $endTime->format('H:i:s.v');
        $duration = round(($span->end_time - $span->start_time) * 1000, 2); // Convert to milliseconds
    }
}
```

### Hierarchical Tree Building

Spans are organized into a parent-child hierarchy using the parent_span_id field:

```php
private function buildTraceTree($spans)
{
    $spansByParent = $spans->groupBy('parent_span_id');
    $rootSpans = $spansByParent->get(null) ?? collect();

    return $rootSpans->map(function ($span) use ($spansByParent) {
        return $this->buildSpanNode($span, $spansByParent);
    })->toArray();
}
```

## Usage

1. Navigate to the chat interface at `/ai-adk/chat`
2. Select an agent and interact with it, or load an existing session
3. Click the "Traces" tab in the details panel to view trace data
4. Expand span items to see additional details

## Testing

You can test this feature using the provided demo script:

```bash
php demo_trace_visualization.php
```

This will generate trace data with multiple agents that can be viewed in the interface.
