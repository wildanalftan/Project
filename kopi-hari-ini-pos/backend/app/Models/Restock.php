<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restock extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'qty',
        'unit_cost',
        'supplier',
        'cost_before',
        'cost_after',
        'note',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'qty'         => 'float',
            'unit_cost'   => 'integer',
            'cost_before' => 'integer',
            'cost_after'  => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class);
    }
}
