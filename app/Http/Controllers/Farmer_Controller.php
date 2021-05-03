<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use Carbon\Carbon;
use App\Models\Upja;
use App\Models\Alsin;
use App\Models\Farmer;
use App\Mail\Upja_notif;
use App\Models\Alsin_item;
use App\Models\Alsin_type;
use Illuminate\Http\Request;
use App\Models\Transaction_order;
use Illuminate\Support\Facades\DB;
use App\Models\token_forget_farmer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Transaction_order_type;
use App\Helpers\LogActivity as Helper;
use App\Models\transaction_notif_token;
use App\Models\Transaction_order_child;
use Tymon\JWTAuth\Exceptions\JWTException;

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

use App\Models\spare_part;
use App\Models\spare_part_type;

class Farmer_Controller extends Controller
{

  function __construct()
  {
      Config::set('auth.defaults.guard', 'farmer');
      Config::set('jwt.farmer' , \App\Models\Farmer::class);
      Config::set('auth.providers.farmer.model', \App\Models\Farmer::class);
  }

  public function login(Request $request ){

    $user = Farmer::select('id','phone_verify','phone_number','name','password')
                    ->where('phone_number', $request->phone_number )
                    ->first();

    if($user!= null){
      if (Hash::check($request->password,$user->password )) {

          if($user->phone_verify == 1){

                \Config::set('jwt.farmer', 'App\Models\Farmer');
                \Config::set('auth.providers.farmer.model', \App\Models\Farmer::class);

                $credentials = $request->only('phone_number', 'password');
                $token = null;

                try {
                    if (! $token = auth('farmer')->attempt($credentials)) {

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
              $device_id = transaction_notif_token::where('farmer_id' , $user->id)
                                                  ->orderby('device_id','desc')
                                                  ->first();
              if($device_id != null){
                $devide_add = $device_id->device_id + 1;
              }

              $notif = new transaction_notif_token;
              $notif->farmer_id = $user->id ;
              $notif->device_id = $devide_add ;
              $notif->token = $request->token_notif ;
              $notif->save();

             $fixed_user = Farmer::select('id','phone_verify','phone_number')->find($user->id);
             $final = array('message' => 'login berhasil','token' => $token ,'farmer' => $fixed_user,
                            'device_id' => $notif->device_id);

             return array('status' => 1, 'result' => $final);
          }else{

            $digits = 4;
            $user->otp_code = rand(pow(10, $digits-1), pow(10, $digits)-1);
            $user->save();

            $userkey = env("zenziva_userkey");
            $passkey = env("zenziva_passkey");
            $telepon = $request->phone_number;
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

            $final = array('message'=>"gagal karena login belum verif", 'otp_code' => $user->otp_code);
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
          'name' => 'required',
          'phone_number' => 'required|unique:farmers',
          'password' => 'required',
      ];
  }

  public function register(Request $request){

    $validator = \Validator::make($request->all(), $this->rules());
    $digits = 4; // Amount of digits

    $user = Farmer::where('phone_number', $request->phone_number )
                    ->first();
    if($user != null){
      if($user->phone_verify == 0){

        $final = array('message'=> 'sudah terdaftar dan belum melakukan otp');
        return array('status' => 2,'result' => $final) ;
      }else{
        $final = array('message'=> 'sudah terdaftar dan terverifikasi');
        return array('status' => 3,'result' => $final) ;
      }
    }
    if ($validator->fails()) {

      $final = array('message'=>$validator->errors()->first());
      return array('status' => 0,'result' => $final) ;
    }

    $blog =  new Farmer;
    $blog->name = $request->name;
    $blog->phone_number = $request->phone_number;
    $blog->province = $request->province;
    $blog->city = $request->city;
    $blog->district = $request->district;
    $blog->village = $request->village;
    $blog->password = Hash::make($request->password);
    $blog->otp_code = rand(pow(10, $digits-1), pow(10, $digits)-1);
    $blog->save();

    $userkey = env("zenziva_userkey");
    $passkey = env("zenziva_passkey");
    $telepon = $request->phone_number;
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

    $final = array('message'=>'register berhasil', 'otp_code'=>$blog->otp_code,'farmer'=>$blog);
    return array('status' => 1,'result'=>$final);
  }

  public function submit_otp(Request $request){

    $user = Farmer::where('phone_number', $request->phone_number )
                    ->first();

    if($user == null){
      $final = array('message'=> "farmer tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }
    $user->phone_verify = 1;
    $user->save();

    $final = array('message'=>'submit otp berhasil');
    return array('status' => 1,'result'=>$final);
  }

  public function reset_otp(Request $request){

    $user = Farmer::where('phone_number', $request->phone_number )
                    ->first();

    if($user == null){
      $final = array('message'=> "farmer tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }
    $user->otp_code = null;
    $user->save();

    $final = array('message'=>'reset otp berhasil');
    return array('status' => 1,'result'=>$final);
  }

  public function resend_otp(Request $request){

    $user = Farmer::where('phone_number', $request->phone_number )
                    ->first();
    // dd(env("zenziva_userkey"));             ;
    if($user == null){
      $final = array('message'=> "farmer tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }
    $digits = 4; // Amount of digits
    $user->otp_code = rand(pow(10, $digits-1), pow(10, $digits)-1);
    $user->save();

    $userkey = env("zenziva_userkey");
    $passkey = env("zenziva_passkey");
    $telepon = $request->phone_number;
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

    $final = array('message'=>'resend otp berhasil','otp_code'=>$user->otp_code);
    return array('status' => 1,'result'=>$final);
  }

  public function show_upja(Request $request ){

    $check_district = Helper::check_village($request->village_id);

    if($check_district == null){
      $final = array('message'=> "kecamatan tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }

    if($request->village_id != null){
      $upja = Upja::select('id','name','leader_name','class')
              ->where('village', $request->village_id )
              ->get();
    }else  if($request->village_id == null && $request->district_id != null){
      $upja = Upja::select('id','name','leader_name','class')
              ->where('district', $request->district_id )
              ->get();
    }else  if($request->district_id == null && $request->city_id != null){
      $upja = Upja::select('id','name','leader_name','class')
              ->where('city', $request->city_id )
              ->get();
    }else  if($request->city_id == null && $request->province_id != null){
      $upja = Upja::select('id','name','leader_name','class')
              ->where('province', $request->province_id )
              ->get();
    }


    $final = array('upjas'=>$upja);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_upja_v2(Request $request ){

    $upja = null;

    if($request->village_id != null){
      $upja = Upja::select('id','name','leader_name','class')
              ->where('village', $request->village_id )
              ->get();
    }else  if($request->village_id == null && $request->district_id != null){
      $upja = Upja::select('id','name','leader_name','class')
              ->where('district', $request->district_id )
              ->get();
    }else  if($request->district_id == null && $request->city_id != null){
      $upja = Upja::select('id','name','leader_name','class')
              ->where('city', $request->city_id )
              ->get();
    }else  if($request->city_id == null && $request->province_id != null){
      $upja = Upja::select('id','name','leader_name','class')
              ->where('province', $request->province_id )
              ->get();
    }

    $final = array('upjas'=>$upja);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_upja(Request $request ){

    $check_upja = Helper::check_upja($request->upja_id);
    if($check_upja == null){
      return array('status' => 0 ,'result'=>null);
    }

    $upja = Upja::select('id','name','leader_name','class')
                    ->Where('upjas.id', $request->upja_id )
                    ->first();

    $alsintan = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id as alsin_type_id','alsin_types.alsin_other'
                                ,'alsin_types.name','alsin_types.picture as alsin_type_picture'
                                ,'alsins.cost','alsin_types.picture_detail as alsins_picture'
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
                                   )
                      ->Where('upjas.id', $request->upja_id )
                      ->Where('alsin_types.alsin_other' , 0 )
                      ->groupBy('alsin_id','alsin_types.id','alsin_types.name','alsin_types.picture'
                            ,'alsin_types.alsin_other','alsins.cost','alsin_types.picture_detail')
                      ->get();

    // $rice = transaction_order_rice::select('alsin_types.id as alsin_type_id',
    //                                        'alsin_types.alsin_other','alsin_types.name as name',
    //                                        'alsin_types.picture as alsin_type_picture',
    //                                        'alsin_types.picture_detail as alsins_picture'
    //                                              )
    //                                   ->Join ('alsin_types', 'alsin_types.id', '=',
    //                                           'transaction_order_rices.alsin_type_id')
    //                                   ->Join ('transaction_orders', 'transaction_orders.id', '=',
    //                                           'transaction_order_rices.transaction_order_id')
    //                                   ->where('transaction_orders.upja_id',
    //                                         $request->upja_id)
    //                                   ->limit(1)
    //                                   ->get();

    $other_service = DB::table('alsins')
                ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                ->select('alsin_types.id as alsin_type_id',
                          'alsin_types.alsin_other','alsin_types.name as name',
                          'alsin_types.picture as alsin_type_picture',
                          'alsin_types.picture_detail as alsins_picture' )
              ->Where('upjas.id', $request->upja_id )
              // ->Where('alsin_types.id' , 10 )
              ->Where('alsin_types.alsin_other' , 1 )
              ->groupBy('alsin_types.id')
              // ->limit(1)
              ->get();

    // $rice = DB::table('alsins')
    //             ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
    //             ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
    //             ->select('alsin_types.id as alsin_type_id',
    //                       'alsin_types.alsin_other','alsin_types.name as name',
    //                       'alsin_types.picture as alsin_type_picture',
    //                       'alsin_types.picture_detail as alsins_picture' )
    //           ->Where('upjas.id', $request->upja_id )
    //           // ->Where('alsin_types.id' , 10 )
    //           ->Where('alsin_types.alsin_other' , 1 )
    //           ->groupBy('alsin_types.id')
    //           // ->limit(1)
    //           ->get();

    // $rice_seed = transaction_order_rice_seed::select('alsin_types.id as alsin_type_id',
    //                                        'alsin_types.alsin_other','alsin_types.name as name',
    //                                        'alsin_types.picture as alsin_type_picture',
    //                                        'alsin_types.picture_detail as alsins_picture'
    //                                              )
    //                                   ->Join ('alsin_types', 'alsin_types.id', '=',
    //                                           'transaction_order_rice_seeds.alsin_type_id')
    //                                   ->Join ('transaction_orders', 'transaction_orders.id', '=',
    //                                           'transaction_order_rice_seeds.transaction_order_id')
    //                                   ->where('transaction_orders.upja_id',
    //                                         $request->upja_id)
    //                                   ->limit(1)
    //                                   ->get();


    // $rmu = transaction_order_rmu::select('alsin_types.id as alsin_type_id',
    //                                        'alsin_types.alsin_other','alsin_types.name as name',
    //                                        'alsin_types.picture as alsin_type_picture',
    //                                        'alsin_types.picture_detail as alsins_picture'
    //                                              )
    //                                  ->Join ('alsin_types', 'alsin_types.id', '=',
    //                                         'transaction_order_rmus.alsin_type_id')
    //                                   ->Join ('transaction_orders', 'transaction_orders.id', '=',
    //                                           'transaction_order_rmus.transaction_order_id')
    //                                   ->where('transaction_orders.upja_id',
    //                                         $request->upja_id)
    //                                 //  -?groupBy('')
    //                                  ->get();



    // $reparation = transaction_order_reparation::
    //                                 select('alsin_types.id as alsin_type_id',
    //                                        'alsin_types.alsin_other','alsin_types.name as name',
    //                                        'alsin_types.picture as alsin_type_picture',
    //                                        'alsin_types.picture_detail as alsins_picture'
    //                                              )
    //                                   ->Join ('alsin_types', 'alsin_types.id', '=',
    //                                           'transaction_order_reparations.alsin_type_order_id')
    //                                   ->Join ('transaction_orders', 'transaction_orders.id', '=',
    //                                           'transaction_order_reparations.transaction_order_id')
    //                                   ->where('transaction_orders.upja_id',
    //                                         $request->upja_id)
    //                                   ->limit(1)
    //                                   ->get();

    // $training = transaction_order_training::
    //                                 select('alsin_types.id as alsin_type_id',
    //                                        'alsin_types.alsin_other','alsin_types.name as name',
    //                                        'alsin_types.picture as alsin_type_picture',
    //                                        'alsin_types.picture_detail as alsins_picture'
    //                                              )
    //                                 ->Join ('alsin_types', 'alsin_types.id', '=',
    //                                         'transaction_order_trainings.alsin_type_id')
    //                                 ->Join ('transaction_orders', 'transaction_orders.id', '=',
    //                                         'transaction_order_trainings.transaction_order_id')
    //                                 ->where('transaction_orders.upja_id',
    //                                       $request->upja_id)
    //                                 ->limit(1)
    //                                 ->get();


    // $spare_part = transaction_order_spare_part::
    //                                   select('alsin_types.id as alsin_type_id',
    //                                          'alsin_types.alsin_other','alsin_types.name as name',
    //                                          'alsin_types.picture as alsin_type_picture',
    //                                          'alsin_types.picture_detail as alsins_picture'
    //                                                )
    //                                   ->Join ('alsin_types', 'alsin_types.id', '=',
    //                                           'transaction_order_spare_parts.alsin_type_id')
    //                                   ->Join ('transaction_orders', 'transaction_orders.id', '=',
    //                                           'transaction_order_spare_parts.transaction_order_id')
    //                                   ->where('transaction_orders.upja_id',
    //                                         $request->upja_id)
    //                                   ->limit(1)
    //                                   ->get();
    // $vehicles = collect();
    // $other_service = $vehicles->merge($rice)->merge($rice_seed)
    //               ->merge($rmu)
    //               ->merge($reparation)->merge($training)->merge($spare_part);

    $final = array('upja' => $upja ,'alsintans'=>$alsintan,'other_services'=>$other_service);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_all_alsin(Request $request ){

    $alsintan = DB::table('alsin_types')
                       ->select('alsin_types.id as alsin_type_id','alsin_types.alsin_other'
                                ,'alsin_types.name','alsin_types.picture as alsin_type_picture'
                                ,'alsin_types.picture_detail as alsins_picture')
                       ->orderBy('alsin_types.alsin_other')
                       ->orderBy('alsin_types.id')
                       ->get();
    $final = array('alsintans'=>$alsintan);
    return array('status' => 1 ,'result'=>$final);
  }

  public function order_alsin(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $prefix = "INV-";
    // create order
    $transaction_order = new Transaction_order ;
    $transaction_order->farmer_id = $user_id;
    $transaction_order->note = $request->note;
    $transaction_order->upja_id = $request->upja_id;
    $transaction_order->delivery_time = $request->delivery_time;
    $transaction_order->latitude = $request->latitude;
    $transaction_order->longtitude = $request->longitude;
    $transaction_order->full_adress = $request->full_address;
    $transaction_order->invoice = uniqid($prefix);
    $transaction_order->save();

    if($request->alsins != null){
      for($i = 0 ; $i < sizeof ($request->alsins)  ; $i ++){

          // create detail alsin type request
          $transaction_order_type = new Transaction_order_type ;
          $transaction_order_type->transaction_order_id = $transaction_order->id;
          $transaction_order_type->alsin_type_id	 = $request->alsins[$i]['alsin_type_id'];
          $transaction_order_type->land_area_range	 = $request->alsins[$i]['land_area_range'];
          $transaction_order_type->save();
      }
    }

    if($request->rmus != null){
      for($j = 0 ; $j < sizeof ($request->rmus)  ; $j ++){

          $transaction_order_rmu = new transaction_order_rmu ;
          $transaction_order_rmu->transaction_order_id = $transaction_order->id;
          $transaction_order_rmu->weight	 = $request->rmus[$j]['weight'];
          $transaction_order_rmu->packaging	 = $request->rmus[$j]['packaging'];
          $transaction_order_rmu->save();
      }
    }

    if($request->rices != null){
      for($i = 0 ; $i < sizeof ($request->rices)  ; $i ++){

          $transaction_order_rice = new transaction_order_rice ;
          $transaction_order_rice->transaction_order_id = $transaction_order->id;
          $transaction_order_rice->land_area_range	 = $request->rices[$i]['land_area_range'];
          $transaction_order_rice->save();
      }
    }

    if($request->trainings != null){
      for($i = 0 ; $i < sizeof ($request->trainings)  ; $i ++){

          $transaction_order_training = new transaction_order_training ;
          $transaction_order_training->transaction_order_id = $transaction_order->id;
          $transaction_order_training->training_id	 = $request->trainings[$i]['training_id'];
          $transaction_order_training->total_member	 = $request->trainings[$i]['total_member'];
          $transaction_order_training->save();
      }
    }

    if($request->reparations != null){
      for($i = 0 ; $i < sizeof ($request->reparations)  ; $i ++){

          $transaction_order_reparation = new transaction_order_reparation ;
          $transaction_order_reparation->transaction_order_id = $transaction_order->id;
          $transaction_order_reparation->alsin_type_id	 = $request->reparations[$i]['reparation_id'];
          $transaction_order_reparation->save();
      }
    }

    if($request->rice_seeds != null){
      for($i = 0 ; $i < sizeof ($request->rice_seeds)  ; $i ++){

          $transaction_order_rice_seed = new transaction_order_rice_seed ;
          $transaction_order_rice_seed->transaction_order_id = $transaction_order->id;
          $transaction_order_rice_seed->rice_seed_id	 = $request->rice_seeds[$i]['rice_seed_id'];
          $transaction_order_rice_seed->weight	 = $request->rice_seeds[$i]['weight'];
          $transaction_order_rice_seed->save();
      }
    }

    if($request->spare_parts != null){
      for($i = 0 ; $i < sizeof ($request->spare_parts)  ; $i ++){

          $transaction_order_spare_part = new transaction_order_spare_part ;
          $transaction_order_spare_part->transaction_order_id = $transaction_order->id;
          $transaction_order_spare_part->alsin_type_id	 = $request->spare_parts[$i]['alsin_type_id'];
          $transaction_order_spare_part->merek	 = $request->spare_parts[$i]['merek'];
          $transaction_order_spare_part->type_model	 = $request->spare_parts[$i]['type_model'];
          $transaction_order_spare_part->nama	 = $request->spare_parts[$i]['nama'];
          $transaction_order_spare_part->jumlah	 = $request->spare_parts[$i]['jumlah'];
          $transaction_order_spare_part->save();
      }
    }

    // send notif to upja
    $upja = Upja::find($request->upja_id);
    $farmer = Farmer::find($user_id);

    if(filter_var($upja->email, FILTER_VALIDATE_EMAIL)){
        //send email
        Mail::to($upja->email)->send(new Upja_notif($farmer));
    }else  if(preg_match('/^[0-9]{3,15}+$/', $upja->email)){

      $userkey = env("zenziva_userkey");
      $passkey = env("zenziva_passkey");
      $telepon = $upja->email;
      $message = 'Ada pesanan dari petani: ' . $farmer->name . ' Silahkan cek website upja anda!';

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

    $final = array('message'=> 'Pesanan Berhasil'  );
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_transaction(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $transactions = DB::table('transaction_orders')
                       ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                , 'transaction_orders.total_cost', 'transaction_orders.status', 'transaction_orders.invoice'
                                , 'upjas.id as upja_id', 'upjas.name as upja_name'
                                ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('upjas', 'upjas.id', '=', 'transaction_orders.upja_id')
                      ->Where('transaction_orders.farmer_id',  $user_id )
                      ->orderBy('transaction_orders.id','desc')
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.status'
                               , 'transaction_orders.delivery_time','transaction_orders.invoice'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name')
                      ->paginate(5);

    $transaction_final = $transactions->toArray()['data'];
    $current_page = $transactions->toArray()['current_page'];
    $last_page = $transactions->toArray()['last_page'];
    $next_page_temp = explode('=' ,$transactions->toArray()['next_page_url'] ) ;
    if($next_page_temp[0] == ""){
      $next_page_url = null;
    }else{
      $next_page_url = $next_page_temp[1];
    }

    $final = array('transactions'=>$transaction_final, 'current_page'=>"" .$current_page,
                   'last_page'=>"" . $last_page, 'next_page'=>$next_page_url );
    return array('status' => 1 ,'result'=>$final);
  }


  public function show_detail_transaction(Request $request ){

    $transaction = DB::table('transaction_orders')
                       ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                  , 'transaction_orders.total_cost', 'transaction_orders.status', 'transaction_orders.invoice'
                                  , 'transaction_orders.latitude', 'transaction_orders.longtitude', 'transaction_orders.full_adress'
                                  , 'upjas.id as upja_id', 'upjas.name as upja_name', 'transaction_orders.note'
                                  ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                  ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('upjas', 'upjas.id', '=', 'transaction_orders.upja_id')
                      ->Where('transaction_orders.id',  $request->transaction_order_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.status'
                               , 'transaction_orders.delivery_time', 'transaction_orders.invoice'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name'
                               , 'transaction_orders.latitude', 'transaction_orders.longtitude'
                               , 'transaction_orders.full_adress',  'transaction_orders.note')
                      ->first();

    if($transaction == null){
      $final = array('message'=> 'transaction tidak ditemukan');
      return array('status' => 0 ,'result'=>$final);
    }

    $alsins = Transaction_order_type::select('transaction_order_types.id',
                                            'alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name'
                                          , 'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other'
                                          , 'transaction_order_types.land_area_range',  'transaction_order_types.cost')
                                      ->where('transaction_order_types.transaction_order_id',
                                            $request->transaction_order_id)
                                      ->Join ('alsin_types', 'alsin_types.id', '=', 'transaction_order_types.alsin_type_id')
                                      ->with('transaction_childs')
                                      ->get();

    $rice = transaction_order_rice::select('transaction_order_rices.*',
                                            'alsin_types.name as alsin_type_name',
                                            'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other')
                                      ->Join ('alsin_types', 'alsin_types.id', '=', 'transaction_order_rices.alsin_type_id')
                                      ->where('transaction_order_rices.transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $rice_seed = transaction_order_rice_seed::select('transaction_order_rice_seeds.*'
                                              , 'rice_seeds.name',
                                                'alsin_types.name as alsin_type_name',
                                                'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other')
                                      ->Join ('rice_seeds', 'rice_seeds.id', '=',
                                              'transaction_order_rice_seeds.rice_seed_id')
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_rice_seeds.alsin_type_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $rmu = transaction_order_rmu::select('transaction_order_rmus.*',
                                                'alsin_types.name as alsin_type_name',
                                                'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other')
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_rmus.alsin_type_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $transaction_order_reparation = transaction_order_reparation::
                                        select('transaction_order_reparations.id','transaction_order_reparations.transaction_order_id',
                                              'transaction_order_reparations.cost',
                                              'transaction_order_reparations.alsin_type_id as alsins_order_id',
                                              'transaction_order_reparations.alsin_type_order_id as alsin_type_id'
                                              , 'alsin_type_order.name as alsin_type_order_name'
                                              , 'alsin_type_order.picture_detail as alsins_order_picture'
                                              , 'alsin_type_source.name as alsin_type_name'
                                              , 'alsin_type_source.picture_detail as alsins_picture'
                                              , 'alsin_type_source.alsin_other')
                                      ->Join ('alsin_types as alsin_type_order', 'alsin_type_order.id', '=',
                                              'transaction_order_reparations.alsin_type_id')
                                      ->Join ('alsin_types as alsin_type_source', 'alsin_type_source.id', '=',
                                              'transaction_order_reparations.alsin_type_order_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $transaction_order_training = transaction_order_training::
                                      select('transaction_order_trainings.*'
                                            , 'trainings.name',
                                            'alsin_types.name as alsin_type_name',
                                            'alsin_types.picture_detail as alsins_picture'
                                            , 'alsin_types.alsin_other')
                                    ->Join ('trainings', 'trainings.id', '=',
                                            'transaction_order_trainings.training_id')
                                    ->Join ('alsin_types', 'alsin_types.id', '=',
                                            'transaction_order_trainings.alsin_type_id')
                                    ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->get();

    $transaction_order_spare_part = transaction_order_spare_part::
                                        select('transaction_order_spare_parts.*'
                                              , 'spare_parts.name',
                                              'alsin_types.name as alsin_name',
                                              'alsin_types.picture_detail as alsins_picture',
                                              DB::raw("(select 1) as alsin_other, (select 'Spare Part') as alsin_type_name"))
                                      ->Join ('spare_parts', 'spare_parts.id', '=',
                                              'transaction_order_spare_parts.spare_part_id')
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_spare_parts.alsin_type_id')
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


    public function accept_pricing(Request $request ){

      $transaction =  Transaction_order::find($request->transaction_order_id) ;
      if($transaction == null){
        $final = array('message'=>'transaction tidak ditemukan');
        return array('status' => 0 ,'result'=>$final);
      }
      $transaction->status = "Menungggu Konfirmasi Upja";
      $transaction->save();

      $final = array('message'=>'berhasil');
      return array('status' => 1 ,'result'=>$final);
    }

    public function decline_pricing(Request $request ){

      $transaction =  Transaction_order::find($request->transaction_order_id) ;
      if($transaction == null){
        $final = array('message'=>'transaction tidak ditemukan');
        return array('status' => 0 ,'result'=>$final);
      }

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

      $transaction->status = "Petani Menolak Harga";
      $transaction->save();

      $final = array('message'=>'berhasil');
      return array('status' => 1 ,'result'=>$final);
    }


    public function delete_order(Request $request ){

      $transaction =  Transaction_order::find($request->transaction_order_id) ;
      if($transaction == null){
        $final = array('message'=>'transaction tidak ditemukan');
        return array('status' => 0 ,'result'=>$final);
      }else if($transaction->status != "Menunggu Penentuan Pembayaran" ){
        $final = array('message'=>'transaksi tidak bisa dibatalkan karena sudah berjalan');
        return array('status' => 0 ,'result'=>$final);
      }

      Log::info(print_r($request->all(), true));

      $transaction->delete();

      $final = array('message'=>'berhasil');
      return array('status' => 1 ,'result'=>$final);
    }


    public function show_detail_profile(Request $request ){

      $token = JWTAuth::getToken();
      $fixedtoken = JWTAuth::setToken($token)->toUser();
      $user_id = $fixedtoken->id;

      $farmer =  farmer::select('farmers.name','farmers.phone_number','indoregion_provinces.name as province',
                                'indoregion_regencies.name as city','indoregion_districts.name as district',
                                'indoregion_villages.name as village','indoregion_provinces.id as province_id',
                                'indoregion_regencies.id as city_id','indoregion_districts.id as district_id',
                                'indoregion_villages.id as village_id')
                              ->Join ('indoregion_provinces', 'indoregion_provinces.id', '=', 'farmers.province')
                              ->Join ('indoregion_regencies', 'indoregion_regencies.id', '=', 'farmers.city')
                              ->Join ('indoregion_districts', 'indoregion_districts.id', '=', 'farmers.district')
                              ->Join ('indoregion_villages', 'indoregion_villages.id', '=', 'farmers.village')
                              ->where('farmers.id' , $user_id)
                              ->first() ;

      if($farmer == null){
        $final = array('message'=>'farmer tidak ditemukan');
        return array('status' => 0 ,'result'=>$final);
      }

      $final = array('farmer'=>$farmer);
      return array('status' => 1 ,'result'=>$final);
    }

    public function update_user(Request $request){

      $token = JWTAuth::getToken();
      $fixedtoken = JWTAuth::setToken($token)->toUser();
      $user_id = $fixedtoken->id;

      $notif = Farmer::find($user_id);

      if($notif == null){
        $final = array('message'=> 'farmer tidak ditemukan');
        return array('status' => 0 ,'result'=>$final);
      }

      $notif->name = $request->name;
      $notif->province = $request->province;
      $notif->city = $request->city;
      $notif->district = $request->district;
      $notif->village = $request->village;
      $notif->save();

      $final = array('message'=> 'update user berhasil');
      return array('status' => 1 ,'result'=>$final);
    }

    public function forget_password(Request $request){

      $upja = Farmer::where('phone_number', $request->phone_number)->first();
      if($upja == null){
        $final = array('message'=>'phone number tidak terdaftar');
        return array('status' => 0,'result'=>$final);
      }

      $invoice = uniqid($upja->id);
      while (token_forget_farmer::where('token', $invoice)->first() != null) {
          $invoice = uniqid($upja);
      }

      $token_forget = new token_forget_farmer;
      $token_forget->farmer_id = $upja->id;
      $token_forget->token = $invoice;
      $token_forget->expired_at = Carbon::now()->addHours(1);
      $token_forget->save();

      // $userkey = 'd27a72ddaf0b';
      // $passkey = '4ba83675fd17f25c721dbb6d';
      // $telepon = '085771559962';
      // $message = 'Anda meminta untuk melakukan reset password. silahkan klik link berikut ' .
      //             env('APP_URL') . '/general/farmer_forget_form/' . $token_forget->token;
      // $url = 'https://console.zenziva.net/wareguler/api/sendWA/';
      // $curlHandle = curl_init();
      // curl_setopt($curlHandle, CURLOPT_URL, $url);
      // curl_setopt($curlHandle, CURLOPT_HEADER, 0);
      // curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
      // curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 2);
      // curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);
      // curl_setopt($curlHandle, CURLOPT_TIMEOUT,30);
      // curl_setopt($curlHandle, CURLOPT_POST, 1);
      // curl_setopt($curlHandle, CURLOPT_POSTFIELDS, array(
      //     'userkey' => $userkey,
      //     'passkey' => $passkey,
      //     'to' => $telepon,
      //     'message' => $message
      // ));
      // $results = json_decode(curl_exec($curlHandle), true);
      // curl_close($curlHandle);
      // // send otp
      $userkey = 'd27a72ddaf0b';
      $passkey = '4ba83675fd17f25c721dbb6d';
      $telepon = $request->phone_number;
      $message = 'Anda meminta untuk melakukan reset password. silahkan klik link berikut ' .
                env('APP_URL') . '/general/farmer_forget_form/' . $token_forget->token;
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
     
      $final = array('message'=>'Forget Password berhasil');
      return array('status' => 1,'result'=>$final);
    }

    public function forget_change_password(Request $request)
    {

      $token_forget = token_forget_farmer::where('token' , $request->token)->first();
      if($token_forget == null){
        $final = array('message'=>'token tidak terdaftar');
        return array('status' => 0,'result'=>$final);
      }

      if($token_forget->is_done == 1  ){
        $final = array('message'=>'token sudah dipakai! Silahkan ulangi forget password');
        return array('status' => 0,'result'=>$final);
      }

      $mytime = Carbon::now();
      if($token_forget->expired_at < $mytime->toDateTimeString()  ){
        $final = array('message'=>'token expired! Silahkan ulangi forget password');
        return array('status' => 0,'result'=>$final);
      }

      $token_forget->is_done = 1;
      $token_forget->save();

      $alsins = Farmer::find($token_forget->farmer_id);
      $alsins->password = Hash::make($request->password);
      $alsins->save();

      $final = array('message'=> "change password berhasil");
      return array('status' => 1 ,'result'=>$final);
    }

    public function farmer_forget_password_succsess(){

      return view('email/upja_forget_password_succsess');
    }

    public function farmer_forget_form(Request $request){

      $farmer = Farmer::find($request->farmer_id);
      return view('email/farmer_forget_password_form',['token' => $request->token]);
    }

    public function show_upja_raparation(Request $request){

      $reparation = transaction_upja_reparation::select('alsin_types.id',
                                                        'alsin_types.name')
                                    ->where('upja_id', $request->upja_id)
                                    ->Join ('alsin_types', 'alsin_types.id', '=',
                                            'transaction_upja_reparations.alsin_type_id')
                                    ->get();

      $final = array('reparations'=> $reparation);
      return array('status' => 1 ,'result'=>$final);
    }

    public function show_upja_rice_seed(Request $request){

      $rice_seed = transaction_upja_rice_seed::select('rice_seeds.id',
                                                        'rice_seeds.name')
                                    ->where('upja_id', $request->upja_id)
                                    ->Join ('rice_seeds', 'rice_seeds.id', '=',
                                            'transaction_upja_rice_seeds.rice_seed_id')
                                    ->get();

      $final = array('rice_seeds'=> $rice_seed);
      return array('status' => 1 ,'result'=>$final);
    }

    public function show_upja_training(Request $request){

      $training = transaction_upja_training::select('trainings.id',
                                                        'trainings.name')
                                    ->where('upja_id', $request->upja_id)
                                    ->Join ('trainings', 'trainings.id', '=',
                                            'transaction_upja_trainings.training_id')
                                    ->get();

      $final = array('trainings'=> $training);
      return array('status' => 1 ,'result'=>$final);
    }

    public function show_upja_spare_part(Request $request){

      $spare_part = trasansaction_upja_spare_part::select('spare_parts.id',
                                                        'spare_parts.name')
                                    ->where('upja_id', $request->upja_id)
                                    ->Join ('spare_parts', 'spare_parts.id', '=',
                                            'trasansaction_upja_spare_parts.spare_part_id')
                                    ->get();

      $final = array('spare_parts'=> $spare_part);
      return array('status' => 1 ,'result'=>$final);
    }

    public function update_token(Request $request){

      $token = JWTAuth::getToken();
      $fixedtoken = JWTAuth::setToken($token)->toUser();
      $user_id = $fixedtoken->id;

      $notif = transaction_notif_token::where('farmer_id', $user_id)
                                      ->where('device_id', $request->device_id)
                                      ->first();
      if($notif == null){
        $final = array('message'=> 'device tidak ditemukan');
        return array('status' => 0 ,'result'=>$final);
      }

      $notif->token = $request->token;
      $notif->save();

      $final = array('message'=> 'update token berhasil');
      return array('status' => 1 ,'result'=>$final);
    }

    public function delete_token(Request $request){

      $token = JWTAuth::getToken();
      $fixedtoken = JWTAuth::setToken($token)->toUser();
      $user_id = $fixedtoken->id;

      $notif = transaction_notif_token::where('farmer_id', $user_id)
                                      ->where('device_id', $request->device_id)
                                      ->first();
      if($notif == null){
        $final = array('message'=> 'device tidak ditemukan');
        return array('status' => 0 ,'result'=>$final);
      }
      $notif->delete();

      $final = array('message'=> 'delete token berhasil');
      return array('status' => 1 ,'result'=>$final);
    }


    public function change_password(Request $request){

      $token = JWTAuth::getToken();
      $fixedtoken = JWTAuth::setToken($token)->toUser();
      $user_id = $fixedtoken->id;

      $notif = Farmer::find($user_id);
      if($notif == null){
        $final = array('message'=> 'petani tidak ditemukan');
        return array('status' => 0 ,'result'=>$final);
      }

      if (Hash::check($request->old_password ,$notif->password )) {

        $notif->password = Hash::make($request->new_password);
        $notif->save();

        $final = array('message'=> 'mengganti password berhasil');
        return array('status' => 1 ,'result'=>$final);
      }else{

        $final = array('message'=> 'password tidak sama');
        return array('status' => 0 ,'result'=>$final);
      }
    }

    public function show_alsin_type(Request $request ){

       $rice_seed = trasansaction_upja_spare_part::select('alsin_types.*')
                    ->where('alsin_types.alsin_other',0)
                    ->where('trasansaction_upja_spare_parts.upja_id',$request->upja_id)
                    ->Join ('spare_parts', 'spare_parts.id', '=',
                           'trasansaction_upja_spare_parts.spare_part_id')
                    ->Join ('spare_part_types', 'spare_part_types.id', '=',
                           'spare_parts.spare_part_type_id')
                    ->Join ('alsin_types', 'alsin_types.id', '=',
                          'spare_part_types.alsin_type_id')
                    ->get();

       $final = array('alsin_types'=>$rice_seed);
       return array('status' => 1 ,'result'=>$final);
    }

    public function show_spare_part_type(Request $request ){

      $rice_seed = trasansaction_upja_spare_part::select('spare_part_types.*')
                   ->where('alsin_types.alsin_other',0)
                   ->where('spare_part_types.alsin_type_id',$request->alsin_type_id)
                   ->where('trasansaction_upja_spare_parts.upja_id',$request->upja_id)
                   ->Join ('spare_parts', 'spare_parts.id', '=',
                          'trasansaction_upja_spare_parts.spare_part_id')
                   ->Join ('spare_part_types', 'spare_part_types.id', '=',
                          'spare_parts.spare_part_type_id')
                   ->Join ('alsin_types', 'alsin_types.id', '=',
                         'spare_part_types.alsin_type_id')
                   ->get();

       $final = array('spare_part_types'=>$rice_seed);
       return array('status' => 1 ,'result'=>$final);
    }

  public function show_spare_part_search(Request $request ){

      if($request->key_search == null){
        $rice_seed = trasansaction_upja_spare_part::select('spare_parts.*')
                       ->where('alsin_types.alsin_other',0)
                       ->where('spare_parts.spare_part_type_id',$request->spare_part_type_id)
                       ->where('trasansaction_upja_spare_parts.upja_id',$request->upja_id)
                       ->Join ('spare_parts', 'spare_parts.id', '=',
                              'trasansaction_upja_spare_parts.spare_part_id')
                       ->Join ('spare_part_types', 'spare_part_types.id', '=',
                              'spare_parts.spare_part_type_id')
                       ->Join ('alsin_types', 'alsin_types.id', '=',
                             'spare_part_types.alsin_type_id')
                       ->get();
      }else{
         $rice_seed = trasansaction_upja_spare_part::select('spare_parts.*')
                        ->where('alsin_types.alsin_other',0)
                        ->where('spare_parts.spare_part_type_id',$request->spare_part_type_id)
                        ->where('trasansaction_upja_spare_parts.upja_id',$request->upja_id)
                        ->where('spare_parts.name', 'like', '%' . $request->key_search . '%')
                        ->Join ('spare_parts', 'spare_parts.id', '=',
                               'trasansaction_upja_spare_parts.spare_part_id')
                        ->Join ('spare_part_types', 'spare_part_types.id', '=',
                               'spare_parts.spare_part_type_id')
                        ->Join ('alsin_types', 'alsin_types.id', '=',
                              'spare_part_types.alsin_type_id')
                        ->get();
      }

     $final = array('spare_parts'=>$rice_seed);
     return array('status' => 1 ,'result'=>$final);
  }
}
