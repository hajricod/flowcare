<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffServiceType extends Model
{
    protected $fillable = ['staff_id', 'service_type_id'];

    public function staff(): BelongsTo { return $this->belongsTo(User::class, 'staff_id'); }
    public function serviceType(): BelongsTo { return $this->belongsTo(ServiceType::class); }
}
