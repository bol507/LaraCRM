<?php

namespace App\Domain\Exceptions\Comment;

use RuntimeException;

/**
 * Thrown when the record a comment would be attached to (or whose comments
 * are being listed) does not exist or has been soft-deleted.
 */
class CommentTargetNotFoundException extends RuntimeException
{
    public static function for(string $module, int $relatedId): self
    {
        return new self(
            "Related {$module} record {$relatedId} not found or has been deleted"
        );
    }
}