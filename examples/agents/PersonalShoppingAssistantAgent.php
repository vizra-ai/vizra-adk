<?php

namespace Vizra\VizraADK\Examples\agents;

use Generator;
use Prism\Prism\Text\Response;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Examples\tools\CartManagerTool;
use Vizra\VizraADK\System\AgentContext;

/**
 * PersonalShoppingAssistantAgent - Demonstrates Context Usage
 *
 * This agent shows how to effectively use AgentContext to maintain
 * shopping state, user preferences, and conversation flow across
 * multiple interactions.
 *
 * Key Context Features Demonstrated:
 * - Session state management (cart, budget, preferences)
 * - Lifecycle hooks (beforeLlmCall, afterLlmResponse)
 * - Context-aware tool integration
 * - Dynamic prompt injection based on context
 */
class PersonalShoppingAssistantAgent extends BaseLlmAgent
{
    protected string $name = 'shopping_assistant';

    protected string $description = 'A personal shopping assistant that helps users find products while maintaining cart state and preferences';

    protected string $model = 'gemini-2.0-flash';

    protected ?float $temperature = 0.7;

    protected ?int $maxTokens = 1000;

    protected array $tools = [
        CartManagerTool::class,
    ];

    protected string $instructions = 'You are a friendly and helpful personal shopping assistant. Your goal is to help users find the perfect products within their budget while learning their preferences.

Key responsibilities:
- Help users build a shopping cart within their specified budget
- Learn and remember user preferences throughout the conversation
- Provide personalized product recommendations
- Keep track of cart contents and remaining budget
- Use the cart_manager tool to add/remove items and calculate totals

Always be helpful, friendly, and budget-conscious. When users mention preferences (brands, styles, price ranges, etc.), remember them for future recommendations.

IMPORTANT: When users provide information about preferences, budget, or shopping goals, include this JSON data at the end of your response (after your main response):

```json
{
  "context_update": {
    "shopping_goals": {
      "budget": 200,
      "purpose": "gifts for family", 
      "target_count": 2
    },
    "preferences": {
      "recipients": {
        "mom": "loves gardening, eco-friendly products",
        "brother": "tech-savvy, likes gadgets"
      },
      "brands": ["Nike", "Apple"],
      "price_sensitivity": "budget-conscious"
    }
  }
}
```

Only include fields that are new or updated. Always place JSON after your response in a code block. This helps me track context accurately.';

    /**
     * Override to inject context into the system prompt
     */
    public function getInstructionsWithMemory(AgentContext $context): string
    {
        // Get the base instructions
        $instructions = parent::getInstructionsWithMemory($context);

        // Get current context state
        $cart = $context->getState('cart', []);
        $budget = $context->getState('budget');
        $preferences = $context->getState('preferences', []);
        $totalSpent = $context->getState('total_spent', 0);

        // Build context summary for the agent
        $contextSummary = $this->buildContextSummary($cart, $budget, $preferences, $totalSpent);

        return $instructions."\n\n".$contextSummary;
    }

    /**
     * After each LLM response, extract and update context from JSON
     */
    public function afterLlmResponse(Response|Generator $response, AgentContext $context): mixed
    {
        if ($response instanceof Response) {
            $responseText = $response->text;

            // Parse structured JSON from LLM response
            $this->parseStructuredResponse($responseText, $context);

            // Track conversation history for better recommendations
            $this->updateConversationHistory($responseText, $context);

            // Note: We don't clean the JSON from the response here since Response is readonly
            // The frontend or consumer can clean it if needed, or we can handle it via a different approach
        }

        return $response;
    }

    /**
     * Build a context summary to inject into prompts
     */
    private function buildContextSummary(array $cart, ?float $budget, array $preferences, float $totalSpent): string
    {
        $summary = "\n=== CURRENT CONTEXT ===\n";

        // Budget information
        if ($budget !== null) {
            $remaining = $budget - $totalSpent;
            $summary .= 'Budget: $'.number_format($budget, 2)."\n";
            $summary .= 'Spent: $'.number_format($totalSpent, 2)."\n";
            $summary .= 'Remaining: $'.number_format($remaining, 2)."\n\n";
        }

        // Current cart
        if (! empty($cart)) {
            $summary .= "Current Cart:\n";
            foreach ($cart as $item) {
                $summary .= "- {$item['name']}: $".number_format($item['price'], 2)."\n";
            }
            $summary .= "\n";
        } else {
            $summary .= "Cart: Empty\n\n";
        }

        // User preferences
        if (! empty($preferences)) {
            $summary .= "User Preferences:\n";
            foreach ($preferences as $category => $prefs) {
                if (is_array($prefs)) {
                    $summary .= "- {$category}: ".implode(', ', $prefs)."\n";
                } else {
                    $summary .= "- {$category}: {$prefs}\n";
                }
            }
            $summary .= "\n";
        }

        $summary .= "Use this context to provide personalized recommendations and maintain conversation continuity.\n";
        $summary .= "========================\n\n";

        return $summary;
    }

    /**
     * Parse structured JSON from LLM response and update context
     */
    private function parseStructuredResponse(string $responseText, AgentContext $context): void
    {
        // Extract JSON from response (looking for ```json code blocks)
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $responseText, $matches)) {
            $jsonData = json_decode($matches[1], true);

            if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['context_update'])) {
                $this->updateContextFromJson($jsonData['context_update'], $context);
            }
        }
    }

    /**
     * Update context state from parsed JSON data
     */
    private function updateContextFromJson(array $contextUpdate, AgentContext $context): void
    {
        // Update shopping goals
        if (isset($contextUpdate['shopping_goals'])) {
            $goals = $contextUpdate['shopping_goals'];

            if (isset($goals['budget'])) {
                $context->setState('budget', (float) $goals['budget']);
            }

            if (isset($goals['purpose'])) {
                $context->setState('shopping_purpose', $goals['purpose']);
            }

            if (isset($goals['target_count'])) {
                $context->setState('target_count', (int) $goals['target_count']);
            }
        }

        // Update preferences
        if (isset($contextUpdate['preferences'])) {
            $currentPreferences = $context->getState('preferences', []);
            $newPreferences = $contextUpdate['preferences'];

            // Merge recipients
            if (isset($newPreferences['recipients'])) {
                $currentPreferences['recipients'] = array_merge(
                    $currentPreferences['recipients'] ?? [],
                    $newPreferences['recipients']
                );
            }

            // Update brands (merge arrays)
            if (isset($newPreferences['brands'])) {
                $currentBrands = $currentPreferences['brands'] ?? [];
                $newBrands = is_array($newPreferences['brands']) ? $newPreferences['brands'] : [$newPreferences['brands']];
                $currentPreferences['brands'] = array_unique(array_merge($currentBrands, $newBrands));
            }

            // Update price sensitivity
            if (isset($newPreferences['price_sensitivity'])) {
                $currentPreferences['price_sensitivity'] = $newPreferences['price_sensitivity'];
            }

            // Update colors (merge arrays)
            if (isset($newPreferences['colors'])) {
                $currentColors = $currentPreferences['colors'] ?? [];
                $newColors = is_array($newPreferences['colors']) ? $newPreferences['colors'] : [$newPreferences['colors']];
                $currentPreferences['colors'] = array_unique(array_merge($currentColors, $newColors));
            }

            // Update styles (merge arrays)
            if (isset($newPreferences['styles'])) {
                $currentStyles = $currentPreferences['styles'] ?? [];
                $newStyles = is_array($newPreferences['styles']) ? $newPreferences['styles'] : [$newPreferences['styles']];
                $currentPreferences['styles'] = array_unique(array_merge($currentStyles, $newStyles));
            }

            $context->setState('preferences', $currentPreferences);
        }
    }

    /**
     * Track conversation history for better context
     */
    private function updateConversationHistory(string $responseText, AgentContext $context): void
    {
        $history = $context->getState('conversation_highlights', []);

        // Store key moments in the conversation
        if (str_contains($responseText, 'added to cart') || str_contains($responseText, 'Added to cart')) {
            $history[] = [
                'type' => 'cart_addition',
                'timestamp' => now()->toIso8601String(),
                'content' => 'Item added to cart',
            ];
        }

        if (str_contains($responseText, 'recommendation') || str_contains($responseText, 'suggest')) {
            $history[] = [
                'type' => 'recommendation',
                'timestamp' => now()->toIso8601String(),
                'content' => 'Made product recommendation',
            ];
        }

        // Keep only recent highlights (last 10)
        $context->setState('conversation_highlights', array_slice($history, -10));
    }
}
