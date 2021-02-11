<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class spare_part_type extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'alsin_type_id'];
}
