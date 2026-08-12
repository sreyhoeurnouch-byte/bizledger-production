<?php
namespace App\Models;
use App\Models\Concerns\UsesUuid; use Illuminate\Database\Eloquent\Factories\HasFactory; use Illuminate\Database\Eloquent\Model;
class Company extends Model { use UsesUuid, HasFactory; protected $guarded=[]; }
