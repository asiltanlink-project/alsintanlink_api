<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class spare_part extends Model
{
    public $timestamps = false;
    protected $fillable = ['spare_part_type_id','name','kode_produk','part_number'];
}
