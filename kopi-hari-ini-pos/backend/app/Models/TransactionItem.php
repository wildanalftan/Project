<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_name',
        'qty',
        'unit_price',
        'unit_cost',
        'line_total',
        'line_cogs',
        'line_profit',
    ];

    protected function casts(): array
    {
        return [
            'qty'        => 'float',
            'unit_price' => 'integer',
            'unit_cost'  => 'integer',
            'line_total' => 'integer',
            'line_cogs'  => 'integer',
            'line_profit'=> 'integer',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
