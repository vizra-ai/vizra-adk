<?php

namespace Vizra\VizraADK\Evaluations\Assertions;

/**
 * Assertion to check if a response contains properly formatted prices.
 *
 * Supports various currency symbols and formats.
 */
class PriceFormatAssertion extends BaseAssertion
{
    /**
     * Assert that the response contains a properly formatted price.
     *
     * @param  string  $response  The LLM response to evaluate
     * @param  mixed  ...$params  Optional currency symbol (default: '$')
     */
    public function assert(string $response, ...$params): array
    {
        $currency = $params[0] ?? '$';

        // Escape special regex characters in currency symbol
        $escapedCurrency = preg_quote($currency, '/');

        // Pattern to match various price formats:
        // $100, $100.00, $1,000, $1,000.50, etc.
        $pattern = '/'.$escapedCurrency.'\s*[\d,]+(?:\.\d{1,2})?/';

        $hasPrice = preg_match($pattern, $response, $matches);

        if ($hasPrice) {
            return $this->result(
                true,
                "Response contains a price in {$currency} format",
                "price like {$currency}XX.XX",
                "found: {$matches[0]}"
            );
        }

        // Also check for written formats like "100 dollars", "fifty euros"
        $writtenCurrencies = [
            '$' => 'dollar',
            '€' => 'euro',
            '£' => 'pound',
            '¥' => 'yen',
        ];

        $currencyWord = $writtenCurrencies[$currency] ?? strtolower(trim($currency));
        $writtenPattern = '/\b\d+(?:\.\d+)?\s*'.preg_quote($currencyWord, '/').'s?\b/i';

        if (preg_match($writtenPattern, $response, $matches)) {
            return $this->result(
                true,
                'Response contains a price in written format',
                "price in {$currency} format",
                "found: {$matches[0]}"
            );
        }

        return $this->result(
            false,
            "Response should contain a price in {$currency} format",
            "price like {$currency}XX.XX",
            'no price found'
        );
    }
}
