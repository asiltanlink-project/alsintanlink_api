<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction_order_type extends Model
{
    public $timestamps = false;

    public function transaction_childs(){
      return $this->hasMany(\App\Models\Transaction_order_child::class,'transaction_order_type_id')
      ->select(
          'transaction_order_children.transaction_order_type_id',
          'transaction_order_children.alsin_item_id',
          'alsin_items.vechile_code',
          'alsin_items.description'
      )
      ->Join ('alsin_items', 'alsin_items.id', '=', 'transaction_order_children.alsin_item_id');
    }
}
