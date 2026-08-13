<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use UsesUuid;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'balance' => 'decimal:2'];
    }
}
