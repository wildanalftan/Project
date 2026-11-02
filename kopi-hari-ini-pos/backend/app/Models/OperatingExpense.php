<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatingExpense extends Model
{
    protected $fillable = ['period', 'category', 'amount', 'note'];

    protected function casts(): array
    {
        return [
            'period' => 'date',
            'amount' => 'integer',
        ];
    }
}
