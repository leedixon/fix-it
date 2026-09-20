<?php
declare(strict_types=1);

namespace FixListed\Core;

use RuntimeException;

/**
 * Thrown when a slug, reference or city does not exist in this market.
 *
 * An exception rather than a returned 404 response so that a controller can
 * bail out of the middle of assembling a page — and so that the one place
 * that renders the 404 page is the front controller, not every controller
 * that can miss.
 */
final class NotFound extends RuntimeException
{
}
