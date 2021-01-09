<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use App\Models\Upja;
use App\Models\Admin;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;

class Admin_Controller extends Controller
{

  function __construct()
  {
      Config::set('auth.defaults.guard', 'admin');
      Config::set('jwt.admin' , \App\Models\Admin::class);
      Config::set('auth.providers.admin.model', \App\Models\Admin::class);
  }

  public function login(Request $request ){

    $user = Admin::select('id','username','password')
                    ->where('username', $request->username )
                    ->first();

    if($user!= null){
      if (Hash::check($request->password,$user->password )) {

            \Config::set('jwt.admin', 'App\Models\Admin');
            \Config::set('auth.providers.admin.model', \App\Models\Admin::class);

            $credentials = $request->only('username', 'password');
            $token = null;

            try {
                if (! $token = auth('admin')->attempt($credentials)) {

                    return response()->json(['error' => 'invalid_credentials'], 400);
                }
            } catch (JWTException $e) {

                return response()->json(['error' => 'could_not_create_token'], 500);
            }
            // month // day // hour // minur
            $myTTL = 6 * 30 * 24 * 60; //minutes

          JWTAuth::factory()->setTTL($myTTL);
          $token = JWTAuth::attempt($credentials);

         $fixed_user = Admin::select('id','username')->find($user->id);
         $final = array('message' => $token, 'admin' => $fixed_user);
         return array('status' => 1, 'result' => $final);

      }else{
        $final = array('message'=>"password dan username tidak sesuai");
        return array('status' => 0, 'result' => $final) ;
      }
    }else{
      $final = array('message'=>"username atau email tidak ditemukan");
      return array('status' => 3, 'result' => $final) ;
    }
  }

  public function show_upja(Request $request ){

    $upja = Upja::select('id','name','leader_name','village','class')
                    ->where('district', $request->district )
                    ->get();
    $final = array('upjas'=>$upja);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_upja(Request $request ){

    $upja = Upja::select('id','name','leader_name','village','class')
                    ->where('id', $request->upja_id )
                    ->first();

    return array('status' => 1 ,'result'=>$upja);
  }

  public function show_farmer(Request $request ){

    $farmers = Farmer::select('id','name','phone_verify')
                    ->where('district', $request->district )
                    ->get();
                    
    $final = array('farmers'=>$farmers);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_farmer(Request $request ){

    $upja = Farmer::select('id','name','leader_name','village','class')
                    ->where('id', $request->farmer_id )
                    ->first();

    return array('status' => 1 ,'result'=>$upja);
  }
}
