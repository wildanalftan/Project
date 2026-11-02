<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'unit',
        'selling_price',
        'is_active',
        'low_stock_threshold',
    ];

    // current_cost and stock_qty are ONLY modified by StockService — never mass-assigned
    protected $guarded = ['current_cost', 'stock_qty'];

    protected function casts(): array
    {
        return [
            'selling_price'       => 'integer',
            'current_cost'        => 'integer',
            'stock_qty'           => 'float',
            'low_stock_threshold' => 'float',
            'is_active'           => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class)->orderBy('received_at');
    }

    public function restocks()
    {
        return $this->hasMany(Restock::class);
    }

    public function transactionItems()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class)->orderByDesc('occurred_at');
    }

    public function opnameItems()
    {
        return $this->hasMany(OpnameItem::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function getStockStatus(): string
    {
        if ($this->stock_qty <= 0) {
            return 'habis';
        }
        if ($this->stock_qty <= $this->low_stock_threshold) {
            return 'menipis';
        }
        return 'aman';
    }

    public function getMarginPercent(): float
    {
        if ($this->selling_price <= 0) {
            return 0;
        }
        return round(($this->selling_price - $this->current_cost) / $this->selling_price * 100, 1);
    }
}
