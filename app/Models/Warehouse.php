<?php

namespace App\Models;

use App\Models\Concerns\UsesUuid;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use UsesUuid;

    public $timestamps = false;

    protected $guarded = [];
}
