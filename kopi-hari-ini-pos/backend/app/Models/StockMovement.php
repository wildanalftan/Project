<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'type',
        'reference_type',
        'reference_id',
        'qty_before',
        'qty_change',
        'qty_after',
        'cost_before',
        'cost_after',
        'note',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_before'  => 'float',
            'qty_change'  => 'float',
            'qty_after'   => 'float',
            'cost_before' => 'integer',
            'cost_after'  => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    // ─── Append-only guard ────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('StockMovement records are append-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('StockMovement records are append-only and cannot be deleted.');
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
