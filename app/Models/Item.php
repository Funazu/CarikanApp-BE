<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Item extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'secret_feature',
        'image_url',
        'type',
        'status',
        'event_time',
        'contact_info',
        'storage_location',
        'user_id',
        'category_id',
        'location_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function claims()
    {
        return $this->hasMany(Claim::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
