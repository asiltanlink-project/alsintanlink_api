<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use App\Models\Upja;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;

class Upja_Controller extends Controller
{

  function __construct()
  {
      Config::set('auth.defaults.guard', 'upja');
      Config::set('jwt.upja' , \App\Models\Upja::class);
      Config::set('auth.providers.upja.model', \App\Models\Upja::class);
  }

  public function login(Request $request ){

    $user = Upja::select('id','email_verify','email','name','password')
                    ->where('email', $request->email )
                    ->first();

    if($user!= null){
      if (Hash::check($request->password,$user->password )) {

          if($user->email_verify == 1){

                \Config::set('jwt.upja', 'App\Models\Upja');
                \Config::set('auth.providers.upja.model', \App\Models\Upja::class);

                $credentials = $request->only('email', 'password');
                $token = null;

                try {
                    if (! $token = auth('upja')->attempt($credentials)) {

                        return response()->json(['error' => 'invalid_credentials'], 400);
                    }
                } catch (JWTException $e) {

                    return response()->json(['error' => 'could_not_create_token'], 500);
                }
                // month // day // hour // minur
                $myTTL = 6 * 30 * 24 * 60; //minutes

              JWTAuth::factory()->setTTL($myTTL);
              $token = JWTAuth::attempt($credentials);

             $fixed_user = Upja::select('id','email_verify','email')->find($user->id);

             $final = array('message' => $token, 'upja' => $fixed_user);
             return array('status' => 1, 'result' => $final);
          }else{

            $final = array('message'=>"gagal login belum verif");
            return array('status' => 2,'result' => $final) ;
          }
      }else{

        $final = array('message'=>"password dan username tidak sesuai");
        return array('status' => 0, 'result' => $final) ;
      }
    }else{

      $final = array('message'=>"username atau email tidak ditemukan");
      return array('status' => 3, 'result' => $final) ;
    }
  }

  public function rules()
  {
      return [
          'email' => 'required|unique:upjas|email',
          'name' => 'required',
          'leader_name' => 'required',
          'password' => 'required',
          'district' => 'required',
      ];
  }

  public function register(Request $request){

    $validator = \Validator::make($request->all(), $this->rules());

    if ($validator->fails()) {

      $final = array('message'=>$validator->errors()->first());
      return array('status' => 0,'result' => $final) ;
    }

    $blog =  new Upja;
    $blog->name = $request->name;
    $blog->email = $request->email;
    $blog->province = $request->province;
    $blog->city = $request->city;
    $blog->district = $request->district;
    $blog->leader_name = $request->leader_name;
    $blog->village = $request->village;
    $blog->class = $request->class;
    $blog->password = Hash::make($request->password);
    $blog->save();

    //send email
    // Mail::to($blog->email)->send(new Verify($blog));

    $final = array('message'=>'register succsess', 'upja'=>$blog);
    return array('status' => 1,'result'=>$final);
  }
}
