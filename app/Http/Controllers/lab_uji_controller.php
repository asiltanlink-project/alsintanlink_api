<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use App\Models\lab_uji;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\LogActivity as Helper;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\transaction_notif_token_lab;

use App\Models\lab_uji\transaction_lab_uji_form;
use App\Models\lab_uji\transaction_lab_uji_jadwal_uji;
use App\Models\lab_uji\transaction_lab_uji_doc_import;
use App\Models\lab_uji\transaction_lab_uji_doc_perorangan;
use App\Models\lab_uji\transaction_lab_uji_doc_dalam_negeri;

class lab_uji_controller extends Controller
{

  function __construct()
  {
      Config::set('auth.defaults.guard', 'lab_uji');
      Config::set('jwt.lab_uji' , \App\Models\lab_uji::class);
      Config::set('auth.providers.lab_uji.model', \App\Models\lab_uji::class);
  }

  public function rules()
  {
      return [
          'email' => 'required|unique:lab_ujis',
          'phone_number' => 'required|unique:farmers',
          'password' => 'required',
      ];
  }

  public function register(Request $request){

    $validator = \Validator::make($request->all(), $this->rules());
    $user = lab_uji::where('phone_number', $request->phone_number )
                    ->first();
    if($user != null){
      if($user->phone_verify == 0){

        $final = array('message'=> 'nomor telpon sudah terdaftar dan belum melakukan verifikasi');
        return array('status' => 2,'result' => $final) ;
      }else{

        $final = array('message'=> 'nomor telpon sudah terdaftar dan terverifikasi');
        return array('status' => 3,'result' => $final) ;
      }
    }else{

      $user = lab_uji::where('email', $request->email )
                      ->first();
      if($user != null){
        if($user->phone_verify == 0){

          $final = array('message'=> 'email sudah terdaftar dan belum melakukan verifikasi');
          return array('status' => 2,'result' => $final) ;
        }else{

          $final = array('message'=> 'email sudah terdaftar dan terverifikasi');
          return array('status' => 3,'result' => $final) ;
        }
      }
    }

    if ($validator->fails()) {

      $final = array('message'=>$validator->errors()->first());
      return array('status' => 0,'result' => $final) ;
    }

    $blog =  new lab_uji;
    $blog->full_name = $request->full_name;
    $blog->phone_number = $request->phone_number;
    $blog->email = $request->email;
    $blog->password = Hash::make($request->password);
    $blog->save();

    $final = array('message'=>'register succsess','lab_uji'=>$blog);
    return array('status' => 1,'result'=>$final);
  }

  public function login(Request $request ){

    $user = lab_uji::select('id','verify','email','full_name','password')
                    ->where('email', $request->email )
                    ->first();

    if($user!= null){
      if (Hash::check($request->password,$user->password )) {

          if($user->verify == 1){

                \Config::set('jwt.lab_uji', 'App\Models\lab_uji');
                \Config::set('auth.providers.lab_uji.model', \App\Models\lab_uji::class);

                $credentials = $request->only('email', 'password');
                $token = null;

                try {

                    if (! $token = auth('lab_uji')->attempt($credentials)) {

                        return response()->json(['error' => 'invalid_credentials'], 400);
                    }
                } catch (JWTException $e) {

                    return response()->json(['error' => 'could_not_create_token'], 500);
                }

                // month // day // hour // minur
                $myTTL = 6 * 30 * 24 * 60; //minutes

              JWTAuth::factory()->setTTL($myTTL);
              $token = JWTAuth::attempt($credentials);

              $devide_add = 0;
              $device_id = transaction_notif_token_lab::where('lab_uji_id' , $user->id)
                                                  ->orderby('device_id','desc')
                                                  ->first();
              if($device_id != null){
                $devide_add = $device_id->device_id + 1;
              }

              $notif = new transaction_notif_token_lab;
              $notif->lab_uji_id = $user->id ;
              $notif->device_id = $devide_add ;
              $notif->token = $request->token_notif ;
              $notif->save();

             $fixed_user = lab_uji::select('id','verify','phone_number')->find($user->id);
             $final = array('message' => 'login sukses','token' => $token ,'farmer' => $fixed_user
                            ,'device_id' => $notif->device_id
                          );

             return array('status' => 1, 'result' => $final);
          }else{

            $final = array('message'=>"gagal login belum verif", 'otp_code' => $user->otp_code);
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

  public function show_detail_lab_uji(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;
    $lab_uji = Helper::check_lab_uji($user_id);

    if($lab_uji->company_type == 0){
      $company_type = transaction_lab_uji_doc_perorangan::where('lab_uji_id', $user_id )->first();
    }else if($lab_uji->company_type == 1){
      $company_type = transaction_lab_uji_doc_dalam_negeri::where('lab_uji_id', $user_id )->first();
    }else if($lab_uji->company_type == 2){
      $company_type = transaction_lab_uji_doc_import::where('lab_uji_id', $user_id )->first();
    }else{
      $company_type = null;
    }

    $lab_uji_form = transaction_lab_uji_form::select('transaction_lab_uji_forms.*' ,
                                                    'lab_uji_status_alsintan.name as status_alsintan_name',
                                                    'lab_uji_status_pemohon.name as status_pemohon_name')->
                                              where('lab_uji_id', $user_id )->
                                              Join ('lab_uji_status_pemohon', 'lab_uji_status_pemohon.id',
                                                    '=', 'transaction_lab_uji_forms.status_pemohon')->
                                              Join ('lab_uji_status_alsintan', 'lab_uji_status_alsintan.id',
                                                    '=', 'transaction_lab_uji_forms.status_alsintan')->
                                              first();

    $lab_uji_jadwal = transaction_lab_uji_jadwal_uji::select('transaction_lab_uji_jadwal_ujis.*' ,
                                    'lab_uji_journey.name as status_name')->
                                    where('lab_uji_id', $user_id )->
                                    Join ('lab_uji_journey', 'lab_uji_journey.id',
                                    '=', 'transaction_lab_uji_jadwal_ujis.status')->
                                    first();

    $final = array('lab_uji'=> $lab_uji, 'company_type'=> $company_type,
                   'lab_uji_form'=> $lab_uji_form, 'lab_uji_jadwal'=> $lab_uji_jadwal);

    return array('status' => 1,'result'=>$final);
  }

  public function create_company_profile(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $lab_uji = lab_uji::find($user_id);
    $lab_uji->company_name = $request->company_name;
    $lab_uji->company_address = $request->company_address;
    $lab_uji->company_number = $request->company_number;
    $lab_uji->company_status = $request->company_status;
    $lab_uji->company_type = $request->company_type;
    $lab_uji->save();

    if($lab_uji->company_type == 0){

      $company_type = new transaction_lab_uji_doc_perorangan;
      $company_type->lab_uji_id = $user_id;
      $company_type->save();

    }else if($lab_uji->company_type == 1){

      $company_type = new transaction_lab_uji_doc_dalam_negeri;
      $company_type->lab_uji_id = $user_id;
      $company_type->save();

    }else if($lab_uji->company_type == 2){

      $company_type = new transaction_lab_uji_doc_import;
      $company_type->lab_uji_id = $user_id;
      $company_type->save();
    }

    $final = array('message'=> "create company profile succsess");
    return array('status' => 1,'result'=>$final);
  }

}
