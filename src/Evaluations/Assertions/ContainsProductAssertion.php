<?php

namespace Vizra\VizraADK\Evaluations\Assertions;

/**
 * Assertion to check if a response mentions a specific product name.
 *
 * This is a simple example of a custom assertion that can be used
 * to validate product-related responses.
 */
class ContainsProductAssertion extends BaseAssertion
{
    /**
     * Assert that the response contains a product name.
     *
     * @param  string  $response  The LLM response to evaluate
     * @param  mixed  ...$params  The product name should be the first parameter
     */
    public function assert(string $response, ...$params): array
    {
        $productName = $params[0] ?? '';

        if (empty($productName)) {
            return $this->result(
                false,
                'Product name parameter is required'
            );
        }

        // Case-insensitive search for the product name
        $contains = stripos($response, $productName) !== false;

        return $this->result(
            $contains,
            "Response should mention the product '{$productName}'",
            "contains '{$productName}'",
            $contains ? "found '{$productName}'" : 'product not mentioned'
        );
    }
}
