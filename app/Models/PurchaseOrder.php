<?php
namespace App\Models;
use App\Models\Concerns\UsesUuid; use Illuminate\Database\Eloquent\Model;
class PurchaseOrder extends Model { use UsesUuid; protected $guarded=[]; protected function casts():array{return ['order_date'=>'date','expected_date'=>'date','approved_at'=>'datetime','total'=>'decimal:2'];} public function vendor(){return $this->belongsTo(Contact::class,'vendor_id');} public function lines(){return $this->hasMany(PurchaseOrderLine::class);} public function goodsReceipts(){return $this->hasMany(GoodsReceipt::class);} public function approvedBy(){return $this->belongsTo(User::class,'approved_by');} }
