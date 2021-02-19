<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use Artisan;
use App\Models\lab_uji;
use App\Mail\Lab_uji_verif;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Helpers\LogActivity as Helper;
use Illuminate\Support\Facades\Storage;
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
          'phone_number' => 'required|unique:lab_ujis',
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

    Mail::to($request->email)->send(new Lab_uji_verif($blog));

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
             $final = array('message' => 'login sukses','token' => $token ,'lab_uji' => $fixed_user
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

  public function update_token(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $notif = transaction_notif_token_lab::where('lab_uji_id', $user_id)
                                    ->where('device_id', $request->device_id)
                                    ->first();
    if($notif == null){
      $final = array('message'=> 'device not found');
      return array('status' => 0 ,'result'=>$final);
    }

    $notif->token = $request->token;
    $notif->save();

    $final = array('message'=> 'update token success');
    return array('status' => 1 ,'result'=>$final);
  }

  public function delete_token(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $notif = transaction_notif_token_lab::where('lab_uji_id', $user_id)
                                    ->where('device_id', $request->device_id)
                                    ->first();
    if($notif == null){
      $final = array('message'=> 'device not found');
      return array('status' => 0 ,'result'=>$final);
    }
    $notif->delete();

    $final = array('message'=> 'delete token success');
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_lab_uji(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;
    $lab_uji = Helper::check_lab_uji($user_id);

    if($lab_uji->company_type == 0){

      $company_type = transaction_lab_uji_doc_perorangan::where('lab_uji_id', $user_id )->first();
      $company_type->url_ktp = 'https://alsintanlink.com/storage/lab_uji_upload/doc/perorangan/ktp/' . $company_type->url_ktp;
      $company_type->url_manual_book = 'https://alsintanlink.com/storage/lab_uji_upload/doc/perorangan/manual_book/' . $company_type->url_manual_book;

    }else if($lab_uji->company_type == 1){

      $company_type = transaction_lab_uji_doc_dalam_negeri::where('lab_uji_id', $user_id )->first();
      $company_type->url_akte_pendirian_perusahaan = 'https://alsintanlink.com/storage/lab_uji_upload/doc/dalam_negeri/akte_pendirian_perusahaan/' . $company_type->url_akte_pendirian_perusahaan;
      $company_type->url_ktp = 'https://alsintanlink.com/storage/lab_uji_upload/doc/dalam_negeri/ktp/' . $company_type->url_ktp;
      $company_type->url_manual_book = 'https://alsintanlink.com/storage/lab_uji_upload/doc/dalam_negeri/manual_book/' . $company_type->url_manual_book;
      $company_type->url_npwp = 'https://alsintanlink.com/storage/lab_uji_upload/doc/dalam_negeri/npwp/' . $company_type->url_npwp;
      $company_type->url_siup = 'https://alsintanlink.com/storage/lab_uji_upload/doc/dalam_negeri/siup/' . $company_type->url_siup;
      $company_type->url_surat_keterangan_domisili = 'https://alsintanlink.com/storage/lab_uji_upload/doc/dalam_negeri/surat_keterangan_domisili/' . $company_type->url_surat_keterangan_domisili;
      $company_type->url_surat_suku_cadang = 'https://alsintanlink.com/storage/lab_uji_upload/doc/dalam_negeri/surat_suku_cadang/' . $company_type->url_surat_suku_cadang;
      $company_type->url_tdp = 'https://alsintanlink.com/storage/lab_uji_upload/doc/dalam_negeri/tdp/' . $company_type->url_tdp;

    }else if($lab_uji->company_type == 2){

      $company_type = transaction_lab_uji_doc_import::where('lab_uji_id', $user_id )->first();
      $company_type->url_akte_pendirian_perusahaan = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/akte_pendirian_perusahaan/' . $company_type->url_akte_pendirian_perusahaan;
      $company_type->url_api = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/api/' . $company_type->url_api;
      $company_type->url_ktp = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/ktp/' . $company_type->url_ktp;
      $company_type->url_manual_book = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/manual_book/' . $company_type->url_manual_book;
      $company_type->url_npwp = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/npwp/' . $company_type->url_npwp;
      $company_type->url_siup = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/siup/' . $company_type->url_siup;
      $company_type->url_surat_keagenan_negara = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/surat_keagenan_negara/' . $company_type->url_surat_keagenan_negara;
      $company_type->url_surat_keterangan_domisili = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/surat_keterangan_domisili/' . $company_type->url_surat_keterangan_domisili;
      $company_type->url_surat_pernyataan_suku_cadang = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/surat_pernyataan_suku_cadang/' . $company_type->url_surat_pernyataan_suku_cadang;
      $company_type->url_tdp = 'https://alsintanlink.com/storage/lab_uji_upload/doc/import/tdp/' . $company_type->url_tdp;
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

  public function upload_doc_perorangan(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;
    // $temp_path = Storage::url('lab_uji_upload/doc/perorangan/ktp/eg5HhRjEGsxwDDnpH09ZIhRJvH7xzRWtixnaUhpL.png');
    // $path_url_ktp = env('APP_URL') . ''. $temp_path ;
    // dd($path_url_ktp);
    // create company
    $lab_uji = lab_uji::find($user_id);
    $lab_uji->company_name = $request->company_name;
    $lab_uji->company_address = $request->company_address;
    $lab_uji->company_number = $request->company_number;
    $lab_uji->company_status = $request->company_status;
    $lab_uji->company_type = 0;
    $lab_uji->save();

    // upload doc
    $this->validate($request, [
            'url_ktp' => 'file|max:7000', // max 7MB
            'url_manual_book' => 'file|max:7000', // max 7MB
        ]);

    $company_type = transaction_lab_uji_doc_perorangan::where('lab_uji_id',$user_id)->first();
    if($company_type == null){
      $company_type = new transaction_lab_uji_doc_perorangan;
      $company_type->lab_uji_id = $user_id;
    }else{

      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/perorangan/ktp/' . $company_type->url_ktp));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/manual_book/ktp/' . $company_type->url_manual_book));
    }

    // ktp
    if($request->hasFile('url_ktp')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/perorangan/ktp' ,
          $request->file('url_ktp')
      );
      $path_url_ktp = basename($upload);
      $company_type->url_ktp = $path_url_ktp;
    }

    // manual book
    if($request->hasFile('url_manual_book')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/perorangan/manual_book' ,
          $request->file('url_manual_book')
      );
      $path_url_manual_book = basename($upload);
      $company_type->url_manual_book = $path_url_manual_book;
    }
    $company_type->save();

    $final = array('message'=> "upload doc succsess");
    return array('status' => 1,'result'=>$final);
  }

  public function upload_doc_dalam_negeri(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    // create company
    $lab_uji = lab_uji::find($user_id);
    $lab_uji->company_name = $request->company_name;
    $lab_uji->company_address = $request->company_address;
    $lab_uji->company_number = $request->company_number;
    $lab_uji->company_status = $request->company_status;
    $lab_uji->company_type = 1;
    $lab_uji->save();

    $company_type = transaction_lab_uji_doc_dalam_negeri::where('lab_uji_id',$user_id)->first();
    if($company_type == null){

      $company_type = new transaction_lab_uji_doc_dalam_negeri;
      $company_type->lab_uji_id = $user_id;
    }else{

      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/akte_pendirian_perusahaan/' . $company_type->url_akte_pendirian_perusahaan));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri\manual_book/' . $company_type->url_manual_book));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri\ktp/' . $company_type->url_ktp));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri\npwp/' . $company_type->url_npwp));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri\surat_keterangan_domisili/' . $company_type->url_surat_keterangan_domisili));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri\siup/' . $company_type->url_siup));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri\tdp/' . $company_type->url_tdp));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri\surat_suku_cadang/' . $company_type->url_surat_suku_cadang));
    }

    // upload doc
    $this->validate($request, [
            'url_akte_pendirian_perusahaan' => 'file|max:7000', // max 7MB
            'url_ktp' => 'file|max:7000', // max 7MB
            'url_npwp' => 'file|max:7000', // max 7MB
            'url_surat_keterangan_domisili' => 'file|max:7000', // max 7MB
            'url_siup' => 'file|max:7000', // max 7MB
            'url_tdp' => 'file|max:7000', // max 7MB
            'url_surat_suku_cadang' => 'file|max:7000', // max 7MB
            'url_manual_book' => 'file|max:7000', // max 7MB
        ]);

    // url_akte_pendirian_perusahaan
    if($request->hasFile('url_akte_pendirian_perusahaan')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/dalam_negeri/akte_pendirian_perusahaan' ,
          $request->file('url_akte_pendirian_perusahaan')
      );
      $path_url_akte_pendirian_perusahaan = basename($upload);
      $company_type->url_akte_pendirian_perusahaan = $path_url_akte_pendirian_perusahaan;
    }

    // url_ktp
    if($request->hasFile('url_ktp')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/dalam_negeri/ktp' ,
          $request->file('url_ktp')
      );
      $path_url_ktp = basename($upload);
      $company_type->url_ktp= $path_url_ktp;
    }

    // url_npwp
    if($request->hasFile('url_npwp')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/dalam_negeri/npwp' ,
          $request->file('url_npwp')
      );
      $path_url_npwp = basename($upload);
      $company_type->url_npwp = $path_url_npwp;
    }

    // url_surat_keterangan_domisili
    if($request->hasFile('url_surat_keterangan_domisili')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/dalam_negeri/surat_keterangan_domisili' ,
          $request->file('url_surat_keterangan_domisili')
      );
      $path_surat_keterangan_domisili = basename($upload);
      $company_type->url_surat_keterangan_domisili = $path_surat_keterangan_domisili;
    }

    // url_siup
    if($request->hasFile('url_siup')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/dalam_negeri/siup' ,
          $request->file('url_siup')
      );
      $path_url_siup = basename($upload);
      $company_type->url_siup = $path_url_siup;
    }

    // url_tdp
    if($request->hasFile('url_tdp')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/dalam_negeri/tdp' ,
          $request->file('url_tdp')
      );
      $path_url_tdp = basename($upload);
      $company_type->url_tdp = $path_url_tdp;
    }

    // url_surat_suku_cadang
    if($request->hasFile('url_surat_suku_cadang')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/dalam_negeri/surat_suku_cadang' ,
          $request->file('url_surat_suku_cadang')
      );
      $path_url_surat_suku_cadang = basename($upload);
      $company_type->url_surat_suku_cadang = $path_url_surat_suku_cadang;
    }

    // url_manual_book
    if($request->hasFile('url_manual_book')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/dalam_negeri/manual_book' ,
          $request->file('url_manual_book')
      );
      $path_url_manual_book = basename($upload);
      $company_type->url_manual_book = $path_url_manual_book;
    }

    $company_type->save();

    $final = array('message'=> "upload doc succsess");
    return array('status' => 1,'result'=>$final);
  }

  public function upload_doc_import(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    // create company
    $lab_uji = lab_uji::find($user_id);
    $lab_uji->company_name = $request->company_name;
    $lab_uji->company_address = $request->company_address;
    $lab_uji->company_number = $request->company_number;
    $lab_uji->company_status = $request->company_status;
    $lab_uji->company_type = 2;
    $lab_uji->save();

    $company_type = transaction_lab_uji_doc_import::where('lab_uji_id',$user_id)->first();
    if($company_type == null){

      $company_type = new transaction_lab_uji_doc_import;
      $company_type->lab_uji_id = $user_id;
    }else{

      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/akte_pendirian_perusahaan/' . $company_type->url_akte_pendirian_perusahaan));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/api\\' . $company_type->url_api));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/ktp\\' . $company_type->url_ktp));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/manual_book\\' . $company_type->url_manual_book));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/npwp\\' . $company_type->url_npwp));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/siup\\' . $company_type->url_siup));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/surat_keagenan_negara\\' . $company_type->url_surat_keagenan_negara));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/surat_keterangan_domisili\\' . $company_type->url_surat_keterangan_domisili));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/surat_pernyataan_suku_cadang\\' . $company_type->url_surat_pernyataan_suku_cadang));
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/tdp\\' . $company_type->url_tdp));
    }

    // upload doc
    $this->validate($request, [
            'url_akte_pendirian_perusahaan' => 'file|max:10000', // max 7MB
            'url_ktp' => 'file|max:10000', // max 7MB
            'url_npwp' => 'file|max:10000', // max 7MB
            'url_api' => 'file|max:10000', // max 7MB
            'url_surat_keterangan_domisili' => 'file|max:10000', // max 7MB
            'url_siup' => 'file|max:10000', // max 7MB
            'url_tdp' => 'file|max:10000', // max 7MB
            'url_surat_keagenan_negara' => 'file|max:10000', // max 7MB
            'url_surat_pernyataan_suku_cadang' => 'file|max:10000', // max 7MB
            'url_manual_book' => 'file|max:10000', // max 7MB
        ]);

    // url_akte_pendirian_perusahaan
    if($request->hasFile('url_akte_pendirian_perusahaan')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/akte_pendirian_perusahaan' ,
          $request->file('url_akte_pendirian_perusahaan')
      );

      $path_akte_pendirian_perusahaan = basename($upload);
      $company_type->url_akte_pendirian_perusahaan = $path_akte_pendirian_perusahaan;
    }

    // url_ktp
    if($request->hasFile('url_ktp')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/ktp' ,
          $request->file('url_ktp')
      );

      $path_url_ktp = basename($upload);
      $company_type->url_ktp = $path_url_ktp;
    }

    // url_npwp
    if($request->hasFile('url_npwp')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/npwp' ,
          $request->file('url_npwp')
      );

      $path_url_npwp = basename($upload);
      $company_type->url_npwp = $path_url_npwp;
    }

    // url_api
    if($request->hasFile('url_api')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/api' ,
          $request->file('url_api')
      );

      $path_url_api = basename($upload);
      $company_type->url_api = $path_url_api;
    }

    // url_surat_keterangan_domisili
    if($request->hasFile('url_surat_keterangan_domisili')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/surat_keterangan_domisili' ,
          $request->file('url_surat_keterangan_domisili')
      );

      $path_url_surat_keterangan_domisili = basename($upload);
      $company_type->url_surat_keterangan_domisili = $path_url_surat_keterangan_domisili;
    }

    // url_siup
    if($request->hasFile('url_siup')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/siup' ,
          $request->file('url_siup')
      );

      $path_url_siup = basename($upload);
      $company_type->url_siup = $path_url_siup;
    }

    // url_tdp
    if($request->hasFile('url_tdp')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/tdp' ,
          $request->file('url_tdp')
      );

      $path_url_tdp = basename($upload);
      $company_type->url_tdp = $path_url_tdp;
    }

    // surat_keagenan_negara
    if($request->hasFile('url_surat_keagenan_negara')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/surat_keagenan_negara' ,
          $request->file('url_surat_keagenan_negara')
      );

      $path_url_surat_keagenan_negara = basename($upload);
      $company_type->url_surat_keagenan_negara = $path_url_surat_keagenan_negara;
    }

    // url_surat_pernyataan_suku_cadang
    if($request->hasFile('url_surat_pernyataan_suku_cadang')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/surat_pernyataan_suku_cadang' ,
          $request->file('url_surat_pernyataan_suku_cadang')
      );

      $path_url_surat_pernyataan_suku_cadang = basename($upload);
      $company_type->url_surat_pernyataan_suku_cadang = $path_url_surat_pernyataan_suku_cadang;
    }

    // url_manual_book
    if($request->hasFile('url_manual_book')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/doc/import/manual_book' ,
          $request->file('url_manual_book')
      );

      $path_url_manual_book = basename($upload);
      $company_type->url_manual_book = $path_url_manual_book;
    }

    $company_type->save();

    $final = array('message'=> "upload doc succsess");
    return array('status' => 1,'result'=>$final);
  }

  private function delete_file($url){

    if(file_exists($url)){
      unlink($url);
    }
  }

  public function create_Form(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $data = $request->all();
    DB::table('transaction_lab_uji_forms')->where('lab_uji_id', '=', $user_id)->update($data);

    $final = array('message'=> "upload form succsess");
    return array('status' => 1,'result'=>$final);
  }

  public function show_jadwal_uji(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $jadwal_uji = transaction_lab_uji_jadwal_uji::select('transaction_lab_uji_jadwal_ujis.*',
                                    'lab_uji_journey.name as journey_name')->
                                join('lab_uji_journey', 'lab_uji_journey.id', '=',
                                    'transaction_lab_uji_jadwal_ujis.status')->
                                where('lab_uji_id' , $user_id)->
                                first();

    $final = array('jadwal_uji'=> $jadwal_uji);
    return array('status' => 1,'result'=>$final);
  }

  public function send_billing(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $lab_uji = lab_uji::find($user_id);

    $final = array('message'=> 'kirim billing berhasil');
    return array('status' => 1,'result'=>$final);
  }

  public function lab_uji_verif_succsess(Request $request){

    $upja = lab_uji::find($request->lab_uji_id);
    if($upja == null){
      $final = array('message'=>'lab uji tidak ditemukan');
      return array('status' => 0,'result'=>$final);
    }
    $upja->verify = 1;
    $upja->save();

    return view('email/lab_uji_verif_succsess');
  }

  public function upload_billing(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $this->validate($request, [
            'bukti_pembayaran' => 'required|file|max:7000', // max 7MB
        ]);

    // ktp
    $upload = Storage::putFile(
        'public/lab_uji_upload/bukti_pembayaran/' ,
        $request->file('bukti_pembayaran')
    );

    $storageName = basename($upload);
    $temp_path = Storage::url('lab_uji_upload/doc/perorangan/ktp/' . $storageName);
    $path_url_ktp = env('APP_URL') . ''. $temp_path ;


    $final = array('message'=> "upload doc succsess");
    return array('status' => 1,'result'=>$final);
  }
}
