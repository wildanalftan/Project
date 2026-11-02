<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockBatch extends Model
{
    protected $fillable = [
        'product_id',
        'restock_id',
        'qty_in',
        'qty_remaining',
        'unit_cost',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_in'        => 'float',
            'qty_remaining' => 'float',
            'unit_cost'     => 'integer',
            'received_at'   => 'datetime',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function restock()
    {
        return $this->belongsTo(Restock::class);
    }
}
