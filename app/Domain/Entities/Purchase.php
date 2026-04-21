<?php

namespace App\Domain\Entities;

class Purchase
{
    public function __construct(
        public readonly int $purchaseorderid,
        public readonly string $subject,
        public readonly string $ponumber,
        public readonly int $projectid,
        public readonly ?string $projectname,
        public readonly int $vendorid,
        public readonly ?string $vendorname,
        public readonly string $postatus,
        public readonly ?string $podate,
        public readonly ?string $validtill,
        public readonly float $subtotal,
        public readonly float $taxtotal,
        public readonly float $total,
        public readonly array $items,
        public readonly ?string $description,
        public readonly int $assigned_user_id,
        public readonly ?string $assigned_user_name,
        public readonly ?string $createdtime,
        public readonly ?string $modifiedtime,
        public readonly ?float $projectBudget,
        public readonly ?float $projectSpent,
    ) {}

    public function toArray(): array
    {
        return [
            'purchaseorderid' => $this->purchaseorderid,
            'subject' => $this->subject,
            'ponumber' => $this->ponumber,
            'projectid' => $this->projectid,
            'projectname' => $this->projectname,
            'vendorid' => $this->vendorid,
            'vendorname' => $this->vendorname,
            'status' => $this->postatus,
            'podate' => $this->podate,
            'validtill' => $this->validtill,
            'subtotal' => $this->subtotal,
            'taxtotal' => $this->taxtotal,
            'total' => $this->total,
            'items' => $this->items,
            'description' => $this->description,
            'assigned_user_id' => $this->assigned_user_id,
            'assigned_user_name' => $this->assigned_user_name,
            'createdtime' => $this->createdtime,
            'modifiedtime' => $this->modifiedtime,
            'budget' => [
                'projectBudget' => $this->projectBudget,
                'projectSpent' => $this->projectSpent,
                'percentageUsed' => $this->projectBudget > 0 
                    ? round(($this->projectSpent / $this->projectBudget) * 100, 2) 
                    : 0,
            ],
        ];
    }

    public function getStatusBadge(): string
    {
        $colors = [
            'Draft' => 'bg-gray-500/10 text-gray-600',
            'Pending Approval' => 'bg-warning/10 text-warning',
            'Approved' => 'bg-blue-500/10 text-blue-600',
            'Received' => 'bg-emerald-500/10 text-emerald-600',
            'Cancelled' => 'bg-destructive/10 text-destructive',
        ];
        return $colors[$this->postatus] ?? 'bg-gray-500/10 text-gray-600';
    }
}