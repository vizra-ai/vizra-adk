<?php

namespace Vizra\VizraADK\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vizra\VizraADK\Exceptions\AgentNotFoundException;
use Vizra\VizraADK\Facades\Agent;

class OpenAICompatibleController extends Controller
{
    /**
     * Handle OpenAI-compatible chat completions request
     *
     * @return JsonResponse|StreamedResponse
     */
    public function chatCompletions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'model' => 'required|string',
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string|in:system,user,assistant',
            'messages.*.content' => 'required|string',
            'stream' => 'boolean',
            'temperature' => 'nullable|numeric|between:0,2',
            'max_tokens' => 'nullable|integer|min:1',
            'top_p' => 'nullable|numeric|between:0,1',
            'n' => 'nullable|integer|min:1|max:1', // We only support n=1 for now
            'user' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Invalid request format', 400, $validator->errors()->toArray());
        }

        // Map model to agent name
        $agentName = $this->mapModelToAgent($request->input('model'));

        if (! Agent::hasAgent($agentName)) {
            return $this->errorResponse(
                "The model '{$request->input('model')}' does not exist or you do not have access to it.",
                404
            );
        }

        // Extract messages
        $messages = $request->input('messages', []);
        $lastUserMessage = $this->getLastUserMessage($messages);

        if (! $lastUserMessage) {
            return $this->errorResponse('No user message found in the conversation', 400);
        }

        // Generate or use existing session ID
        $sessionId = $this->getOrCreateSessionId($request);

        try {
            // Get the agent executor
            $executor = Agent::named($agentName);

            // Apply optional parameters
            if ($request->has('temperature')) {
                $executor->setTemperature($request->input('temperature'));
            }
            if ($request->has('max_tokens')) {
                $executor->setMaxTokens($request->input('max_tokens'));
            }
            if ($request->has('top_p')) {
                $executor->setTopP($request->input('top_p'));
            }

            // Set streaming if requested
            $stream = $request->input('stream', false);
            if ($stream) {
                $executor->setStreaming(true);
            }

            // Add conversation context
            $context = $this->buildContext($messages, $sessionId, $request->input('user'));

            // Execute the agent
            if ($stream) {
                return $this->streamResponse($executor, $lastUserMessage, $context, $request->input('model'));
            } else {
                $response = $executor->withContext($context)->execute($lastUserMessage);

                return $this->formatResponse($response, $request->input('model'));
            }

        } catch (AgentNotFoundException $e) {
            return $this->errorResponse("Model '{$request->input('model')}' not found", 404);
        } catch (\Throwable $e) {
            logger()->error('OpenAI-compatible API error: '.$e->getMessage(), ['exception' => $e]);

            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Map OpenAI model names to agent names
     */
    protected function mapModelToAgent(string $model): string
    {
        // You can customize this mapping based on your needs
        $mapping = config('vizra-adk.openai_model_mapping', []);

        // If there's a specific mapping, use it
        if (isset($mapping[$model])) {
            return $mapping[$model];
        }

        // Otherwise, treat the model name as the agent name
        // Convert common OpenAI model names to a generic agent if needed
        if (Str::startsWith($model, 'gpt-')) {
            return config('vizra-adk.default_chat_agent', $model);
        }

        return $model;
    }

    /**
     * Get the last user message from the messages array
     */
    protected function getLastUserMessage(array $messages): ?string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if ($messages[$i]['role'] === 'user') {
                return $messages[$i]['content'];
            }
        }

        return null;
    }

    /**
     * Build context from OpenAI messages format
     */
    protected function buildContext(array $messages, string $sessionId, ?string $user = null): array
    {
        $context = [
            'session_id' => $sessionId,
            'conversation_history' => [],
        ];

        if ($user) {
            $context['user_id'] = $user;
        }

        // Convert messages to conversation history
        foreach ($messages as $message) {
            $context['conversation_history'][] = [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }

        return $context;
    }

    /**
     * Get or create session ID
     */
    protected function getOrCreateSessionId(Request $request): string
    {
        // Use user ID as session if provided
        if ($request->has('user')) {
            return 'openai_'.$request->input('user');
        }

        // Otherwise generate a new session ID
        return 'openai_'.Str::uuid()->toString();
    }

    /**
     * Format response in OpenAI format
     */
    protected function formatResponse(string $content, string $model): JsonResponse
    {
        $response = [
            'id' => 'chatcmpl-'.Str::random(29),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $model,
            'system_fingerprint' => null,
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $content,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => $this->estimateTokens($content) / 4, // Rough estimate
                'completion_tokens' => $this->estimateTokens($content),
                'total_tokens' => $this->estimateTokens($content) * 1.25,
            ],
        ];

        return response()->json($response);
    }

    /**
     * Stream response in OpenAI SSE format
     */
    protected function streamResponse($executor, string $input, array $context, string $model): StreamedResponse
    {
        return response()->stream(function () use ($executor, $input, $context, $model) {
            // Send headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no');

            $streamId = 'chatcmpl-'.Str::random(29);

            try {
                // Execute with streaming
                $stream = $executor->withContext($context)->execute($input);

                $fullContent = '';

                // Send initial chunk
                $this->sendChunk([
                    'id' => $streamId,
                    'object' => 'chat.completion.chunk',
                    'created' => time(),
                    'model' => $model,
                    'system_fingerprint' => null,
                    'choices' => [
                        [
                            'index' => 0,
                            'delta' => [
                                'role' => 'assistant',
                                'content' => '',
                            ],
                            'finish_reason' => null,
                        ],
                    ],
                ]);

                // Stream the response
                foreach ($stream as $chunk) {
                    $fullContent .= $chunk;

                    $this->sendChunk([
                        'id' => $streamId,
                        'object' => 'chat.completion.chunk',
                        'created' => time(),
                        'model' => $model,
                        'choices' => [
                            [
                                'index' => 0,
                                'delta' => [
                                    'content' => $chunk,
                                ],
                                'finish_reason' => null,
                            ],
                        ],
                    ]);

                    // Flush output
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                // Send final chunk
                $this->sendChunk([
                    'id' => $streamId,
                    'object' => 'chat.completion.chunk',
                    'created' => time(),
                    'model' => $model,
                    'choices' => [
                        [
                            'index' => 0,
                            'delta' => [],
                            'finish_reason' => 'stop',
                        ],
                    ],
                ]);

                // Send done signal
                echo "data: [DONE]\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

            } catch (\Throwable $e) {
                // Send error in SSE format
                $this->sendChunk([
                    'error' => [
                        'message' => $e->getMessage(),
                        'type' => 'server_error',
                        'code' => 'internal_error',
                    ],
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Send a chunk in SSE format
     */
    protected function sendChunk(array $data): void
    {
        echo 'data: '.json_encode($data)."\n\n";
    }

    /**
     * Format error response in OpenAI format
     */
    protected function errorResponse(string $message, int $code, array $details = []): JsonResponse
    {
        $error = [
            'error' => [
                'message' => $message,
                'type' => $this->getErrorType($code),
                'code' => $this->getErrorCode($code),
            ],
        ];

        if (! empty($details)) {
            $error['error']['details'] = $details;
        }

        return response()->json($error, $code);
    }

    /**
     * Get OpenAI error type from HTTP status code
     */
    protected function getErrorType(int $code): string
    {
        return match ($code) {
            400 => 'invalid_request_error',
            401 => 'authentication_error',
            403 => 'permission_error',
            404 => 'not_found_error',
            429 => 'rate_limit_error',
            default => 'server_error',
        };
    }

    /**
     * Get OpenAI error code from HTTP status code
     */
    protected function getErrorCode(int $code): string
    {
        return match ($code) {
            400 => 'invalid_request',
            404 => 'model_not_found',
            default => 'internal_error',
        };
    }

    /**
     * Rough token estimation (4 characters ≈ 1 token)
     */
    protected function estimateTokens(string $text): int
    {
        return (int) ceil(strlen($text) / 4);
    }
}
