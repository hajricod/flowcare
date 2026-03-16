<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Slot;

class SlotRetentionCleanupService
{
    public function cleanup(?string $actorId = null, string $actorRole = 'SYSTEM'): int
    {
        $retentionDays = (int) Setting::get('soft_delete_retention_days', 30);
        $cutoff = now()->subDays($retentionDays);

        $slots = Slot::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->get();

        foreach ($slots as $slot) {
            Appointment::where('slot_id', $slot->id)->update(['slot_id' => null]);
            AuditLog::log($actorId, $actorRole, 'SLOT_HARD_DELETED', 'SLOT', $slot->id, [
                'reason' => 'retention_cleanup',
                'source' => 'scheduler',
            ], $slot->branch_id);
            $slot->forceDelete();
        }

        return $slots->count();
    }
}