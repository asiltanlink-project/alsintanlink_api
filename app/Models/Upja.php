<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;
// use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Upja extends Authenticatable implements JWTSubject
{

  public $timestamps = false;
  public function getJWTIdentifier()
 {
     return $this->getKey();
 }

 public function getJWTCustomClaims()
 {
     return [];
 }
}
