<?php
namespace App\Models;
use App\Models\Concerns\UsesUuid; use Illuminate\Database\Eloquent\Model;
class Contact extends Model { use UsesUuid; protected $guarded=[]; protected function casts():array{return ['active'=>'boolean','opening_balance'=>'decimal:2'];} }
