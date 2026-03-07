<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasUuids;

    protected $fillable = [
        'id', 'customer_id', 'branch_id', 'service_type_id', 'slot_id', 'staff_id',
        'status', 'notes', 'attachment_path', 'attachment_original_name', 'queue_number',
    ];

    public function customer() { return $this->belongsTo(User::class, 'customer_id'); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function serviceType() { return $this->belongsTo(ServiceType::class); }
    public function slot() { return $this->belongsTo(Slot::class); }
    public function staff() { return $this->belongsTo(User::class, 'staff_id'); }
}
