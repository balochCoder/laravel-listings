<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 1;
    const STATUS_CANCELLED = 2;

    protected $casts = [
        'price' => 'integer',
        'status' => 'integer',
        'start_date' => 'immutable_date',
        'end_date' => 'immutable_date',
        'wifi_password' => 'encrypted'
    ];

    // Reservation belongs to user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    // Reservation belongs to office
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function scopeActiveBetween($query, $from, $to)
    {
        $query->whereStatus(Reservation::STATUS_ACTIVE)
            ->betweenDates($from, $to);
    }
    public function scopeBetweenDates($query, $from, $to)
    {
        $query->where(
            function ($query) use ($from, $to) {
                $query
                    ->whereBetween('start_date', [$from,  $to])
                    ->orWhereBetween('end_date', [$from,  $to])
                    ->orWhere(function ($query) use ($to, $from) {
                        $query->where('start_date', '<', $from)
                            ->where('end_date', '>', $to);
                    });
            }
        );
    }
}
