<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Slot extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['id', 'branch_id', 'service_type_id', 'staff_id', 'start_at', 'end_at', 'capacity', 'is_active'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function branch() { return $this->belongsTo(Branch::class); }
    public function serviceType() { return $this->belongsTo(ServiceType::class); }
    public function staff() { return $this->belongsTo(User::class, 'staff_id'); }
    public function appointment() { return $this->hasOne(Appointment::class); }
}
