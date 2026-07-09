<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Claim extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'item_id',
        'claimer_id',
        'proof_description',
        'proof_image_url',
        'status',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function claimer()
    {
        return $this->belongsTo(User::class, 'claimer_id');
    }
}
