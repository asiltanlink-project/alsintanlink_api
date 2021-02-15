<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use App\Models\Upja;
use App\Models\Alsin;
use App\Mail\Upja_Verif;
use App\Models\Alsin_item;
use Illuminate\Http\Request;
use App\Models\Transaction_order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Mail\Upja_Forget_Password;
use Illuminate\Support\Facades\Mail;
use App\Models\Transaction_order_type;
use App\Models\Transaction_order_child;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Helpers\LogActivity as Helper;

use App\Models\Other_Service\transaction_order_rmu;
use App\Models\Other_Service\transaction_order_rice;
use App\Models\Other_Service\transaction_order_training;
use App\Models\Other_Service\transaction_order_reparation;
use App\Models\Other_Service\transaction_order_rice_seed;
use App\Models\Other_Service\transaction_order_spare_part;

use App\Models\Upja_Ownership\transaction_upja_training;
use App\Models\Upja_Ownership\transaction_upja_rice_seed;
use App\Models\Upja_Ownership\transaction_upja_reparation;
use App\Models\Upja_Ownership\trasansaction_upja_spare_part;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

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

             $final = array('message' => 'login sukses','token' => $token, 'upja' => $fixed_user);
             return array('status' => 1, 'result' => $final);
          }else{

            $final = null;
            if(filter_var($request->email, FILTER_VALIDATE_EMAIL)){
                //send email
                $final = array('message'=>"gagal email belum verif");
            }else  if(preg_match('/^[0-9]{3,15}+$/', $request->email)){

              $digits = 4;
              $user->otp_code = rand(pow(10, $digits-1), pow(10, $digits)-1);
              $user->save();

              $final = array('message'=>"gagal phone number belum verif", 'otp_code' => $user->otp_code);
            }

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
          'email' => 'required|unique:upjas',
          'name' => 'required',
          'leader_name' => 'required',
          'password' => 'required',
          'district' => 'required',
      ];
  }

  public function rules_update()
  {
      return [
          'name' => 'required',
          'leader_name' => 'required',
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
    $blog->legality = $request->legality;
    $blog->password = Hash::make($request->password);
    $blog->save();

    $this->other_service_update($request->rice ,$blog->id , 9 );
    $this->other_service_update($request->rice_seed ,$blog->id , 10 );
    $this->other_service_update($request->rmu ,$blog->id , 8 );
    $this->other_service_update($request->reparation ,$blog->id , 11 );
    $this->other_service_update($request->training ,$blog->id , 13 );
    $this->other_service_update($request->spare_part ,$blog->id , 12 );

    if(filter_var($request->email, FILTER_VALIDATE_EMAIL)){
        //send email
        Mail::to($request->email)->send(new Upja_Verif($blog));
    }else  if(preg_match('/^[0-9]{3,15}+$/', $request->email)){
      // send otp
      $digits = 4; // Amount of digits
      $blog->otp_code = rand(pow(10, $digits-1), pow(10, $digits)-1);
      $blog->save();

      $userkey = env("zenziva_userkey");
      $passkey = env("zenziva_passkey");
      $telepon = $request->email;
      $otp = $blog->otp_code;

      $url = 'https://console.zenziva.net/reguler/api/sendOTP/';
      $curlHandle = curl_init();
      curl_setopt($curlHandle, CURLOPT_URL, $url);
      curl_setopt($curlHandle, CURLOPT_HEADER, 0);
      curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($curlHandle, CURLOPT_TIMEOUT,30);
      curl_setopt($curlHandle, CURLOPT_POST, 1);
      curl_setopt($curlHandle, CURLOPT_POSTFIELDS, array(
          'userkey' => $userkey,
          'passkey' => $passkey,
          'to' => $telepon,
          'kode_otp' => $otp
      ));
      $results = json_decode(curl_exec($curlHandle), true);
      curl_close($curlHandle);
    }

    $final = array('message'=>'register succsess', 'otp_code'=>$blog->otp_code, 'upja'=>$blog);
    return array('status' => 1,'result'=>$final);
  }

  private function other_service_update($request , $upja_id, $index){

    if($request == 1){
      $new_alsin = new Alsin;
      $new_alsin->upja_id = $upja_id;
      $new_alsin->alsin_type_id = $index;
      $new_alsin->save();
    }
  }

  private function update_rice_seed($request , $upja_id, $rice_seed_id){

    if($request == 1){
      $new_alsin = new transaction_upja_rice_seed;
      $new_alsin->upja_id = $upja_id;
      $new_alsin->rice_seed_id = $rice_seed_id;
      $new_alsin->save();
    }
  }

  private function update_reparation($request , $upja_id, $reparation_id){

    if($request == 1){
      $new_alsin = new transaction_upja_reparation;
      $new_alsin->upja_id = $upja_id;
      $new_alsin->alsin_type_id = $reparation_id;
      $new_alsin->save();
    }
  }

  private function update_sparepart($request , $upja_id, $sparepart_id){

    if($request == 1){
      $new_alsin = new trasansaction_upja_spare_part;
      $new_alsin->upja_id = $upja_id;
      $new_alsin->spare_part_id = $sparepart_id;
      $new_alsin->save();
    }
  }

  private function update_training($request , $upja_id, $training_id){

    if($request == 1){
      $new_alsin = new transaction_upja_training;
      $new_alsin->upja_id = $upja_id;
      $new_alsin->training_id = $training_id;
      $new_alsin->save();
    }
  }

  private function other_service_ownership($request , $upja_id, $other_sevice_id){

    if($other_sevice_id == 9){
      if($request == 1){
        $new_alsin = new transaction_upja_rice_seed;
        $new_alsin->upja_id = $upja_id;
        $new_alsin->rice_seed_id = $request[$i]['id'];
        $new_alsin->save();
      }
    }else if($other_sevice_id == 11){
      if($request == 1){
        $new_alsin = new transaction_upja_reparation;
        $new_alsin->upja_id = $upja_id;
        $new_alsin->alsin_type_id = $request[$i]['id'];
        $new_alsin->save();
      }
    }
    else if($other_sevice_id == 12){
      if($request == 1){
        $new_alsin = new trasansaction_upja_spare_part;
        $new_alsin->upja_id = $upja_id;
        $new_alsin->spare_part_id = $request[$i]['id'];
        $new_alsin->save();
      }
    }
    else if($other_sevice_id == 13){
      if($request == 1){
        $new_alsin = new transaction_upja_training;
        $new_alsin->upja_id = $upja_id;
        $new_alsin->training_id = $request[$i]['id'];
        $new_alsin->save();
      }
    }
  }

  private function other_service_clear($upja_id){

    // $new_alsin = Alsin::where('alsins.upja_id', $upja_id)->get();
    // for($i = 0 ; $i < sizeof ($new_alsin)  ; $i ++){
    //     $new_alsin[$i]->delete();
    // }

    $rice_seed = transaction_upja_rice_seed::where('upja_id', $upja_id)->get();
    for($i = 0 ; $i < sizeof ($rice_seed)  ; $i ++){
        $rice_seed[$i]->delete();
    }

    $reparation = transaction_upja_reparation::where('upja_id', $upja_id)->get();
    for($i = 0 ; $i < sizeof ($reparation)  ; $i ++){
        $reparation[$i]->delete();
    }

    $spare_part = trasansaction_upja_spare_part::where('upja_id', $upja_id)->get();
    for($i = 0 ; $i < sizeof ($spare_part)  ; $i ++){
        $spare_part[$i]->delete();
    }

    $training = transaction_upja_training::where('upja_id', $upja_id)->get();
    for($i = 0 ; $i < sizeof ($training)  ; $i ++){
        $training[$i]->delete();
    }
  }

  public function submit_otp(Request $request){

    $user = Upja::where('email', $request->email )
                    ->first();

    if($user == null){
      $final = array('message'=> "upja not found");
      return array('status' => 0 ,'result'=>$final);
    }
    $user->email_verify = 1;
    $user->save();

    $final = array('message'=>'submit otp succsess');
    return array('status' => 1,'result'=>$final);
  }

  public function reset_otp(Request $request){

    $user = Upja::where('email', $request->email )
                    ->first();

    if($user == null){
      $final = array('message'=> "upja not found");
      return array('status' => 0 ,'result'=>$final);
    }
    $user->otp_code = null;
    $user->save();

    $final = array('message'=>'reset otp succsess');
    return array('status' => 1,'result'=>$final);
  }

  public function resend_otp(Request $request){

    $user = Upja::where('email', $request->email )
                    ->first();
    // dd(env("zenziva_userkey"));             ;
    if($user == null){
      $final = array('message'=> "upja not found");
      return array('status' => 0 ,'result'=>$final);
    }
    $digits = 4; // Amount of digits
    $user->otp_code = rand(pow(10, $digits-1), pow(10, $digits)-1);
    $user->save();

    $userkey = env("zenziva_userkey");
    $passkey = env("zenziva_passkey");
    $telepon = $request->email;
    $otp = $user->otp_code;

    $url = 'https://console.zenziva.net/reguler/api/sendOTP/';
    $curlHandle = curl_init();
    curl_setopt($curlHandle, CURLOPT_URL, $url);
    curl_setopt($curlHandle, CURLOPT_HEADER, 0);
    curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curlHandle, CURLOPT_TIMEOUT,30);
    curl_setopt($curlHandle, CURLOPT_POST, 1);
    curl_setopt($curlHandle, CURLOPT_POSTFIELDS, array(
        'userkey' => $userkey,
        'passkey' => $passkey,
        'to' => $telepon,
        'kode_otp' => $otp
    ));
    $results = json_decode(curl_exec($curlHandle), true);
    curl_close($curlHandle);

    $final = array('message'=>'resend otp succsess','otp_code'=>$user->otp_code);
    return array('status' => 1,'result'=>$final);
  }

  public function forget_password(Request $request){

    $upja = Upja::where('email', $request->email)->first();
    if($upja == null){
      $final = array('message'=>'Email tidak terdaftar');
      return array('status' => 0,'result'=>$final);
    }

    if(filter_var($request->email, FILTER_VALIDATE_EMAIL)){
        //send email
        Mail::to($request->email)->send(new Upja_Forget_Password($upja));
    }else  if(preg_match('/^[0-9]{3,15}+$/', $request->email)){

      // send otp
      $userkey = 'd27a72ddaf0b';
      $passkey = '4ba83675fd17f25c721dbb6d';
      $telepon = $request->email;
      $message = 'Anda meminta untuk melakukan reset password. silahkan klik link berikut ' .
                 'http://alsintanlink.com/general/upja_forget_form/' . $upja->id;
      $url = 'https://console.zenziva.net/reguler/api/sendsms/';
      $curlHandle = curl_init();
      curl_setopt($curlHandle, CURLOPT_URL, $url);
      curl_setopt($curlHandle, CURLOPT_HEADER, 0);
      curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($curlHandle, CURLOPT_TIMEOUT,30);
      curl_setopt($curlHandle, CURLOPT_POST, 1);
      curl_setopt($curlHandle, CURLOPT_POSTFIELDS, array(
          'userkey' => $userkey,
          'passkey' => $passkey,
          'to' => $telepon,
          'message' => $message
      ));
      $results = json_decode(curl_exec($curlHandle), true);
      curl_close($curlHandle);
    }

    $final = array('message'=>'Forger Password Succsess');
    return array('status' => 1,'result'=>$final);
  }

  public function upja_verif_succsess(Request $request){

    $upja = Upja::find($request->upja_id);
    if($upja == null){
      $final = array('message'=>'upja tidak ditemukan');
      return array('status' => 0,'result'=>$final);
    }
    $upja->email_verify = 1;
    $upja->save();

    return view('email/upja_verif_succsess');
  }

  public function upja_forget_form(Request $request){

    $upja = Upja::find($request->upja_id);
    return view('email/upja_forget_password_form',['upja_id' => $request->upja_id]);
  }
  public function upja_forget_password_succsess(Request $request){

    return view('email/upja_forget_password_succsess',['upja_id' => $request->upja_id]);
  }

  public function show_detail_upja(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $upja = Upja::select('upjas.id','upjas.email','upjas.name','upjas.leader_name','upjas.class',
                        'upjas.legality','indoregion_provinces.name as province','indoregion_regencies.name as city',
                        'indoregion_districts.name as district','indoregion_villages.name as village',
                        'indoregion_provinces.id as province_id','indoregion_regencies.id as city_id',
                        'indoregion_districts.id as district_id','indoregion_villages.id as village_id',
                        DB::raw("(select 0) as rice, (select 0) as rice_seed, (select 0) as rmu,
                                 (select 0) as reparation, (select 0) as training, (select 0) as spare_part")
                        )
                 ->Join ('indoregion_provinces', 'indoregion_provinces.id', '=', 'upjas.province')
                 ->Join ('indoregion_regencies', 'indoregion_regencies.id', '=', 'upjas.city')
                 ->Join ('indoregion_districts', 'indoregion_districts.id', '=', 'upjas.district')
                 ->Join ('indoregion_villages', 'indoregion_villages.id', '=', 'upjas.village')
                 ->find( $user_id );

    $other_service = Alsin::select('alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name')
                      ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                      ->where('alsin_types.alsin_other' ,  1)
                      ->where('upja_id' ,  $user_id )
                      ->get();

    $reparations = transaction_upja_reparation::
                        select('alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name')
                      ->Join ('alsin_types', 'transaction_upja_reparations.alsin_type_id',
                              '=', 'alsin_types.id')
                      ->where('transaction_upja_reparations.upja_id' ,  $user_id )
                      ->get();

    $training = transaction_upja_training::
                        select('trainings.id', 'trainings.name')
                      ->Join ('trainings', 'trainings.id', '=', 'transaction_upja_trainings.training_id')
                      ->where('transaction_upja_trainings.upja_id' ,  $user_id )
                      ->get();

    $rice_seed = transaction_upja_rice_seed::
                        select('rice_seeds.id', 'rice_seeds.name')
                      ->Join ('rice_seeds', 'rice_seeds.id', '=', 'transaction_upja_rice_seeds.rice_seed_id')
                      ->where('transaction_upja_rice_seeds.upja_id' ,  $user_id )
                      ->get();


    for($i =0 ; $i < sizeof($other_service) ; $i++){
      $this->convert_other_service($other_service[$i]->alsin_type_id ,$upja );
    }
    for($i =0 ; $i < sizeof($reparations) ; $i++){
      $this->convert_upja_reparation($reparations[$i]->alsin_type_id ,$upja );
    }
    for($i =0 ; $i < sizeof($training) ; $i++){
      $this->convert_upja_training($training[$i]->id ,$upja );
    }
    for($i =0 ; $i < sizeof($rice_seed) ; $i++){
      $this->convert_upja_rice_seed($rice_seed[$i]->id ,$upja );
    }

    $final = array('upja'=>$upja);
    return array('status' => 1 ,'result'=>$final);
  }

  private function convert_other_service($alsin_type_id, $upja){

    if($alsin_type_id == 8){
      $upja->rmu = 1;
    }else if($alsin_type_id == 9){
      $upja->rice_seed = 1;
    }else if($alsin_type_id == 10){
      $upja->rice = 1;
    }else if($alsin_type_id == 11){
      $upja->reparation = 1;
    }else if($alsin_type_id == 12){
      $upja->spare_part = 1;
    }else if($alsin_type_id == 13){
      $upja->training = 1;
    }
  }

  private function convert_upja_training($training_id, $upja){

    if($training_id == 1){
      $upja->trainingOperator = 1;
    }else if($training_id == 2){
      $upja->trainingPerawatan = 1;
    }else if($training_id == 3){
      $upja->trainingPerbaikan = 1;
    }else if($training_id == 4){
      $upja->trainingPembengkelan = 1;
    }else if($training_id == 5){
      $upja->trainingPembibitan = 1;
    }
  }

  private function convert_upja_reparation($alsin_type_id, $upja){

    if($alsin_type_id == 1){
      $upja->traktorRoda2 = 1;
    }else if($alsin_type_id == 2){
      $upja->traktorRoda4 = 1;
    }else if($alsin_type_id == 3){
      $upja->pompa = 1;
    }else if($alsin_type_id == 4){
      $upja->transplanter = 1;
    }else if($alsin_type_id == 5){
      $upja->powerWeeder = 1;
    }else if($alsin_type_id == 6){
      $upja->combineHarvester = 1;
    }else if($alsin_type_id == 7){
      $upja->dryer = 1;
    }
  }

  private function convert_upja_rice_seed($alsin_type_id, $upja){

    if($alsin_type_id == 1){
      $upja->benihPadiInpari30 = 1;
    }else if($alsin_type_id == 2){
      $upja->benihPadiInpari33 = 1;
    }else if($alsin_type_id == 3){
      $upja->benihPadiInpari42 = 1;
    }

    else if($alsin_type_id == 4){
      $upja->benihPadiInpari43 = 1;
    }else if($alsin_type_id == 5){
      $upja->benihPadiCiherang = 1;
    }else if($alsin_type_id == 6){
      $upja->benihPadiIR64 = 1;
    }

    else if($alsin_type_id == 7){
      $upja->benihPadiInpago12 = 1;
    }else if($alsin_type_id == 8){
      $upja->benihPadiMembramo = 1;
    }else if($alsin_type_id == 9){
      $upja->benihPadiRindang = 1;
    }

    else if($alsin_type_id == 10){
      $upja->benihPadiSintanur = 1;
    }else if($alsin_type_id == 11){
      $upja->benihPadiSitubagendit = 1;
    }else if($alsin_type_id == 12){
      $upja->benihPadiTrabas = 1;
    }
  }

  private function convert_other_ownership($alsin_type_id, $upja){

    if($alsin_type_id == 8){
      $upja->rmu = 1;
    }else if($alsin_type_id == 9){
      $upja->rice_seed = 1;
    }else if($alsin_type_id == 10){
      $upja->rice = 1;
    }else if($alsin_type_id == 11){
      $upja->reparation = 1;
    }else if($alsin_type_id == 12){
      $upja->spare_part = 1;
    }else if($alsin_type_id == 13){
      $upja->training = 1;
    }
  }

  public function update_upja(Request $request){

    $validator = \Validator::make($request->all(), $this->rules_update());

    if ($validator->fails()) {

      $final = array('message'=>$validator->errors()->first());
      return array('status' => 0,'result' => $final) ;
    }

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $blog = Upja::find($user_id);
    $blog->name = $request->name;
    $blog->province = $request->province;
    $blog->city = $request->city;
    $blog->district = $request->district;
    $blog->village = $request->village;
    $blog->leader_name = $request->leader_name;

    $blog->class = $request->class;
    $blog->legality = $request->legality;
    $blog->save();

    $this->other_service_clear($user_id);

    $this->other_service_update($request->rice ,$user_id , 9 );
    $this->other_service_update($request->rice_seed ,$user_id , 10 );
    $this->other_service_update($request->rmu ,$user_id , 8 );
    $this->other_service_update($request->reparation ,$user_id , 11 );
    $this->other_service_update($request->training ,$user_id , 13 );
    $this->other_service_update($request->spare_par ,$user_id , 12 );

    // benih padi
    $this->update_rice_seed($request->benihPadiInpari30 ,$user_id , 1 );
    $this->update_rice_seed($request->benihPadiInpari33 ,$user_id , 2 );
    $this->update_rice_seed($request->benihPadiInpari42 ,$user_id , 3 );
    $this->update_rice_seed($request->benihPadiInpari43 ,$user_id , 4 );
    $this->update_rice_seed($request->benihPadiCiherang ,$user_id , 5 );
    $this->update_rice_seed($request->benihPadiIR64 ,$user_id , 6 );
    $this->update_rice_seed($request->benihPadiInpago12 ,$user_id , 7 );
    $this->update_rice_seed($request->benihPadiMembramo ,$user_id , 8 );
    $this->update_rice_seed($request->benihPadiRindang ,$user_id , 9 );
    $this->update_rice_seed($request->benihPadiSintanur ,$user_id , 10 );
    $this->update_rice_seed($request->benihPadiSitubagendit ,$user_id , 11 );
    $this->update_rice_seed($request->benihPadiTrabas ,$user_id , 12 );

    // reparation
    $this->update_reparation($request->traktorRoda2 ,$user_id , 1 );
    $this->update_reparation($request->traktorRoda4 ,$user_id , 2 );
    $this->update_reparation($request->pompa ,$user_id , 3 );
    $this->update_reparation($request->transplanter ,$user_id , 4 );
    $this->update_reparation($request->powerWeeder ,$user_id , 5 );
    $this->update_reparation($request->combineHarvester ,$user_id , 6 );
    $this->update_reparation($request->dryer ,$user_id , 7 );

    // training
    $this->update_training($request->trainingOperator ,$user_id , 1 );
    $this->update_training($request->trainingPerawatan ,$user_id , 2 );
    $this->update_training($request->trainingPerbaikan ,$user_id , 3 );
    $this->update_training($request->trainingPembengkelan ,$user_id , 4 );
    $this->update_training($request->trainingPembibitan ,$user_id , 5 );

    $final = array('message'=>'update succsess', 'upja'=>$blog);
    return array('status' => 1,'result'=>$final);
  }


  public function insert_alsin(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    for($i = 0; $i < sizeof($request["alsins"]) ; $i ++){

      $check_alsin = Alsin::select('alsins.id', 'alsin_types.name')
                          ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                          ->where('upja_id', $user_id)
                          ->where('alsin_type_id', $request["alsins"][$i]["alsin_type_id"])
                          ->first();

      if($check_alsin != null){

        for($j = 0; $j < $request["alsins"][$i]["total_item"] ; $j ++){

          $alsin_item = new Alsin_item;
          $alsin_item->alsin_id = $check_alsin->id;
          $alsin_item->save();
        }

      }else{

        $alsin = new Alsin;
        $alsin->upja_id = $user_id;
        $alsin->alsin_type_id = $request["alsins"][$i]["alsin_type_id"];
        $alsin->cost = $request["alsins"][$i]["cost"];
        $alsin->save();

        for($j = 0; $j < $request["alsins"][$i]["total_item"] ; $j ++){

          $alsin_item = new Alsin_item;
          $alsin_item->alsin_id = $alsin->id;
          $alsin_item->save();
        }
      }
    }

    $final = array('message'=>'insert succsess');
    return array('status' => 1,'result'=>$final);
  }

  public function show_all_alsin( ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $alsins = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id as alsin_type_id','alsin_types.name'
                                ,'alsin_types.picture_detail'
                                ,DB::raw("(select count(alsin_items.id)
                                        FROM alsin_items
                                        WHERE (alsin_items.alsin_id = alsins.id)
                                        AND (alsin_items.status = 'Tersedia')
                                      ) as available
                                   ")
                                   ,DB::raw("(select count(alsin_items.id)
                                           FROM alsin_items
                                           WHERE (alsin_items.alsin_id = alsins.id)
                                           AND (alsin_items.status = 'Sedang Digunakan')
                                         ) as not_available
                                      "),DB::raw("(select count(alsin_items.id)
                                              FROM alsin_items
                                              WHERE (alsin_items.alsin_id = alsins.id)
                                              AND (alsin_items.status = 'Rusak')
                                            ) as rusak
                                         ")
                                      ,DB::raw("(select (available + not_available + rusak)
                                            ) as total_item
                                         ")
                                   )
                      ->Where('upjas.id', $user_id )
                      ->Where('alsin_types.alsin_other' , 0 )
                      ->groupBy('alsins.id','alsin_types.id','alsin_types.name'
                                ,'alsins.cost','alsin_types.picture_detail')
                      ->get();

    $final = array('alsins'=>$alsins);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_alsin(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $alsin = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id as alsin_type_id','alsin_types.name'
                                ,'alsins.cost','alsin_types.picture_detail'
                                ,DB::raw("(select count(alsin_items.id)
                                        FROM alsin_items
                                        WHERE (alsin_items.alsin_id = alsins.id)
                                        AND (alsin_items.status = 'Tersedia')
                                      ) as available
                                   ")
                                   ,DB::raw("(select count(alsin_items.id)
                                           FROM alsin_items
                                           WHERE (alsin_items.alsin_id = alsins.id)
                                           AND (alsin_items.status = 'Sedang Digunakan')
                                         ) as not_available
                                      ")
                                      ,DB::raw("(select (available + not_available)
                                            ) as total_item
                                         ")
                                   )
                      ->Where('upjas.id', $user_id )
                      ->Where('alsin_types.id', $request->alsin_type_id )
                      ->groupBy('alsins.id','alsin_types.id','alsin_types.name'
                                ,'alsins.cost','alsin_types.picture_detail')
                      ->first();

    $alsin_items = DB::table('alsin_items')
                       ->select('alsin_items.id as alsin_item_id', 'alsin_items.vechile_code',
                                'alsin_items.status', 'alsin_items.description')
                      ->Where('alsin_items.alsin_id',  $alsin->alsin_id )
                      ->paginate(10);

    $max_page = round($alsin_items->total() / 10);
    $current_page =$alsin_items->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }
    $final = array('alsin'=>$alsin, 'alsin_items'=>$alsin_items,
                   'current_page'=>$current_page,
                   'max_page'=>$max_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function update_alsin(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $alsin = Alsin::find($request->alsin_id);
    if($alsin == null){
      $final = array('message'=>'alsin item not found');
      return array('status' => 0,'result'=>$final);
    }
    if($alsin->upja_id != $user_id){
      $final = array('message'=>'anda tidak dapat update item ini');
      return array('status' => 0,'result'=>$final);
    }
    $alsin->cost = $request->cost;
    $alsin->save();

    $final = array('message'=>'update succsess');
    return array('status' => 1,'result'=>$final);
  }

  public function update_alsin_item(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $alsin = Alsin_item::select('alsin_items.id','alsins.upja_id','alsin_items.vechile_code',
                                'alsin_items.description','alsin_items.status')
                        ->where('alsin_items.id' , $request->alsin_item_id)
                        ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                        ->first();

    if($alsin == null){
      $final = array('message'=>'item tidak ditemukan');
      return array('status' => 0,'result'=>$final);
    }
    if($alsin->upja_id != $user_id){
      $final = array('message'=>'anda tidak dapat update item ini');
      return array('status' => 0,'result'=>$final);
    }
    $alsin->vechile_code = $request->vechile_code;
    $alsin->status = $request->status;
    $alsin->description = $request->description;
    $alsin->save();

    $final = array('message'=>'update succsess');
    return array('status' => 1,'result'=>$final);
  }

  public function delete_alsin(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $alsin = Alsin::find($request->alsin_id);
    if($alsin == null){
      $final = array('message'=>'alsin not found');
      return array('status' => 0,'result'=>$final);
    }
    if($alsin->upja_id != $user_id){
      $final = array('message'=>'anda tidak dapat hapus item ini');
      return array('status' => 0,'result'=>$final);
    }
    $alsin->delete();

    $final = array('message'=>'delete succsess');
    return array('status' => 1,'result'=>$final);
  }

  public function delete_alsin_item(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $alsin = Alsin_item::select('alsins.upja_id')
                        ->where('alsin_items.id' , $request->alsin_item_id)
                        ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                        ->first();

    if($alsin == null){
      $final = array('message'=>'alsin item not found');
      return array('status' => 0,'result'=>$final);
    }
    if($alsin->upja_id != $user_id){
      $final = array('message'=>'anda tidak dapat hapus item ini');
      return array('status' => 0,'result'=>$final);
    }

    $alsin = Alsin_item::find( $request->alsin_item_id);
    $alsin->delete();

    $final = array('message'=>'delete succsess');
    return array('status' => 1,'result'=>$final);
  }

  public function show_all_transaction(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    if($request->status == ""){
      $transactions = DB::table('transaction_orders')
                         ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                  , 'transaction_orders.total_cost', 'transaction_orders.status', 'transaction_orders.status'
                                  , 'farmers.id as farmer_id', 'farmers.name as farmer_name'
                                  ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                  ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                  )
                        ->Join ('farmers', 'farmers.id', '=', 'transaction_orders.farmer_id')
                        ->Where('transaction_orders.upja_id',  $user_id )
                        ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                                 , 'transaction_orders.total_cost', 'transaction_orders.status'
                                 , 'transaction_orders.delivery_time'
                                 , 'transaction_orders.created_at', 'farmers.id', 'farmers.name'
                                 , 'transaction_orders.status')
                        ->paginate(10);

    }else{
      $transactions = DB::table('transaction_orders')
                         ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                  ,'transaction_orders.total_cost', 'transaction_orders.status', 'transaction_orders.status'
                                  ,'farmers.id as farmer_id', 'farmers.name as farmer_name'
                                  ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                  ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                  )
                        ->Join ('farmers', 'farmers.id', '=', 'transaction_orders.farmer_id')
                        ->Where('transaction_orders.upja_id',  $user_id )
                        ->Where('transaction_orders.status',  $request->status)
                        ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                                 , 'transaction_orders.total_cost', 'transaction_orders.status'
                                 , 'transaction_orders.delivery_time'
                                 , 'transaction_orders.created_at', 'farmers.id', 'farmers.name'
                                 , 'transaction_orders.status')
                        ->paginate(10);
    }

    $max_page = round($transactions->total() / 10);
    $current_page =$transactions->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('transactions'=>$transactions, 'current_page'=>$current_page,
                   'max_page'=>$max_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_transaction(Request $request ){

    $transaction = DB::table('transaction_orders')
                       ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                  ,'transaction_orders.total_cost', 'transaction_orders.status'
                                  , 'transaction_orders.latitude', 'transaction_orders.longtitude'
                                  , 'transaction_orders.full_adress', 'transaction_orders.longtitude'
                                  , 'transaction_orders.latitude'  , 'transaction_orders.note'
                                  ,'upjas.id as upja_id', 'upjas.name as upja_name'
                                  ,'farmers.id as farmer_id','farmers.name as farmer_name'
                                  ,'farmers.phone_number'
                                  ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                  ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('upjas', 'upjas.id', '=', 'transaction_orders.upja_id')
                      ->Join ('farmers', 'farmers.id', '=', 'transaction_orders.farmer_id')
                      ->Where('transaction_orders.id',  $request->transaction_order_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.status'
                               , 'transaction_orders.delivery_time'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name'
                               , 'transaction_orders.latitude', 'transaction_orders.longtitude'
                               , 'transaction_orders.full_adress','farmers.id'
                               ,'farmers.name','farmers.phone_number' , 'transaction_orders.note')
                      ->first();

    if($transaction == null){
      $final = array('message'=> 'transaction tidak ditemukan');
      return array('status' => 0 ,'result'=>$final);
    }

    $alsins = Transaction_order_type::select('transaction_order_types.*','alsin_types.name as alsin_name')
                                      ->where('transaction_order_types.transaction_order_id',
                                            $request->transaction_order_id)
                                      ->Join ('alsin_types', 'alsin_types.id', '=', 'transaction_order_types.alsin_type_id')
                                      ->with('transaction_childs')
                                      ->get();

    $rice = transaction_order_rice::where('transaction_order_rices.transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $rice_seed = transaction_order_rice_seed::select('transaction_order_rice_seeds.*'
                                              , 'rice_seeds.name')
                                      ->Join ('rice_seeds', 'rice_seeds.id', '=',
                                              'transaction_order_rice_seeds.rice_seed_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $rmu = transaction_order_rmu::where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $transaction_order_reparation = transaction_order_reparation::
                                        select('transaction_order_reparations.*'
                                              , 'alsin_types.name')
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_reparations.alsin_type_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $transaction_order_training = transaction_order_training::
                                      select('transaction_order_trainings.*'
                                            , 'trainings.name')
                                    ->Join ('trainings', 'trainings.id', '=',
                                            'transaction_order_trainings.training_id')
                                    ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $transaction_order_spare_part = transaction_order_spare_part::
                                        select('transaction_order_spare_parts.*'
                                              , 'spare_parts.name')
                                      ->Join ('spare_parts', 'spare_parts.id', '=',
                                              'transaction_order_spare_parts.spare_part_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $other_service = array('rices'=>$rice , 'rice_seeds'=>$rice_seed, 'rmus'=>$rmu,
                    'reparations'=>$transaction_order_reparation,
                    'trainings'=>$transaction_order_training,
                    'spare_parts'=>$transaction_order_spare_part  );

    $final = array('transaction'=>$transaction, 'alsins'=>$alsins,
                   'other_service'=>$other_service);

    return array('status' => 1 ,'result'=>$final);
  }

  public function update_status_transaction(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $transaction = Transaction_order::find($request->transaction_order_id);
    if($transaction == null){
      $final = array('message'=> 'transaction not found');
      return array('status' => 0 ,'result'=>$final);
    }

    $tokenList = DB::table('transaction_notif_tokens')
                  ->where('transaction_notif_tokens.farmer_id', $transaction->farmer_id)
                  ->pluck('transaction_notif_tokens.token')
                   ->all();

    if($request->status == 'Menunggu Konfirmasi Petani'){

      $transaction->transport_cost = $request->transport_cost;
      $total_cost = 0;


      for($j =0; $j< sizeof($request['alsin_items']) ; $j++ ){

        $alsin_item  = new Transaction_order_child;
        $alsin_item->alsin_item_id = $request['alsin_items'][$j]['alsin_item_id'];
        $alsin_item->transaction_order_type_id = $request['alsin_items'][$j]['transaction_order_type_id'];
        $alsin_item->save();

        $alsin_item_fix  = Alsin_item::find($request['alsin_items'][$j]['alsin_item_id']);
        $alsin_item_fix->status = "Sedang Digunakan";
        $alsin_item_fix->save();

        $transaction_type = Transaction_order_type::find($request['alsin_items'][$j]['transaction_order_type_id']);
        // $transaction_type->cost = $transaction_type->cost + $request['alsin_items'][$j]['cost'];
        $transaction_type->cost += $request['alsin_items'][$j]['cost'];
        $transaction_type->save();

        $total_cost += ($request['alsin_items'][$j]['cost']);
        // $total_cost += 5000;
      }

      for($i = 0 ; $i < sizeof ($request['rmus'])  ; $i ++){

          $transaction_order_rmu = transaction_order_rmu::find($request['rmus'][$i]['id']);  ;
          $transaction_order_rmu->cost = $request['rmus'][$i]['cost'];
          $transaction_order_rmu->save();

          $total_cost +=  $request['rmus'][$i]['cost'] ;
      }

      for($i = 0 ; $i < sizeof ($request['rice'])  ; $i ++){

          $transaction_order_rice = transaction_order_rice::find($request['rice'][$i]['id']);
          $transaction_order_rice->cost = $request['rice'][$i]['cost'];
          $transaction_order_rice->total_rice = $request['rice'][$i]['total_rice'];
          $transaction_order_rice->save();

          $total_cost +=  $request['rice'][$i]['cost'] ;
      }

      for($i = 0 ; $i < sizeof ($request['training'])  ; $i ++){

          $transaction_order_training = transaction_order_training::find($request['training'][$i]['id']);
          $transaction_order_training->cost = $request['training'][$i]['cost'];
          $transaction_order_training->save();

          $total_cost +=  $request['training'][$i]['cost'] ;
      }

      for($i = 0 ; $i < sizeof ($request['reparation'])  ; $i ++){

          $transaction_order_reparation = transaction_order_reparation::find($request['reparation'][$i]['id']);
          $transaction_order_reparation->cost = $request['reparation'][$i]['cost'];
          $transaction_order_reparation->save();

          $total_cost +=  $request['reparation'][$i]['cost'] ;
      }

      for($i = 0 ; $i < sizeof ($request['rice_seed'])  ; $i ++){

          $transaction_order_rice_seed = transaction_order_rice_seed::find($request['rice_seed'][$i]['id']);
          $transaction_order_rice_seed->cost = $request['rice_seed'][$i]['cost'];
          $transaction_order_rice_seed->save();

          $total_cost +=  $request['rice_seed'][$i]['cost'] ;
      }

      for($i = 0 ; $i < sizeof ($request['spare_part'])  ; $i ++){

          $transaction_order_spare_part = transaction_order_spare_part::find($request['spare_part'][$i]['id']);
          $transaction_order_spare_part->cost = $request['spare_part'][$i]['cost'];
          $transaction_order_spare_part->save();

          $total_cost +=  $request['spare_part'][$i]['cost'] ;
      }
      $transaction->total_cost = $total_cost + $request->transport_cost;

      app('App\Http\Controllers\General_Controller')->
      PostNotifMultiple('Alsintanlink',$tokenList, 'Pesanan anda telah ditentukan harga oleh Upja. silahkan cek transaksi.'
                        ,1, $tokenList );

    }else if($request->status == 'Menunggu Konfirmasi Upja'){
      app('App\Http\Controllers\General_Controller')->
      PostNotifMultiple('Alsintanlink',$tokenList, 'Pesanan anda telah diterima Upja'
                        ,1, $tokenList );
    }else if($request->status == 'Menunggu Alsin dikirim'){
      app('App\Http\Controllers\General_Controller')->
      PostNotifMultiple('Alsintanlink',$tokenList, 'Pesanan anda telah dikirim Upja'
                        ,1, $tokenList );
    }else if($request->status == 'Sedang dikerjakan'){
      app('App\Http\Controllers\General_Controller')->
      PostNotifMultiple('Alsintanlink',$tokenList, 'Pesanan anda sedang dikerjakan Upja'
                        ,1, $tokenList );
    }else if($request->status === 'Selesai'){

      $alsin_item  = Alsin_item::select('alsin_items.id','alsin_items.status')
                              ->Join ('transaction_order_children',
                                      'transaction_order_children.alsin_item_id', '=',
                                      'alsin_items.id')
                              ->Join ('transaction_order_types',
                                      'transaction_order_types.id', '=',
                                      'transaction_order_children.transaction_order_type_id')
                              ->where('transaction_order_types.transaction_order_id' ,
                                        $request->transaction_order_id )
                              ->get();

      for($i = 0 ; $i < sizeof ($alsin_item)  ; $i ++){

        $alsin_item[$i]->status = "Tersedia";
        $alsin_item[$i]->save();
      }

      app('App\Http\Controllers\General_Controller')->
      PostNotifMultiple('Alsintanlink',$tokenList, 'Pesanan anda telah selesai'
                        ,1, $tokenList );
    }else if($request->status == 'Transaksi ditolak Upja'){

      // $transaction->delete();
    }
    // else{
    //   $final = array('message'=> 'request not found');
    //   return array('status' => 0 ,'result'=>$final);
    // }

    $transaction->status = $request->status;
    $transaction->save();

    $final = array('message'=> 'update succsess');
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_all_alsin_type()
  {
    $alsins = DB::table('alsin_types')
                      ->select('alsin_types.*')
                      ->Where('alsin_types.alsin_other',  0 )
                      ->get();

    $final = array( 'alsins'=>$alsins);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_form_pricing(Request $request)
  {
    $header = DB::table('transaction_orders')
                       ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                  ,'transaction_orders.total_cost', 'transaction_orders.status'
                                  , 'transaction_orders.latitude', 'transaction_orders.longtitude'
                                  , 'transaction_orders.full_adress', 'transaction_orders.longtitude'
                                  , 'transaction_orders.latitude'
                                  ,'upjas.id as upja_id', 'upjas.name as upja_name'
                                  ,'farmers.id as farmer_id','farmers.name as farmer_name'
                                  ,'farmers.phone_number'
                                  ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                  ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('upjas', 'upjas.id', '=', 'transaction_orders.upja_id')
                      ->Join ('farmers', 'farmers.id', '=', 'transaction_orders.farmer_id')
                      ->Where('transaction_orders.id',  $request->transaction_order_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.status'
                               , 'transaction_orders.delivery_time'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name'
                               , 'transaction_orders.latitude', 'transaction_orders.longtitude'
                               , 'transaction_orders.full_adress','farmers.id'
                               ,'farmers.name','farmers.phone_number')
                      ->first();

    $alsin_type = DB::table('transaction_order_types')
                      ->select('alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name',
                               'transaction_order_types.id as transaction_order_type_id'
                               ,'transaction_order_types.land_area_range','transaction_order_types.cost')
                      ->Join ('alsin_types', 'alsin_types.id', '=',
                              'transaction_order_types.alsin_type_id')
                      ->Where('transaction_order_types.transaction_order_id',
                              $request->transaction_order_id )
                      ->get();

    $alsins = DB::table('alsin_items')
                      ->select('alsin_items.id as alsin_item_id',
                               'alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name',
                               'alsin_items.vechile_code','alsin_items.description'
                               ,DB::raw("(SELECT transaction_order_types.id FROM transaction_order_types
                                   WHERE transaction_order_types.transaction_order_id = $request->transaction_order_id
                                   AND transaction_order_types.alsin_type_id = alsin_types.id
                                      ) as transaction_order_type_id
                                  ")
                               )
                      ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                      ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                      ->whereIn('alsin_types.id',
                                DB::table('transaction_order_types')
                                ->select('alsin_types.id')
                                ->Join ('alsin_types', 'alsin_types.id', '=',
                                        'transaction_order_types.alsin_type_id')
                                ->Where('transaction_order_types.transaction_order_id',
                                        $request->transaction_order_id )
                         )
                      ->where('alsin_types.name', 'like', '%' . $request->keyword_alsin_item . '%')
                      ->Where('alsin_items.status','Tersedia')
                      ->Paginate(10);

    $alsins->setPath(env('APP_URL') . '/api/upja/show_form_pricing?transaction_order_id=' .
                     $request->transaction_order_id .'&keyword_alsin_item=' . $request->keyword_alsin_item  );

    $alsin_item_selected = DB::table('transaction_order_children')
                     ->select('alsin_items.id as alsin_item_id', 'alsin_items.vechile_code',
                              'alsin_items.description')
                     ->Join ('transaction_order_types', 'transaction_order_types.id', '=',
                              'transaction_order_children.transaction_order_type_id')
                     ->Join ('alsin_items', 'alsin_items.id', '=',
                             'transaction_order_children.alsin_item_id')
                     ->Where('transaction_order_types.transaction_order_id',
                             $request->transaction_order_id )
                     ->paginate(10);

    $rice = transaction_order_rice::select('transaction_order_rices.*'
                                          , 'alsin_types.name as alsin_type_name')
                                      ->where('transaction_order_rices.transaction_order_id',
                                            $request->transaction_order_id)
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_rices.alsin_type_id')
                                      ->get();

    $rice_seed = transaction_order_rice_seed::select('transaction_order_rice_seeds.*'
                                              , 'rice_seeds.name', 'alsin_types.name as alsin_type_name')
                                      ->Join ('rice_seeds', 'rice_seeds.id', '=',
                                              'transaction_order_rice_seeds.rice_seed_id')
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_rice_seeds.alsin_type_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $rmu = transaction_order_rmu::select('transaction_order_rmus.*'
                                          , 'alsin_types.name as alsin_type_name')
                                     ->Join ('alsin_types', 'alsin_types.id', '=',
                                            'transaction_order_rmus.alsin_type_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $reparation = transaction_order_reparation::
                                        select('transaction_order_reparations.*'
                                                , 'order.id as alsin_type_order_id'
                                                , 'source.id as alsin_type_id'
                                              , 'order.name as alsin_type_order_name'
                                              , 'source.name as alsin_type_name')
                                      ->Join ('alsin_types as order', 'order.id', '=',
                                              'transaction_order_reparations.alsin_type_id')
                                      ->Join ('alsin_types as source', 'source.id', '=',
                                            'transaction_order_reparations.alsin_type_order_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $training = transaction_order_training::
                                      select('transaction_order_trainings.*'
                                            , 'trainings.name', 'alsin_types.name as alsin_type_name')
                                    ->Join ('trainings', 'trainings.id', '=',
                                            'transaction_order_trainings.training_id')
                                    ->Join ('alsin_types', 'alsin_types.id', '=',
                                            'transaction_order_trainings.alsin_type_id')
                                    ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $spare_part = transaction_order_spare_part::
                                        select('transaction_order_spare_parts.*'
                                              , 'spare_parts.name', 'alsin_types.name as alsin_type_name')
                                      ->Join ('spare_parts', 'spare_parts.id', '=',
                                              'transaction_order_spare_parts.spare_part_id')
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_spare_parts.alsin_type_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $final = array('header'=>$header,'alsin_types'=>$alsin_type,'alsins'=>$alsins
                    , 'alsin_item_selected'=>$alsin_item_selected  ,'rices'=>$rice
                    , 'rice_seeds'=>$rice_seed, 'rmus'=>$rmu, 'reparations'=>$reparation
                    , 'trainings'=>$training, 'spare_parts'=>$spare_part
  );
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_alsin_item_available(Request $request)
  {
    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $alsins = DB::table('alsin_items')
                      ->select('alsin_items.id as alsin_item_id','alsin_items.vechile_code',
                               'alsin_items.description')
                      ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                      ->Where('alsins.upja_id', $user_id )
                      ->Where('alsins.alsin_type_id', $request->alsin_type_id )
                      ->Where('alsin_items.status','Tersedia' )
                      ->get();

    $final = array('alsins'=>$alsins);
    return array('status' => 1 ,'result'=>$final);
  }

  public function change_password(Request $request)
  {

    $alsins = upja::find($request->upja_id);
    if($alsins == null){
      $final = array('message'=> "upja not found");
      return array('status' => 0 ,'result'=>$final);
    }
    $alsins->password = Hash::make($request->password);
    $alsins->save();

    $final = array('message'=> "change password succsess");
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_spare_part_upja(Request $request)
  {
    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $spare_parts = DB::table('trasansaction_upja_spare_parts')
                      ->select('trasansaction_upja_spare_parts.id as trasansaction_upja_spare_part_id',
                               'spare_parts.id as spare_part_id','spare_parts.name as spare_part_name'
                               ,'spare_part_types.name as spare_part_type_name','alsin_types.name as alsin_type_name'
                               )
                      ->Join ('spare_parts', 'spare_parts.id', '=', 'trasansaction_upja_spare_parts.spare_part_id')
                      ->Join ('spare_part_types', 'spare_part_types.id', '=', 'spare_parts.spare_part_type_id')
                      ->Join ('alsin_types', 'alsin_types.id', '=', 'spare_part_types.alsin_type_id')
                      ->Where('trasansaction_upja_spare_parts.upja_id', $user_id )
                      ->paginate(10);

    $max_page = round($spare_parts->total() / 10);
    $current_page =$spare_parts->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('spare_parts'=> $spare_parts,'max_page'=> $max_page,
                   'current_page'=> $current_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function insert_spare_part_upja(Request $request)
  {
    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $check_spare_part = Helper::check_spare_part($request->spare_part_id);
    if($check_spare_part == null){
      $final = array('message'=> "spare part not found");
      return array('status' => 0 ,'result'=>$final);
    }
    $spare_part_check = trasansaction_upja_spare_part::
                                where('spare_part_id',
                                      $request->spare_part_id)
                              ->where('upja_id',$user_id)->first();

    if($spare_part_check != null){
      $final = array('message'=> "spare part upja telah dimasukan");
      return array('status' => 0 ,'result'=>$final);
    }
    $spare_parts = new trasansaction_upja_spare_part;
    $spare_parts->spare_part_id = $request->spare_part_id;
    $spare_parts->upja_id = $user_id;
    $spare_parts->save();

    $final = array('message'=> "sukses insert spare part upja");
    return array('status' => 1 ,'result'=>$final);
  }

  public function delete_spare_part_upja(Request $request)
  {
    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $spare_parts = trasansaction_upja_spare_part::find($request->trasansaction_upja_spare_part_id);

    if($spare_parts == null){
      $final = array('message'=> "transaksi tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }
    if($spare_parts->upja_id != $user_id){
      $final = array('message'=> "upja anda tidak bisa menghapus tranksi ini");
      return array('status' => 0 ,'result'=>$final);
    }

    $spare_parts->delete();

    $final = array('message'=> "sukses delete spare part upja");
    return array('status' => 1 ,'result'=>$final);
  }
}
