<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPlan extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'modules' => 'array',
            'gateway_ids' => 'array',
            'monthly_price_aud' => 'decimal:2',
            'yearly_price_aud' => 'decimal:2',
            'is_active' => 'boolean',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
        ];
    }

    public function includes(string $module): bool
    {
        return $this->modules === null || in_array($module, $this->modules, true);
    }

    /** Price for a billing period in a currency (monthly or yearly). */
    public function price(string $currency, string $period): float
    {
        $column = ($period === 'yearly' ? 'yearly_price' : 'monthly_price').($currency === 'AUD' ? '_aud' : '');

        return (float) $this->{$column};
    }

    public function gyms(): HasMany
    {
        return $this->hasMany(Gym::class);
    }
}
