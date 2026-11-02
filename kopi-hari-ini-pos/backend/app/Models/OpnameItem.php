<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpnameItem extends Model
{
    protected $fillable = [
        'opname_session_id',
        'product_id',
        'system_qty',
        'physical_qty',
        'difference',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'system_qty'   => 'float',
            'physical_qty' => 'float',
            'difference'   => 'float',
        ];
    }

    public function session()
    {
        return $this->belongsTo(OpnameSession::class, 'opname_session_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
