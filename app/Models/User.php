<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasUuids, Notifiable;

    public const ROLE_ADMIN = 'ADMIN';
    public const ROLE_BRANCH_MANAGER = 'BRANCH_MANAGER';
    public const ROLE_STAFF = 'STAFF';
    public const ROLE_CUSTOMER = 'CUSTOMER';

    protected $fillable = [
        'id', 'username', 'email', 'password', 'full_name', 'phone',
        'role', 'branch_id', 'id_image_path', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool { return $this->role === self::ROLE_ADMIN; }
    public function isBranchManager(): bool { return $this->role === self::ROLE_BRANCH_MANAGER; }
    public function isStaff(): bool { return $this->role === self::ROLE_STAFF; }
    public function isCustomer(): bool { return $this->role === self::ROLE_CUSTOMER; }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function appointments(): HasMany { return $this->hasMany(Appointment::class, 'customer_id'); }
    public function staffServiceTypes(): HasMany { return $this->hasMany(StaffServiceType::class, 'staff_id'); }
    public function slots(): HasMany { return $this->hasMany(Slot::class, 'staff_id'); }
}
