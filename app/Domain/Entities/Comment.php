<?php

namespace App\Domain\Entities;

class Comment
{
    public function __construct(
        public int $commentid,
        public string $commentcontent,
        public int $related_to,
        public string $parent_comments,
        public ?string $customer,
        public ?string $userid,
        public ?string $reasontoedit,
        public ?string $parent_commentid,
        public ?string $createdtime,
        public ?string $modifiedtime,
        public ?string $assigned_user_name,
        public ?string $assigned_user_email
    ) {}
}