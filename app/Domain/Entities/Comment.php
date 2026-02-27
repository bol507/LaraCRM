<?php

namespace App\Domain\Entities;

class Comment
{
    public function __construct(
        public int $commentid,
        public string $commentcontent,
        public int $related_to,
        public ?int $parent_comments,
        public ?int $customer,  
        public ?int $userid,  
         public ?string $reasontoedit,
        public ?int $is_private,
        public ?string $filename,
        public ?int $related_email_id,
        public ?string $createdtime,
        public ?string $modifiedtime,
        public ?string $assigned_user_name,
        public ?string $assigned_user_email
    ) {}
}