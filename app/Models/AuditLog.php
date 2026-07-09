<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'item_id',
        'actor_id',
        'action',
        'old_value',
        'new_value',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
