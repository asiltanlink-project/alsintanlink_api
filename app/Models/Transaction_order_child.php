<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction_order_child extends Model
{
    public $timestamps = false;

    public function Transaction_Parent(){
      return $this->belongsToMany(\App\Models\Transaction_order_type::class,'transaction_order_type_id');
    }
}
