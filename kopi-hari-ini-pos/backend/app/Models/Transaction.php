<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'invoice_no',
        'client_uuid',
        'user_id',
        'table_label',
        'subtotal',
        'tax_amount',
        'total',
        'payment_method',
        'paid_amount',
        'change_amount',
        'total_cogs',
        'gross_profit',
        'status',
        'is_offline',
        'occurred_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'      => 'integer',
            'tax_amount'    => 'integer',
            'total'         => 'integer',
            'paid_amount'   => 'integer',
            'change_amount' => 'integer',
            'total_cogs'    => 'integer',
            'gross_profit'  => 'integer',
            'is_offline'    => 'boolean',
            'occurred_at'   => 'datetime',
            'synced_at'     => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }
}
