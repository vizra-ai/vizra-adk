<?php

namespace Vizra\VizraADK\Examples\tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * CartManagerTool - Demonstrates Context Integration in Tools
 *
 * This tool shows how tools can read from and update AgentContext
 * to maintain application state across interactions.
 */
class CartManagerTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'cart_manager',
            'description' => 'Manage shopping cart items: add, remove, view cart contents, and calculate totals',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['add_item', 'remove_item', 'view_cart', 'clear_cart', 'calculate_total'],
                        'description' => 'The cart action to perform',
                    ],
                    'item_name' => [
                        'type' => 'string',
                        'description' => 'Name of the item (required for add_item and remove_item)',
                    ],
                    'item_price' => [
                        'type' => 'number',
                        'description' => 'Price of the item (required for add_item)',
                    ],
                    'item_category' => [
                        'type' => 'string',
                        'description' => 'Category of the item (optional for add_item)',
                    ],
                    'item_description' => [
                        'type' => 'string',
                        'description' => 'Brief description of the item (optional for add_item)',
                    ],
                ],
                'required' => ['action'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $action = $arguments['action'] ?? '';

        switch ($action) {
            case 'add_item':
                return $this->addItem($arguments, $context);

            case 'remove_item':
                return $this->removeItem($arguments, $context);

            case 'view_cart':
                return $this->viewCart($context);

            case 'clear_cart':
                return $this->clearCart($context);

            case 'calculate_total':
                return $this->calculateTotal($context);

            default:
                return json_encode([
                    'success' => false,
                    'message' => 'Invalid action. Available actions: add_item, remove_item, view_cart, clear_cart, calculate_total',
                ]);
        }
    }

    private function addItem(array $arguments, AgentContext $context): string
    {
        $itemName = $arguments['item_name'] ?? '';
        $itemPrice = $arguments['item_price'] ?? 0;
        $itemCategory = $arguments['item_category'] ?? 'general';
        $itemDescription = $arguments['item_description'] ?? '';

        if (empty($itemName) || $itemPrice <= 0) {
            return json_encode([
                'success' => false,
                'message' => 'Item name and valid price are required',
            ]);
        }

        // Get current cart from context
        $cart = $context->getState('cart', []);
        $budget = $context->getState('budget');
        $currentTotal = $this->calculateCartTotal($cart);

        // Check budget constraints
        if ($budget !== null && ($currentTotal + $itemPrice) > $budget) {
            $remaining = $budget - $currentTotal;

            return json_encode([
                'success' => false,
                'message' => 'Cannot add item. It would exceed your budget. You have $'.number_format($remaining, 2).' remaining.',
                'cart_total' => $currentTotal,
                'budget' => $budget,
                'remaining_budget' => $remaining,
            ]);
        }

        // Create item
        $item = [
            'id' => uniqid('item_'),
            'name' => $itemName,
            'price' => (float) $itemPrice,
            'category' => $itemCategory,
            'description' => $itemDescription,
            'added_at' => now()->toIso8601String(),
        ];

        // Add to cart
        $cart[] = $item;
        $context->setState('cart', $cart);

        // Update total spent
        $newTotal = $this->calculateCartTotal($cart);
        $context->setState('total_spent', $newTotal);

        $remaining = $budget ? $budget - $newTotal : null;

        return json_encode([
            'success' => true,
            'message' => "Added '{$itemName}' to cart for $".number_format($itemPrice, 2),
            'item' => $item,
            'cart_total' => $newTotal,
            'cart_count' => count($cart),
            'remaining_budget' => $remaining,
        ]);
    }

    private function removeItem(array $arguments, AgentContext $context): string
    {
        $itemName = $arguments['item_name'] ?? '';

        if (empty($itemName)) {
            return json_encode([
                'success' => false,
                'message' => 'Item name is required',
            ]);
        }

        $cart = $context->getState('cart', []);
        $originalCount = count($cart);

        // Find and remove item (case-insensitive)
        $cart = array_filter($cart, function ($item) use ($itemName) {
            return stripos($item['name'], $itemName) === false;
        });

        // Reindex array
        $cart = array_values($cart);

        if (count($cart) === $originalCount) {
            return json_encode([
                'success' => false,
                'message' => "Item '{$itemName}' not found in cart",
            ]);
        }

        // Update context
        $context->setState('cart', $cart);
        $newTotal = $this->calculateCartTotal($cart);
        $context->setState('total_spent', $newTotal);

        $budget = $context->getState('budget');
        $remaining = $budget ? $budget - $newTotal : null;

        return json_encode([
            'success' => true,
            'message' => "Removed '{$itemName}' from cart",
            'cart_total' => $newTotal,
            'cart_count' => count($cart),
            'remaining_budget' => $remaining,
        ]);
    }

    private function viewCart(AgentContext $context): string
    {
        $cart = $context->getState('cart', []);
        $budget = $context->getState('budget');
        $total = $this->calculateCartTotal($cart);

        if (empty($cart)) {
            return json_encode([
                'success' => true,
                'message' => 'Your cart is empty',
                'cart' => [],
                'cart_total' => 0,
                'cart_count' => 0,
                'budget' => $budget,
                'remaining_budget' => $budget,
            ]);
        }

        $remaining = $budget ? $budget - $total : null;

        return json_encode([
            'success' => true,
            'message' => 'Here are your cart contents',
            'cart' => $cart,
            'cart_total' => $total,
            'cart_count' => count($cart),
            'budget' => $budget,
            'remaining_budget' => $remaining,
        ]);
    }

    private function clearCart(AgentContext $context): string
    {
        $cart = $context->getState('cart', []);
        $itemCount = count($cart);

        // Clear cart and reset total
        $context->setState('cart', []);
        $context->setState('total_spent', 0);

        $budget = $context->getState('budget');

        return json_encode([
            'success' => true,
            'message' => "Cart cleared. Removed {$itemCount} items.",
            'cart_total' => 0,
            'cart_count' => 0,
            'budget' => $budget,
            'remaining_budget' => $budget,
        ]);
    }

    private function calculateTotal(AgentContext $context): string
    {
        $cart = $context->getState('cart', []);
        $budget = $context->getState('budget');
        $total = $this->calculateCartTotal($cart);

        $remaining = $budget ? $budget - $total : null;
        $isOverBudget = $budget && $total > $budget;

        $breakdown = array_map(function ($item) {
            return [
                'name' => $item['name'],
                'price' => $item['price'],
                'category' => $item['category'] ?? 'general',
            ];
        }, $cart);

        return json_encode([
            'success' => true,
            'message' => 'Cart total calculated',
            'cart_total' => $total,
            'cart_count' => count($cart),
            'budget' => $budget,
            'remaining_budget' => $remaining,
            'over_budget' => $isOverBudget,
            'breakdown' => $breakdown,
        ]);
    }

    private function calculateCartTotal(array $cart): float
    {
        return array_sum(array_column($cart, 'price'));
    }
}
