<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'id', 'actor_id', 'actor_role', 'action_type', 'entity_type',
        'entity_id', 'metadata', 'branch_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function log(
        ?string $actorId,
        ?string $actorRole,
        string $actionType,
        string $entityType,
        string $entityId,
        ?array $metadata = null,
        ?string $branchId = null
    ): void {
        self::create([
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'branch_id' => $branchId,
            'created_at' => now(),
        ]);
    }
}
