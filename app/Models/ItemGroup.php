<?php
namespace App\Models;
use App\Models\Concerns\UsesUuid; use Illuminate\Database\Eloquent\Model;
class ItemGroup extends Model { use UsesUuid; protected $guarded=[]; }
