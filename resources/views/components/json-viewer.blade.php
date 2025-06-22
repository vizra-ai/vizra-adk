@props([
    'data' => null,
    'title' => null,
    'icon' => null,
    'bgColor' => 'bg-gray-800/30',
    'borderColor' => 'border-gray-700/40',
    'textColor' => 'text-gray-200',
    'titleColor' => 'text-gray-300',
    'iconColor' => 'text-gray-400',
    'maxHeight' => 'max-h-32',
    'collapsible' => true,
    'copyable' => true,
    'expandable' => true
])

@php
    $jsonId = 'json-' . uniqid();
    $modalId = 'modal-' . uniqid();
    
    // Process the data safely
    $processedData = null;
    $jsonString = '';
    $hasError = false;
    $errorMessage = '';
    
    try {
        if (is_null($data)) {
            $processedData = null;
        } elseif (is_array($data)) {
            $processedData = $data;
        } elseif (is_string($data)) {
            // Try to decode if it's a JSON string
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $processedData = $decoded;
            } else {
                $processedData = $data;
            }
        } else {
            // For any other type, convert to string for safe display
            $processedData = (string) $data;
        }
        
        $jsonString = json_encode($processedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } catch (\Exception $e) {
        $hasError = true;
        $errorMessage = $e->getMessage();
        $jsonString = '[Error displaying data: ' . $errorMessage . ']';
    }
@endphp

@if($processedData !== null || $hasError)
<div class="{{ $bgColor }} border {{ $borderColor }} rounded-md p-2">
    @if($title)
        <div class="flex items-center justify-between mb-1.5">
            <div class="flex items-center space-x-1.5">
                @if($icon)
                    <div class="w-3 h-3 {{ $iconColor }}">
                        {!! $icon !!}
                    </div>
                @endif
                <span class="text-xs font-semibold {{ $titleColor }}">{{ $title }}</span>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex items-center space-x-1">
                @if($copyable && !$hasError)
                    <button 
                        onclick="copyJsonToClipboard('{{ $jsonId }}')"
                        class="p-1 rounded text-xs {{ $iconColor }} hover:text-gray-300 hover:bg-gray-700/50 transition-colors duration-150"
                        title="Copy to clipboard"
                    >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                @endif
                
                @if($expandable && !$hasError)
                    <button 
                        onclick="openJsonModal('{{ $modalId }}')"
                        class="p-1 rounded text-xs {{ $iconColor }} hover:text-gray-300 hover:bg-gray-700/50 transition-colors duration-150"
                        title="View fullscreen"
                    >
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                    </button>
                @endif
                
                @if($collapsible)
                    <button 
                        onclick="toggleJsonCollapse('{{ $jsonId }}')"
                        class="p-1 rounded text-xs {{ $iconColor }} hover:text-gray-300 hover:bg-gray-700/50 transition-colors duration-150"
                        title="Toggle collapse"
                    >
                        <svg class="w-3 h-3 transform transition-transform duration-200" id="chevron-{{ $jsonId }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>
    @endif
    
    <!-- JSON Content -->
    <div id="{{ $jsonId }}" class="json-content">
        <pre class="text-xs {{ $textColor }} bg-gray-900/60 rounded border {{ $borderColor }} p-1.5 overflow-auto {{ $maxHeight }} whitespace-pre-wrap"><code class="language-json">{{ $jsonString }}</code></pre>
    </div>
</div>

@if($expandable && !$hasError)
    <!-- Fullscreen Modal -->
    <div id="{{ $modalId }}" class="fixed inset-0 z-50 hidden bg-black/75 backdrop-blur-sm">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-gray-800 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b border-gray-700">
                    <div class="flex items-center space-x-2">
                        @if($icon)
                            <div class="w-4 h-4 {{ $iconColor }}">
                                {!! $icon !!}
                            </div>
                        @endif
                        <h3 class="text-lg font-semibold text-gray-100">
                            {{ $title ?: 'JSON Data' }}
                        </h3>
                    </div>
                    <div class="flex items-center space-x-2">
                        @if($copyable)
                            <button 
                                onclick="copyJsonToClipboard('{{ $modalId }}-content')"
                                class="p-2 rounded text-gray-400 hover:text-gray-300 hover:bg-gray-700/50 transition-colors duration-150"
                                title="Copy to clipboard"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </button>
                        @endif
                        <button 
                            onclick="closeJsonModal('{{ $modalId }}')"
                            class="p-2 rounded text-gray-400 hover:text-gray-300 hover:bg-gray-700/50 transition-colors duration-150"
                            title="Close"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Content -->
                <div class="flex-1 overflow-auto p-4">
                    <pre id="{{ $modalId }}-content" class="text-sm text-gray-200 bg-gray-900/60 rounded border border-gray-700 p-3 overflow-auto whitespace-pre-wrap"><code class="language-json">{{ $jsonString }}</code></pre>
                </div>
            </div>
        </div>
    </div>
@endif

@endif