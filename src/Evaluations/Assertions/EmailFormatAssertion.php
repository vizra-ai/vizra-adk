<?php

namespace Vizra\VizraADK\Evaluations\Assertions;

/**
 * Assertion to check if a response contains valid email addresses.
 *
 * Can validate single or multiple email addresses in a response.
 */
class EmailFormatAssertion extends BaseAssertion
{
    /**
     * Assert that the response contains valid email address(es).
     *
     * @param  string  $response  The LLM response to evaluate
     * @param  mixed  ...$params  Optional: minimum number of emails expected (default: 1)
     */
    public function assert(string $response, ...$params): array
    {
        $minEmails = $params[0] ?? 1;

        // RFC-compliant email regex pattern (simplified but effective)
        $pattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/';

        preg_match_all($pattern, $response, $matches);
        $emailsFound = count($matches[0]);

        if ($emailsFound >= $minEmails) {
            $emailList = implode(', ', array_unique($matches[0]));

            return $this->result(
                true,
                $minEmails > 1
                    ? "Response contains at least {$minEmails} email addresses"
                    : 'Response contains valid email address',
                $minEmails > 1 ? "≥ {$minEmails} emails" : 'valid email',
                "found {$emailsFound}: {$emailList}"
            );
        }

        return $this->result(
            false,
            $minEmails > 1
                ? "Response should contain at least {$minEmails} email addresses"
                : 'Response should contain a valid email address',
            $minEmails > 1 ? "≥ {$minEmails} emails" : 'valid email',
            $emailsFound > 0
                ? "found only {$emailsFound}"
                : 'no email found'
        );
    }
}
