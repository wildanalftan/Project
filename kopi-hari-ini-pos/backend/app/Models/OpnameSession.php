<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpnameSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_date',
        'status',
        'locked_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'locked_at'    => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OpnameItem::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
