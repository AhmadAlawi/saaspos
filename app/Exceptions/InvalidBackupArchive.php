<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown while *validating* a backup before any destructive work begins —
 * a missing/corrupt archive, a missing or unreadable manifest, a checksum
 * mismatch, or an incompatible app/schema version.
 *
 * The distinction matters: an {@see InvalidBackupArchive} means the live
 * install is untouched and the operator can safely pick another file. A
 * failure thrown *after* the database drop is a different, dangerous class
 * of error that the restore wizard surfaces with the rollback path.
 */
class InvalidBackupArchive extends RuntimeException
{
}
