<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasUuids;

    protected $fillable = ['id', 'name', 'city', 'address', 'timezone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function serviceTypes() { return $this->hasMany(ServiceType::class); }
    public function staff() { return $this->hasMany(User::class)->whereIn('role', ['STAFF', 'BRANCH_MANAGER']); }
    public function slots() { return $this->hasMany(Slot::class); }
    public function appointments() { return $this->hasMany(Appointment::class); }
}
