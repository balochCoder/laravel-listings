<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use HasFactory, SoftDeletes;

    const APPROVAL_PENDING = 1;
    const APPROVAL_APPROVED = 2;

    public $casts = [
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'approval_status' => 'integer',
        'hidden' => 'boolean',
        'price_per_day' => 'integer',
        'monthly_discount' => 'integer',
    ];
    // Office belongs to user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    // Office has many reservations
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
    // Office has many images -> we used polymorphic relation


    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'resource');
    }

    // Office has a featuredImage

    public function featuredImage()
    {
        return $this->belongsTo(Image::class,'featured_image_id');
    }

    // office belongs to Many tags

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'office_tags');
    }

    // Scope for nearest office

    public function scopeNearestTo(Builder $builder, $lat, $lng)
    {
        return $builder
        ->select()
        ->orderByRaw(
            'POW(69.1 *(lat-?),2) + POW(69.1 * (?-lng) * COS(lat/57.3),2)',
            [
                $lat,
                $lng
            ]
        );
    }
}
