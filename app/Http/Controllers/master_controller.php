<?php

namespace App\Http\Controllers;
use Hash;
use Config;
use JWTAuth;
use Response;
use ZipArchive;
use App\Models\master;
use App\Models\lab_uji;
use Illuminate\Http\Request;
use App\Mail\Master_Kode_Billing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Helpers\LogActivity as Helper;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\lab_uji\transaction_lab_uji_form;
use App\Models\lab_uji\transaction_lab_uji_jadwal_uji;
use App\Models\lab_uji\transaction_lab_uji_doc_import;
use App\Models\lab_uji\transaction_lab_uji_doc_perorangan;
use App\Models\lab_uji\transaction_lab_uji_doc_dalam_negeri;
use Illuminate\Pagination\LengthAwarePaginator;
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

           $final = array('message' => 'login berhasil','token' => $token);

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
 
   
  $lab_uji = lab_uji::select('lab_ujis.*', 
                              DB::raw("(select count(transaction_lab_uji_forms.id)
                                        FROM transaction_lab_uji_forms
                                        WHERE (transaction_lab_uji_forms.lab_uji_id = lab_ujis.id)
                                        AND (transaction_lab_uji_forms.is_admin_action = 1)
                                      ) as is_admin_action_form")   ,
                              DB::raw("(select is_admin_action_form + lab_ujis.need_verify_doc + lab_ujis.is_download) as is_admin_action")
                          )
                      ->orderBy('is_admin_action','desc')
                      ->orderBy('id','asc')
                      ->paginate(10);
   

   $max_page = $lab_uji->lastPage();
   $current_page =$lab_uji->currentPage();
   if($max_page == 0){
     $max_page = 1;
   }

   if($request->verify == 1){

     $final_lab_uji = $lab_uji->getCollection()->where('is_admin_action' , '>' , 0);
     $final = array('lab_ujis'=> $lab_uji->setCollection($final_lab_uji),'max_page'=> $max_page,
                  'current_page'=> $current_page);
     return array('status' => 1,'result'=>$final);

   }

   $final = array('lab_ujis'=> $lab_uji,'max_page'=> $max_page,
                  'current_page'=> $current_page);
   return array('status' => 1,'result'=>$final);
 }


  public function show_detail_lab_uji(Request $request){

    $lab_uji = Helper::check_lab_uji($request->lab_uji_id);
    if($lab_uji == null){
      $final = array('message'=> "lab uji tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }

    if($lab_uji->company_type == 0){

      $company_type = transaction_lab_uji_doc_perorangan::where('lab_uji_id',$request->lab_uji_id)->first();
      if($company_type!=null){
        $company_type->url_ktp = env('APP_URL') . '/public/storage/lab_uji_upload/doc/perorangan/ktp/' . $company_type->url_ktp;
        $company_type->url_npwp = env('APP_URL') . '/public/storage/lab_uji_upload/doc/perorangan/npwp/' . $company_type->url_npwp;
      }

    }else if($lab_uji->company_type == 1){

      $company_type = transaction_lab_uji_doc_dalam_negeri::where('lab_uji_id', $request->lab_uji_id )->first();
      if($company_type!=null){
        $company_type->url_akte_pendirian_perusahaan = env('APP_URL') . '/public/storage/lab_uji_upload/doc/dalam_negeri/akte_pendirian_perusahaan/' . $company_type->url_akte_pendirian_perusahaan;
        $company_type->url_ktp = env('APP_URL') . '/public/storage/lab_uji_upload/doc/dalam_negeri/ktp/' . $company_type->url_ktp;
        $company_type->url_manual_book = env('APP_URL') . '/public/storage/lab_uji_upload/doc/dalam_negeri/manual_book/' . $company_type->url_manual_book;
        $company_type->url_npwp = env('APP_URL') . '/public/storage/lab_uji_upload/doc/dalam_negeri/npwp/' . $company_type->url_npwp;
        $company_type->url_siup = env('APP_URL') . '/public/storage/lab_uji_upload/doc/dalam_negeri/siup/' . $company_type->url_siup;
        $company_type->url_surat_keterangan_domisili = env('APP_URL') . '/public/storage/lab_uji_upload/doc/dalam_negeri/surat_keterangan_domisili/' . $company_type->url_surat_keterangan_domisili;
        $company_type->url_surat_suku_cadang = env('APP_URL') . '/public/storage/lab_uji_upload/doc/dalam_negeri/surat_suku_cadang/' . $company_type->url_surat_suku_cadang;
        $company_type->url_tdp = env('APP_URL') . '/public/storage/lab_uji_upload/doc/dalam_negeri/tdp/' . $company_type->url_tdp;
      }

    }else if($lab_uji->company_type == 2){

      $company_type = transaction_lab_uji_doc_import::where('lab_uji_id', $request->lab_uji_id )->first();
      if($company_type!=null){

        $company_type->url_akte_pendirian_perusahaan = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/akte_pendirian_perusahaan/' . $company_type->url_akte_pendirian_perusahaan;
        $company_type->url_api = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/api/' . $company_type->url_api;
        $company_type->url_ktp = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/ktp/' . $company_type->url_ktp;
        $company_type->url_manual_book = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/manual_book/' . $company_type->url_manual_book;
        $company_type->url_npwp = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/npwp/' . $company_type->url_npwp;
        $company_type->url_siup = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/siup/' . $company_type->url_siup;
        $company_type->url_surat_keagenan_negara = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/surat_keagenan_negara/' . $company_type->url_surat_keagenan_negara;
        $company_type->url_surat_keterangan_domisili = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/surat_keterangan_domisili/' . $company_type->url_surat_keterangan_domisili;
        $company_type->url_surat_suku_cadang = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/surat_suku_cadang/' . $company_type->url_surat_suku_cadang;
        $company_type->url_tdp = env('APP_URL') . '/public/storage/lab_uji_upload/doc/import/tdp/' . $company_type->url_tdp;
      }
    }else{
      $company_type = null;
    }

    $lab_uji_form = transaction_lab_uji_form::select('transaction_lab_uji_forms.id' ,
                                                    'transaction_lab_uji_forms.nama_alsintan' ,
                                                    'transaction_lab_uji_forms.is_admin_action' ,
                                                    'lab_uji_status_alsintan.name as status_alsintan_name',
                                                    'lab_uji_status_pemohon.name as status_pemohon_name',
                                                    'lab_uji_journey.name as status_journey')->
                                              where('lab_uji_id', $request->lab_uji_id )->
                                              Join ('lab_uji_status_pemohon', 'lab_uji_status_pemohon.id',
                                                    '=', 'transaction_lab_uji_forms.status_pemohon')->
                                              Join ('lab_uji_status_alsintan', 'lab_uji_status_alsintan.id',
                                                    '=', 'transaction_lab_uji_forms.status_alsintan')->
                                              Join ('lab_uji_journey', 'lab_uji_journey.id',
                                                    '=', 'transaction_lab_uji_forms.status_journey')->
                                              get();
    // $lab_uji_form = transaction_lab_uji_form::select('transaction_lab_uji_forms.*' ,
    //                                                 'lab_uji_status_alsintan.name as status_alsintan_name',
    //                                                 'lab_uji_status_pemohon.name as status_pemohon_name')->
    //                                           where('lab_uji_id', $request->lab_uji_id )->
    //                                           Join ('lab_uji_status_pemohon', 'lab_uji_status_pemohon.id',
    //                                                 '=', 'transaction_lab_uji_forms.status_pemohon')->
    //                                           Join ('lab_uji_status_alsintan', 'lab_uji_status_alsintan.id',
    //                                                 '=', 'transaction_lab_uji_forms.status_alsintan')->
    //                                           first();
    //
    // $lab_uji_jadwal = transaction_lab_uji_jadwal_uji::select('transaction_lab_uji_jadwal_ujis.*')->
    //                                 where('lab_uji_id', $request->lab_uji_id )->
    //                                 first();
    // if($lab_uji_jadwal != null){
    //   $lab_uji_jadwal->bukti_pembayaran = env('APP_URL') . '/public/storage/lab_uji_upload/bukti_pembayaran/' . $lab_uji_jadwal->bukti_pembayaran;
    //   $lab_uji_jadwal->scan_hasil_uji= env('APP_URL') . '/public/storage/lab_uji_upload/scan_hasil_uji/' . $lab_uji_jadwal->scan_hasil_uji;
    // }

    $final = array('lab_uji'=> $lab_uji, 'company_type'=> $company_type,
                   'lab_uji_form'=> $lab_uji_form);

    return array('status' => 1,'result'=>$final);
  }

  public function show_detail_form(Request $request){

    $lab_uji_form = transaction_lab_uji_form::select('transaction_lab_uji_forms.*',
                                                    'lab_uji_status_alsintan.name as status_alsintan_name',
                                                    'lab_uji_status_pemohon.name as status_pemohon_name')->
                                              where('transaction_lab_uji_forms.id', $request->form_uji_id )->
                                              Join ('lab_uji_status_pemohon', 'lab_uji_status_pemohon.id',
                                                    '=', 'transaction_lab_uji_forms.status_pemohon')->
                                              Join ('lab_uji_status_alsintan', 'lab_uji_status_alsintan.id',
                                                    '=', 'transaction_lab_uji_forms.status_alsintan')->
                                              first();
    if($lab_uji_form == null){
      $final = array('message'=> 'form lab uji tidak ditemukan');
      return array('status' => 1,'result'=>$final);
    }

    $lab_uji_jadwal = transaction_lab_uji_jadwal_uji::select('transaction_lab_uji_jadwal_ujis.*',
                                    // DB::raw('DATE_FORMAT(transaction_lab_uji_jadwal_ujis.waktu_uji_lab, "%m-%d-%Y") as waktu_uji_lab'),
                                    // DB::raw('DATE_FORMAT(transaction_lab_uji_jadwal_ujis.waktu_uji_lapangan, "%m-%d-%Y") as waktu_uji_lapangan')
                                    DB::raw('DATE_FORMAT(transaction_lab_uji_jadwal_ujis.waktu_uji_lab, "%Y-%m-%d") as waktu_uji_lab'),
                                    DB::raw('DATE_FORMAT(transaction_lab_uji_jadwal_ujis.waktu_uji_lapangan, "%Y-%m-%d") as waktu_uji_lapangan')
                                    )->
                                    where('form_uji_id', $request->form_uji_id )->
                                    first();

    if($lab_uji_jadwal != null){
      $lab_uji_jadwal->bukti_pembayaran = env('APP_URL') . '/public/storage/lab_uji_upload/bukti_pembayaran/' . $lab_uji_jadwal->bukti_pembayaran;
      $lab_uji_jadwal->scan_hasil_uji= env('APP_URL') . '/public/storage/lab_uji_upload/scan_hasil_uji/' . $lab_uji_jadwal->scan_hasil_uji;
    }

    $final = array('lab_uji_form'=> $lab_uji_form , 'lab_uji_jadwal'=> $lab_uji_jadwal);
    return array('status' => 1,'result'=>$final);
  }

  public function change_status_doc_file(Request $request){

    $lab_uji = Helper::check_lab_uji($request->lab_uji_id);
    if($lab_uji == null){
      $final = array('message'=> "lab uji tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }

    // $zip_file = 'invoices.zip'; // Name of our archive to download
    // // Initializing PHP class
    // $zip = new ZipArchive;
    // $zip->open( storage_path($zip_file) , ZipArchive::CREATE | ZipArchive::OVERWRITE );

    if($lab_uji->company_type == 0){

      $company_type = transaction_lab_uji_doc_perorangan::where('lab_uji_id', $request->lab_uji_id )->first();
      $company_type->ktp = $request->ktp;
      $company_type->npwp = $request->npwp;
      
      if($request->verif == 1){
        
        $lab_uji->is_download = 1;
        $company_type->verif = 1;
  
        $ktp= $company_type->url_ktp  ;
        $npwp= $company_type->url_npwp  ;
      
        $tokenList = DB::table('transaction_notif_token_labs')
                      ->where('transaction_notif_token_labs.lab_uji_id', $request->lab_uji_id)
                      ->pluck('transaction_notif_token_labs.token')
                       ->all();

        app('App\Http\Controllers\General_Controller')->
        PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Dokumen diterima'
                          ,1, $tokenList );
      }else{
        $company_type->verif = -1;

        $tokenList = DB::table('transaction_notif_token_labs')
                      ->where('transaction_notif_token_labs.lab_uji_id', $request->lab_uji_id)
                      ->pluck('transaction_notif_token_labs.token')
                       ->all();

        app('App\Http\Controllers\General_Controller')->
        PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Dokumen ditolak'
                          ,1, $tokenList );
      }
      $company_type->keterangan = $request->keterangan;
      $company_type->save();

      $lab_uji->need_verify_doc = 0;
      $lab_uji->save();

    }else if($lab_uji->company_type == 1){

      $company_type = transaction_lab_uji_doc_dalam_negeri::where('lab_uji_id', $request->lab_uji_id )->first();

      $company_type->akte_pendirian_perusahaan = $request->akte_pendirian_perusahaan;
      $company_type->ktp = $request->ktp;
      $company_type->npwp = $request->npwp;
      $company_type->surat_keterangan_domisili = $request->surat_keterangan_domisili;
      $company_type->siup = $request->siup;
      $company_type->tdp = $request->tdp;
      $company_type->surat_suku_cadang = $request->surat_suku_cadang;
      $company_type->manual_book = $request->manual_book;

      if($request->verif == 1){
        $company_type->verif = 1;
        $lab_uji->is_download = 1;

        $tokenList = DB::table('transaction_notif_token_labs')
                      ->where('transaction_notif_token_labs.lab_uji_id', $request->lab_uji_id)
                      ->pluck('transaction_notif_token_labs.token')
                       ->all();

        app('App\Http\Controllers\General_Controller')->
        PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Dokumen diterima'
                          ,1, $tokenList );
      }else{
        $company_type->verif = -1;

        $tokenList = DB::table('transaction_notif_token_labs')
                      ->where('transaction_notif_token_labs.lab_uji_id', $request->lab_uji_id)
                      ->pluck('transaction_notif_token_labs.token')
                       ->all();

        app('App\Http\Controllers\General_Controller')->
        PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Dokumen ditolak'
                          ,1, $tokenList );
      }
      $company_type->keterangan = $request->keterangan;
      $company_type->save();

      $lab_uji->need_verify_doc = 0;
      $lab_uji->save();

    }else if($lab_uji->company_type == 2){

      $company_type = transaction_lab_uji_doc_import::where('lab_uji_id', $request->lab_uji_id )->first();

      $company_type->akte_pendirian_perusahaan = $request->akte_pendirian_perusahaan;
      $company_type->ktp = $request->ktp;
      $company_type->npwp = $request->npwp;
      $company_type->surat_keterangan_domisili = $request->surat_keterangan_domisili;
      $company_type->manual_book = $request->manual_book;
      $company_type->api = $request->api;
      $company_type->siup = $request->siup;
      $company_type->tdp = $request->tdp;
      $company_type->surat_keagenan_negara = $request->surat_keagenan_negara;
      $company_type->surat_suku_cadang = $request->surat_suku_cadang;

      if($request->verif == 1){
        $company_type->verif = 1;
        $lab_uji->is_download = 1;
        
        $tokenList = DB::table('transaction_notif_token_labs')
                      ->where('transaction_notif_token_labs.lab_uji_id', $request->lab_uji_id)
                      ->pluck('transaction_notif_token_labs.token')
                       ->all();

        app('App\Http\Controllers\General_Controller')->
        PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Dokumen diterima'
                          ,1, $tokenList );
      }else{
        $company_type->verif = -1;

        $tokenList = DB::table('transaction_notif_token_labs')
                      ->where('transaction_notif_token_labs.lab_uji_id', $request->lab_uji_id)
                      ->pluck('transaction_notif_token_labs.token')
                       ->all();

        app('App\Http\Controllers\General_Controller')->
        PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Dokumen ditolak'
                          ,1, $tokenList );
      }
      $company_type->keterangan = $request->keterangan;
      $company_type->save();
      
      $lab_uji->need_verify_doc = 0;
      $lab_uji->save();
    }

    $final = array('message'=> "menerima dokumen berhasil");
    // return Response::download( storage_path($zip_file) , 'lab_uji_' . $request->lab_uji_id  . '_' . $lab_uji->email . '.zip'  );
    return array('status' => 1,'result'=>$final);
  }

  public function change_status_form(Request $request){

    $lab_uji_form = transaction_lab_uji_form::where('id', $request->form_uji_id )->
                                              first();
    if($lab_uji_form == null){
      $final = array('message'=> "lab uji form tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }
    $lab_uji_form->verif = $request->verif;
    $lab_uji_form->keterangan = $request->keterangan;

    if($request->verif == 1){

      $lab_uji_form->status_journey = 2;
      $lab_uji_form->save();
      $tokenList = DB::table('transaction_notif_token_labs')
                    ->where('transaction_notif_token_labs.lab_uji_id', $lab_uji_form->lab_uji_id)
                    ->pluck('transaction_notif_token_labs.token')
                     ->all();

      app('App\Http\Controllers\General_Controller')->
      PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Formulir diterima'
                        ,1, $tokenList );
    }else{

      $lab_uji_form->status_journey = -2;
      $lab_uji_form->is_admin_action = 0;
      $lab_uji_form->save();

      $tokenList = DB::table('transaction_notif_token_labs')
                    ->where('transaction_notif_token_labs.lab_uji_id', $lab_uji_form->lab_uji_id)
                    ->pluck('transaction_notif_token_labs.token')
                     ->all();

      app('App\Http\Controllers\General_Controller')->
      PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Formulir ditolak'
                        ,1, $tokenList );
    }

    $final = array('message'=> "mengganti status formulir berhasil");
    return array('status' => 1,'result'=>$final);
  }

  public function create_jadwal_uji(Request $request){

    $jadwal_uji = transaction_lab_uji_jadwal_uji::where('form_uji_id',$request->form_uji_id )
                                                  ->first();

    $lab_uji_form = transaction_lab_uji_form::where('id', $request->form_uji_id )->
                      first();
  
    if($jadwal_uji == null){
      $jadwal_uji = new transaction_lab_uji_jadwal_uji;
      $jadwal_uji->lab_uji_id = $request->lab_uji_id;
      $jadwal_uji->form_uji_id = $request->form_uji_id;
      
      $lab_uji_form->status_journey = 3;
    } 
    $jadwal_uji->tim_uji = $request->tim_uji;
    $jadwal_uji->waktu_uji_lab = $request->waktu_uji_lab;
    $jadwal_uji->waktu_uji_lapangan = $request->waktu_uji_lapangan;
    $jadwal_uji->lokasi_uji = $request->lokasi_uji;
    $jadwal_uji->save();

    $lab_uji_form->is_admin_action = 0;
    $lab_uji_form->save();

    $tokenList = DB::table('transaction_notif_token_labs')
                  ->where('transaction_notif_token_labs.lab_uji_id', $request->lab_uji_id)
                  ->pluck('transaction_notif_token_labs.token')
                   ->all();

    app('App\Http\Controllers\General_Controller')->
    PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Jadwal uji telah ditentukan'
                      ,1, $tokenList );

    $final = array('message'=> "membuat jadwal berhasil");
    return array('status' => 1,'result'=>$final);
  }

  public function change_status_jadwal_uji(Request $request){

    $lab_uji_form = transaction_lab_uji_form::where('id', $request->form_uji_id )->
                                              first();
    if($lab_uji_form == null){
      $final = array('message'=> "form uji tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }

    $lab_uji_form->status_journey = $request->status;
    // $lab_uji_form->is_admin_action = 0;
    $lab_uji_form->save();

    $tokenList = DB::table('transaction_notif_token_labs')
                  ->where('transaction_notif_token_labs.lab_uji_id', $lab_uji_form->lab_uji_id)
                  ->pluck('transaction_notif_token_labs.token')
                   ->all();

     $lab_uji_journey = DB::table('lab_uji_journey')
                   ->where('lab_uji_journey.id', $request->status)
                    ->first();

    app('App\Http\Controllers\General_Controller')->
    PostNotifMultiple('Alsintanlink',$tokenList, 'Status: ' . $lab_uji_journey->name
                      ,1, $tokenList );

    $final = array('message'=> "ganti status sukses");
    return array('status' => 1,'result'=>$final);
  }

  public function upload_kode_billing(Request $request){

    $validator = \Validator::make($request->all(), [
            'kode_billing' => 'required|file|mimes:pdf|max:2000', // max 2MB
        ], [
          'kode_billing.required' => 'kode_billing belum dipilih',
          'kode_billing.file' => 'kode_billing bukan file',
          'kode_billing.mimes' => 'kode_billing bukan pdf',
          'kode_billing.max' => 'ukuran kode_billing melewati 2MB'
        ]);

    if ($validator->fails()) {
      $final = array('message'=> $validator->errors()->first());
      return array('status' => 0,'result'=>$final);
    }

    $lab_uji = transaction_lab_uji_jadwal_uji::where('form_uji_id', $request->form_uji_id)->first();

    if($lab_uji == null){
      $final = array('message'=> "jadwal belum dibuat");
      return array('status' => 0,'result'=>$final);
    }
    // bukti pembayaran
    if($request->hasFile('kode_billing')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/kode_billing/' ,
          $request->file('kode_billing')
      );

      $path_url_manual_book = basename($upload);

      if($lab_uji->kode_billing != null){
        $this->delete_file(storage_path('app/public/lab_uji_upload/kode_billing/' . $lab_uji->kode_billing));
      }
      $lab_uji->kode_billing = $path_url_manual_book;
      $lab_uji->save();

      $lab_uji_id = lab_uji::find($lab_uji->lab_uji_id);

      Mail::to($lab_uji_id->email)->send(new Master_Kode_Billing($lab_uji));

      $lab_uji_form = transaction_lab_uji_form::where('id', $request->form_uji_id )->
                                                first();
      $lab_uji_form->status_journey = 5;
      $lab_uji_form->is_admin_action = 0;
      $lab_uji_form->save();

      $tokenList = DB::table('transaction_notif_token_labs')
                    ->where('transaction_notif_token_labs.lab_uji_id', $lab_uji->lab_uji_id)
                    ->pluck('transaction_notif_token_labs.token')
                     ->all();

      app('App\Http\Controllers\General_Controller')->
      PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Kode billing telah dikirim'
                        ,1, $tokenList );
    }

    $final = array('message'=> "upload kode billing berhasil");
    return array('status' => 1,'result'=>$final);
  }

  public function upload_hasil_laporan(Request $request){

    $validator = \Validator::make($request->all(), [
            'scan_hasil_uji' => 'required|file|mimes:pdf|max:2000', // max 2MB
        ], [
          'scan_hasil_uji.required' => 'hasil_laporan belum dipilih',
          'scan_hasil_uji.file' => 'hasil_laporan bukan file',
          'scan_hasil_uji.mimes' => 'hasil_laporan bukan pdf',
          'scan_hasil_uji.max' => 'ukuran hasil_laporan melewati 2MB'
        ]);

    if ($validator->fails()) {
      $final = array('message'=> $validator->errors()->first());
      return array('status' => 0,'result'=>$final);
    }

    // bukti pembayaran
    if($request->hasFile('scan_hasil_uji')){
      $upload = Storage::putFile(
          'public/lab_uji_upload/scan_hasil_uji/' ,
          $request->file('scan_hasil_uji')
      );

      $path_url_manual_book = basename($upload);
      $lab_uji = transaction_lab_uji_jadwal_uji::where('form_uji_id', $request->form_uji_id)->first();
      if($lab_uji->scan_hasil_uji != null){
        $this->delete_file(storage_path('app/public/lab_uji_upload/scan_hasil_uji/' . $lab_uji->scan_hasil_uji));
      }
      $lab_uji->scan_hasil_uji = $path_url_manual_book;
      $lab_uji->save();

      $lab_uji_form = transaction_lab_uji_form::where('id', $request->form_uji_id )->
                                                first();
      $lab_uji_form->status_journey = 10;
      $lab_uji_form->is_admin_action = 0;
      $lab_uji_form->save();

      $tokenList = DB::table('transaction_notif_token_labs')
                    ->where('transaction_notif_token_labs.lab_uji_id', $lab_uji_form->lab_uji_id)
                    ->pluck('transaction_notif_token_labs.token')
                     ->all();

      app('App\Http\Controllers\General_Controller')->
      PostNotifMultiple('Alsintanlink',$tokenList, 'Status: Hasil laporan telah dikirim'
                        ,1, $tokenList );
    }

    $final = array('message'=> "upload hasil laporan berhasil");
    return array('status' => 1,'result'=>$final);
  }

  private function delete_file($url){

    if(file_exists($url)){
      unlink($url);
    }
  }

  
  public function download_zip_file(Request $request){

    $zip_file = 'invoices.zip'; // Name of our archive to download
    // Initializing PHP class
    $zip = new ZipArchive;
    $zip->open( storage_path($zip_file) , ZipArchive::CREATE | ZipArchive::OVERWRITE );
   
    $lab_uji = lab_uji::find( $request->lab_uji_id );
    
    if($lab_uji->company_type == 0){

      $document = transaction_lab_uji_doc_perorangan::where('lab_uji_id' ,  $request->lab_uji_id)->first();
      $document->save();

      $lab_uji->is_download = 2;
      $lab_uji->save();

      $ktp= $document->url_ktp  ;
      $npwp= $document->url_npwp  ;
      
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/perorangan/ktp/' . $ktp) , 'ktp/' .$ktp );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/perorangan/npwp/' . $npwp) , 'npwp/' .$npwp );
      $zip->close();
     
    }else if($lab_uji->company_type == 1){

      $document = transaction_lab_uji_doc_dalam_negeri::where('lab_uji_id' ,  $request->lab_uji_id)->first();
      $document->save();

      $lab_uji->is_download = 2;
      $lab_uji->save();

      $ktp= $document->url_ktp  ;
      $npwp= $document->url_npwp  ;
      $surat_keterangan_domisili= $document->url_surat_keterangan_domisili  ;
      $akte_pendirian_perusahaan= $document->url_akte_pendirian_perusahaan  ;
      $siup= $document->url_siup  ;
      $tdp= $document->url_tdp  ;
      $surat_suku_cadang= $document->url_surat_suku_cadang  ;
      
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/ktp/' . $ktp) , 'ktp/' .$ktp );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/npwp/' . $npwp) , 'npwp/' .$npwp );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/surat_keterangan_domisili/' . $surat_keterangan_domisili) , 'surat_keterangan_domisili/' .$surat_keterangan_domisili );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/akte_pendirian_perusahaan/' . $akte_pendirian_perusahaan) , 'akte_pendirian_perusahaan/' .$akte_pendirian_perusahaan );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/siup/' . $siup) , 'siup/' .$siup );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/tdp/' . $tdp) , 'tdp/' .$tdp );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/surat_suku_cadang/' . $surat_suku_cadang) , 'surat_suku_cadang/' .$surat_suku_cadang );
      $zip->close();

    }else if($lab_uji->company_type == 2){

      $document = transaction_lab_uji_doc_import::where('lab_uji_id' ,  $request->lab_uji_id)->first();
      $document->save();

      $lab_uji->is_download = 2;
      $lab_uji->save();
      
      $ktp= $document->url_ktp  ;
      $npwp= $document->url_npwp  ;
      $surat_keterangan_domisili= $document->url_surat_keterangan_domisili  ;
      $akte_pendirian_perusahaan= $document->url_akte_pendirian_perusahaan  ;
      $siup= $document->url_siup  ;
      $tdp= $document->url_tdp  ;
      $surat_suku_cadang= $document->url_surat_suku_cadang  ;
      $api= $document->url_api  ;
      $surat_keagenan_negara= $document->url_surat_keagenan_negara  ;

      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/import/ktp/' . $ktp) , 'ktp/' .$ktp );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/import/npwp/' . $npwp) , 'npwp/' .$npwp );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/import/surat_keterangan_domisili/' . $surat_keterangan_domisili) , 'surat_keterangan_domisili/' .$surat_keterangan_domisili );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/import/akte_pendirian_perusahaan/' . $akte_pendirian_perusahaan) , 'akte_pendirian_perusahaan/' .$akte_pendirian_perusahaan );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/import/siup/' . $siup) , 'siup/' .$siup );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/import/tdp/' . $tdp) , 'tdp/' .$tdp );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/import/surat_suku_cadang/' . $surat_suku_cadang) , 'surat_suku_cadang/' .$surat_suku_cadang );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/import/api/' . $api) , 'api/' .$api );
      $zip->addFile(storage_path('app/public/lab_uji_upload/doc/import/surat_keagenan_negara/' . $surat_keagenan_negara) , 'surat_keagenan_negara/' .$surat_keagenan_negara );
      $zip->close();

    }
        
    return Response::download( storage_path($zip_file) , 'lab_uji_' . $request->lab_uji_id  . '_' . $lab_uji->email . '.zip'  );
  }


  public function clear_zip_file(Request $request){

  
    $lab_uji = lab_uji::find( $request->lab_uji_id );
    
    if($lab_uji->company_type == 0){

      $document = transaction_lab_uji_doc_perorangan::where('lab_uji_id' ,  $request->lab_uji_id)->first();
      $document->save();

      $lab_uji->is_download = 0;
      $lab_uji->save();

      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/perorangan/ktp/' . $document->url_ktp) , $document->url_ktp);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/perorangan/npwp/' . $document->url_npwp) , $document->url_npwp);
      
    }else if($lab_uji->company_type == 1){

      $document = transaction_lab_uji_doc_dalam_negeri::where('lab_uji_id' ,  $request->lab_uji_id)->first();
      $document->save();

      $lab_uji->is_download = 0;
      $lab_uji->save();

      $ktp= $document->url_ktp  ;
      $npwp= $document->url_npwp  ;
      $surat_keterangan_domisili= $document->url_surat_keterangan_domisili  ;
      $akte_pendirian_perusahaan= $document->url_akte_pendirian_perusahaan  ;
      $siup= $document->url_siup  ;
      $tdp= $document->url_tdp  ;
      $surat_suku_cadang= $document->url_surat_suku_cadang  ;

      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/akte_pendirian_perusahaan/' . $document->url_akte_pendirian_perusahaan) , $document->url_akte_pendirian_perusahaan);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/ktp/' . $document->url_ktp) , $document->url_ktp);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/npwp/' . $document->url_npwp) , $document->url_npwp);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/surat_keterangan_domisili/' . $document->url_surat_keterangan_domisili) , $document->url_surat_keterangan_domisili);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/siup/' . $document->url_siup) , $document->url_siup);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/tdp/' . $document->url_tdp) , $document->url_tdp);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/dalam_negeri/surat_suku_cadang/' . $document->url_surat_suku_cadang) , $document->url_surat_suku_cadang);

    }else if($lab_uji->company_type == 2){

      $document = transaction_lab_uji_doc_import::where('lab_uji_id' ,  $request->lab_uji_id)->first();
      $document->save();

      $lab_uji->is_download = 0;
      $lab_uji->save();
      
      $ktp= $document->url_ktp  ;
      $npwp= $document->url_npwp  ;
      $surat_keterangan_domisili= $document->url_surat_keterangan_domisili  ;
      $akte_pendirian_perusahaan= $document->url_akte_pendirian_perusahaan  ;
      $siup= $document->url_siup  ;
      $tdp= $document->url_tdp  ;
      $surat_suku_cadang= $document->url_surat_suku_cadang  ;
      $api= $document->url_api  ;
      $surat_keagenan_negara= $document->url_surat_keagenan_negara  ;

      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/akte_pendirian_perusahaan/' . $document->url_akte_pendirian_perusahaan) , $document->url_akte_pendirian_perusahaan);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/api/' . $document->url_api) , $document->url_api);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/ktp/' . $document->url_ktp) , $document->url_ktp);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/npwp/' . $document->url_npwp) , $document->url_npwp);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/surat_keterangan_domisili/' . $document->url_surat_keterangan_domisili) ,$document->url_surat_keterangan_domisili);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/siup/' . $document->url_siup) , $document->url_siup);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/tdp/' . $document->url_tdp) , $document->url_tdp);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/surat_suku_cadang/' . $document->url_surat_suku_cadang) ,$document->url_surat_suku_cadang);
      $this->delete_file(storage_path('app/public/lab_uji_upload/doc/import/surat_keagenan_negara/' . $document->url_surat_keagenan_negara) , $document->url_surat_keagenan_negara);

    }
        
    $final = array('message'=> "clear document berhasil");
    return array('status' => 1,'result'=>$final);
  }

}