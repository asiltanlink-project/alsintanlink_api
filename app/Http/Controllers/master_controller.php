<?php

namespace App\Http\Controllers;
use Hash;
use Config;
use JWTAuth;
use App\Models\master;
use App\Models\lab_uji;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\LogActivity as Helper;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\lab_uji\transaction_lab_uji_form;
use App\Models\lab_uji\transaction_lab_uji_jadwal_uji;
use App\Models\lab_uji\transaction_lab_uji_doc_import;
use App\Models\lab_uji\transaction_lab_uji_doc_perorangan;
use App\Models\lab_uji\transaction_lab_uji_doc_dalam_negeri;

class master_controller extends Controller
{
  function __construct()
  {
      Config::set('auth.defaults.guard', 'master');
      Config::set('jwt.master' , \App\Models\master::class);
      Config::set('auth.providers.master.model', \App\Models\master::class);
  }

  public function login(Request $request ){


    $user = master::select('password')
                    ->where('username', $request->username )
                    ->first();
    if($user != null){
        if (Hash::check($request->password,$user->password )) {

              \Config::set('jwt.master', 'App\Models\master');
              \Config::set('auth.providers.master.model', \App\Models\master::class);

              $credentials = $request->only('username', 'password');
              $token = null;

              try {

                  if (! $token = auth('master')->attempt($credentials)) {

                      return response()->json(['error' => 'invalid_credentials'], 400);
                  }
              } catch (JWTException $e) {

                  return response()->json(['error' => 'could_not_create_token'], 500);
              }

              // month // day // hour // minur
              $myTTL = 6 * 30 * 24 * 60; //minutes

            JWTAuth::factory()->setTTL($myTTL);
            $token = JWTAuth::attempt($credentials);

           $final = array('message' => 'login sukses','token' => $token);

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

  public function show_lab_uji(Request $request){

    if($request->verify == null){
      $lab_uji = lab_uji::orderBy('created_at','desc')->
                          get();
    }else{
      $lab_uji = lab_uji::where('verify',$request->verify)->
                          orderBy('created_at','desc')->
                          get();
    }

    $final = array('lab_ujis'=> $lab_uji);
    return array('status' => 1,'result'=>$final);
  }

  public function show_detail_lab_uji(Request $request){

    $lab_uji = Helper::check_lab_uji($request->lab_uji_id);
    if($lab_uji == null){
      $final = array('message'=> "lab uji not found");
      return array('status' => 0 ,'result'=>$final);
    }

    if($lab_uji->company_type == 0){
      $company_type = transaction_lab_uji_doc_perorangan::where('lab_uji_id', $request->lab_uji_id )->first();
    }else if($lab_uji->company_type == 1){
      $company_type = transaction_lab_uji_doc_dalam_negeri::where('lab_uji_id', $request->lab_uji_id )->first();
    }else if($lab_uji->company_type == 2){
      $company_type = transaction_lab_uji_doc_import::where('lab_uji_id', $request->lab_uji_id )->first();
    }else{
      $company_type = null;
    }

    $lab_uji_form = transaction_lab_uji_form::select('transaction_lab_uji_forms.*' ,
                                                    'lab_uji_status_alsintan.name as status_alsintan_name',
                                                    'lab_uji_status_pemohon.name as status_pemohon_name')->
                                              where('lab_uji_id', $request->lab_uji_id )->
                                              Join ('lab_uji_status_pemohon', 'lab_uji_status_pemohon.id',
                                                    '=', 'transaction_lab_uji_forms.status_pemohon')->
                                              Join ('lab_uji_status_alsintan', 'lab_uji_status_alsintan.id',
                                                    '=', 'transaction_lab_uji_forms.status_alsintan')->
                                              first();

    $lab_uji_jadwal = transaction_lab_uji_jadwal_uji::select('transaction_lab_uji_jadwal_ujis.*' ,
                                    'lab_uji_journey.name as status_name')->
                                    where('lab_uji_id', $request->lab_uji_id )->
                                    Join ('lab_uji_journey', 'lab_uji_journey.id',
                                    '=', 'transaction_lab_uji_jadwal_ujis.status')->
                                    first();

    $final = array('lab_uji'=> $lab_uji, 'company_type'=> $company_type,
                   'lab_uji_form'=> $lab_uji_form, 'lab_uji_jadwal'=> $lab_uji_jadwal);

    return array('status' => 1,'result'=>$final);
  }

  public function change_status_doc_file(Request $request){

    $lab_uji = Helper::check_lab_uji($request->lab_uji_id);
    if($lab_uji == null){
      $final = array('message'=> "lab uji not found");
      return array('status' => 0 ,'result'=>$final);
    }

    if($lab_uji->company_type == 0){

      $company_type = transaction_lab_uji_doc_perorangan::where('lab_uji_id', $request->lab_uji_id )->first();
      $company_type->verif = $request->verif;
      $company_type->ktp = $request->ktp;
      $company_type->manual_book = $request->manual_book;
      $company_type->save();
    }else if($lab_uji->company_type == 1){

      $company_type = transaction_lab_uji_doc_dalam_negeri::where('lab_uji_id', $request->lab_uji_id )->first();
      $company_type->verif = $request->verif;
      $company_type->akte_pendirian_perusahaan = $request->akte_pendirian_perusahaan;
      $company_type->ktp = $request->ktp;
      $company_type->npwp = $request->npwp;
      $company_type->surat_keterangan_domisili = $request->surat_keterangan_domisili;
      $company_type->siup = $request->siup;
      $company_type->tdp = $request->tdp;
      $company_type->surat_suku_cadang = $request->surat_suku_cadang;
      $company_type->manual_book = $request->manual_book;
      $company_type->save();
    }else if($lab_uji->company_type == 2){

      $company_type = transaction_lab_uji_doc_import::where('lab_uji_id', $request->lab_uji_id )->first();
      $company_type->verif = $request->verif;
      $company_type->akte_pendirian_perusahaan = $request->akte_pendirian_perusahaan;
      $company_type->ktp = $request->ktp;
      $company_type->npwp = $request->npwp;
      $company_type->surat_keterangan_domisili = $request->surat_keterangan_domisili;
      $company_type->manual_book = $request->manual_book;
      $company_type->api = $request->api;
      $company_type->siup = $request->siup;
      $company_type->tdp = $request->tdp;
      $company_type->surat_keagenan_negara = $request->surat_keagenan_negara;
      $company_type->surat_pernyataan_suku_cadang = $request->surat_pernyataan_suku_cadang;
      $company_type->save();
    }

    $final = array('message'=> "accept doc sukses");
    return array('status' => 1,'result'=>$final);
  }

  public function change_status_form(Request $request){

    $lab_uji = Helper::check_lab_uji($request->lab_uji_id);
    if($lab_uji == null){
      $final = array('message'=> "lab uji not found");
      return array('status' => 0 ,'result'=>$final);
    }

    $lab_uji_form = transaction_lab_uji_form::where('lab_uji_id', $request->lab_uji_id )->
                                              first();
    if($lab_uji_form == null){
      $final = array('message'=> "lab uji form not found");
      return array('status' => 0 ,'result'=>$final);
    }
    $lab_uji_form->verif = $request->verif;
    $lab_uji_form->save();

    $final = array('message'=> "accept form sukses");
    return array('status' => 1,'result'=>$final);
  }

  public function create_jadwal_uji(Request $request){

    $lab_uji = Helper::check_lab_uji($request->lab_uji_id);
    if($lab_uji == null){
      $final = array('message'=> "lab uji not found");
      return array('status' => 0 ,'result'=>$final);
    }

    $jadwal_uji = new transaction_lab_uji_jadwal_uji;
    $jadwal_uji->lab_uji_id = $request->lab_uji_id;
    $jadwal_uji->tim_uji = $request->tim_uji;
    $jadwal_uji->waktu_uji_lab = $request->waktu_uji_lab;
    $jadwal_uji->waktu_uji_lapangan = $request->waktu_uji_lapangan;
    $jadwal_uji->lokasi_uji = $request->lokasi_uji;
    $jadwal_uji->save();

    $final = array('message'=> "create jadwal sukses");
    return array('status' => 1,'result'=>$final);
  }

  public function change_status_jadwal_uji(Request $request){

    $lab_uji = Helper::check_lab_uji($request->lab_uji_id);
    if($lab_uji == null){
      $final = array('message'=> "lab uji not found");
      return array('status' => 0 ,'result'=>$final);
    }

    $lab_uji_form = transaction_lab_uji_jadwal_uji::where('lab_uji_id', $request->lab_uji_id )->
                                              first();
    if($lab_uji_form == null){
      $final = array('message'=> "lab uji form not found");
      return array('status' => 0 ,'result'=>$final);
    }
    $lab_uji_form->status = $request->status;
    $lab_uji_form->save();

    $final = array('message'=> "ganti status sukses");
    return array('status' => 1,'result'=>$final);
  }
}
