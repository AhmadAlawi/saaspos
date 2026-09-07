<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a downloaded update zip fails verification — a SHA-256 mismatch
 * (corrupt / truncated download) or a bad Ed25519 signature (the file didn't
 * come from us, or was tampered with in transit).
 *
 * The update must abort BEFORE any file is replaced: an unverified package is
 * exactly the man-in-the-middle / compromised-feed case the signature exists
 * to stop.
 */
class InvalidUpdateSignature extends RuntimeException
{
}
