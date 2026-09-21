<?php
declare(strict_types=1);

namespace FixListed\Core;

use RuntimeException;

/**
 * Stops the request and sends a prepared Response.
 *
 * For the case where a helper several calls deep finds a reason to redirect —
 * a tradesperson whose listing is not live trying to reach the quote form.
 * The alternative is returning nullable Responses up through every layer and
 * checking each one, which is the same logic spread over more places and
 * forgotten in at least one of them.
 *
 * Caught in the front controller beside NotFound. Deliberately narrow: it
 * carries a Response and nothing else.
 */
final class HaltWith extends RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('halted');
    }
}
