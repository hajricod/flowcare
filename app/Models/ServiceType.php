<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'branch_id', 'name', 'description', 'duration_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch() { return $this->belongsTo(Branch::class); }
    public function slots() { return $this->hasMany(Slot::class); }
    public function staffServiceTypes() { return $this->hasMany(StaffServiceType::class); }
}
