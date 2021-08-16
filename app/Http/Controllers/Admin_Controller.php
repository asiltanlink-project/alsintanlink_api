<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use Carbon\Carbon;
use App\Models\Upja;
use App\Models\Admin;
use App\Models\Farmer;
use App\Models\Village;
use App\Models\District;
use App\Models\Regency;
use App\Mail\Upja_Alert;
use Illuminate\Http\Request;
use App\Models\spare_part;
use App\Models\spare_part_type;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Mail;
use App\Models\Excel\spare_part_Excel;
use App\Helpers\LogActivity as Helper;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Exceptions\JWTException;

use App\Models\Other_Service\transaction_order_rmu;
use App\Models\Other_Service\transaction_order_rice;
use App\Models\Other_Service\transaction_order_training;
use App\Models\Other_Service\transaction_order_reparation;
use App\Models\Other_Service\transaction_order_rice_seed;
use App\Models\Other_Service\transaction_order_spare_part;

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
         $final = array('message' => 'login berhasil','token' => $token,'admin' => $fixed_user);
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

    if($request->village_id != null){

        $upja = Upja::select('id as upja_id','name as upja_name','leader_name','class'
                                )
                            ->where('village', $request->village_id )
                            ->paginate(10);

    }else  if($request->village_id == null && $request->district_id != null){

        $upja = Upja::select('id as upja_id','name as upja_name','leader_name','class'
                                )
                            ->where('district', $request->district_id )
                            ->paginate(10);

    }else  if($request->district_id == null && $request->city_id != null){

        $upja = Upja::select('id as upja_id','name as upja_name','leader_name','class'
                                )
                            ->where('city', $request->city_id )
                            ->paginate(10);

    }else  if($request->city_id == null && $request->province_id != null){

        $upja = Upja::select('id as upja_id','name as upja_name','leader_name','class'
                                )
                            ->where('province', $request->province_id )
                            ->paginate(10);
    }else{

      $upja = Upja::select('id as upja_id','name as upja_name','leader_name','class'
                    )
                ->paginate(10);
    }

    $max_page = round($upja->total() / 10);
    $current_page =$upja->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('upjas'=>$upja,'max_page'=> $max_page,
                   'current_page'=> $current_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_upja(Request $request ){

    $upja = Upja::select('upjas.id as upja_id','upjas.name as upja_name','leader_name','class','legality',
                          'indoregion_provinces.name as province','indoregion_regencies.name as city',
                          'indoregion_districts.name as district','indoregion_villages.name as village',
                          DB::raw("(CASE WHEN upjas.class = 0 THEN 'Pemula'
                                    WHEN upjas.class = 1 THEN 'Berkembang'
                                    WHEN upjas.class = 2 THEN 'Profesional'
                                    ELSE 'Pemula' END) as class")
                            )
                    ->Join ('indoregion_provinces', 'indoregion_provinces.id', '=', 'upjas.province')
                    ->Join ('indoregion_regencies', 'indoregion_regencies.id', '=', 'upjas.city')
                    ->Join ('indoregion_districts', 'indoregion_districts.id', '=', 'upjas.district')
                    ->Join ('indoregion_villages', 'indoregion_villages.id', '=', 'upjas.village')
                    ->where('upjas.id', $request->upja_id )
                    ->first();

    if($upja == null){
      $final = array('message'=> "upja tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }

    $alsins = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id as alsin_type_id','alsin_types.name as alsin_type_name'
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
                                      ,DB::raw("(select count(alsin_items.id)
                                              FROM alsin_items
                                              WHERE (alsin_items.alsin_id = alsins.id)
                                              AND (alsin_items.status = 'Rusak')
                                            ) as rusak
                                         ")
                                         ,DB::raw("(select (available + not_available + rusak)
                                            ) as total_item
                                         ")
                                   )
                      ->Where('upjas.id', $request->upja_id )
                      ->Where('alsin_types.alsin_other', 0 )
                      ->groupBy('alsins.id','alsin_types.id','alsin_types.name'
                                ,'alsins.cost','alsin_types.picture_detail')
                      ->get();

    $other_service = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id as alsin_type_id','alsin_types.name as alsin_type_name'
                                ,'alsins.cost','alsin_types.picture_detail')
                      ->Where('upjas.id', $request->upja_id )
                      ->Where('alsin_types.alsin_other', 1 )
                      ->groupBy('alsin_types.id')
                      ->distinct()
                      ->get();

  $transactions = DB::table('transaction_orders')
                     ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                              , 'transaction_orders.total_cost', 'transaction_orders.status'
                              , 'farmers.id as farmer_id', 'farmers.name as farmer_name'
                              ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                              ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                              )
                    ->Join ('farmers', 'farmers.id', '=', 'transaction_orders.farmer_id')
                    ->Where('transaction_orders.upja_id',  $request->upja_id )
                    ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                             , 'transaction_orders.total_cost', 'transaction_orders.status'
                             , 'transaction_orders.delivery_time'
                             , 'transaction_orders.created_at', 'farmers.id', 'farmers.name')
                    ->paginate(10);

  // $other_service = array('rices'=>$rice , 'rice_seeds'=>$rice_seed, 'rmus'=>$rmu,
  //                 'reparations'=>$transaction_order_reparation,
  //                 'trainings'=>$transaction_order_training,
  //                 'spare_parts'=>$transaction_order_spare_part  );
    $max_page = round($transactions->total() / 10);
    $current_page =$transactions->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('upja'=>$upja ,'alsins' =>$alsins,'other_service' =>$other_service,
                   'transactions' =>$transactions,'max_page'=> $max_page,
                   'current_page'=> $current_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_farmer(Request $request ){

    if($request->village_id != null){

      $farmers = Farmer::select('id as farmer_id','name as farmer_name','phone_number','phone_verify')
                ->where('village', $request->village_id )
                ->paginate(10);

    }else  if($request->village_id == null && $request->district_id != null){
 
      $farmers = Farmer::select('id as farmer_id','name as farmer_name','phone_number','phone_verify')
                ->where('district', $request->district_id )
                ->paginate(10);
    }else  if($request->district_id == null && $request->city_id != null){

      $farmers = Farmer::select('id as farmer_id','name as farmer_name','phone_number','phone_verify')
                  ->where('city', $request->city_id )
                  ->paginate(10);

    }else  if($request->city_id == null && $request->province_id != null){

        $farmers = Farmer::select('id as farmer_id','name as farmer_name','phone_number','phone_verify')
                  ->where('province', $request->province_id )
                  ->paginate(10);
    }else{
      $farmers = Farmer::select('id as farmer_id','name as farmer_name','phone_number','phone_verify')
                  ->paginate(10);
    }

    $max_page = round($farmers->total() / 10);
    $current_page =$farmers->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }
    $final = array('farmers'=>$farmers,'max_page'=> $max_page,
                   'current_page'=> $current_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_farmer(Request $request ){

    $farmer = Farmer::select('farmers.id as farmer_id','farmers.name as farmer_name','phone_number','phone_verify',
                            'indoregion_provinces.name as province','indoregion_regencies.name as city',
                            'indoregion_districts.name as district','indoregion_villages.name as village')
                    ->Join ('indoregion_provinces', 'indoregion_provinces.id', '=', 'farmers.province')
                    ->Join ('indoregion_regencies', 'indoregion_regencies.id', '=', 'farmers.city')
                    ->Join ('indoregion_districts', 'indoregion_districts.id', '=', 'farmers.district')
                    ->Join ('indoregion_villages', 'indoregion_villages.id', '=', 'farmers.village')
                    ->where('farmers.id', $request->farmer_id )
                    ->first();

    if($farmer == null){
      $final = array('message'=> "farmer tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }

    $transactions = DB::table('transaction_orders')
                       ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                , 'transaction_orders.total_cost', 'transaction_orders.status'
                                , 'upjas.id as upja_id', 'upjas.name as upja_name'
                                ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('upjas', 'upjas.id', '=', 'transaction_orders.upja_id')
                      ->Where('transaction_orders.farmer_id',  $request->farmer_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.status'
                               , 'transaction_orders.delivery_time'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name')
                      ->paginate(10);

    $max_page = round($transactions->total() / 10);
    $current_page =$transactions->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('farmer'=>$farmer, 'transactions'=>$transactions,
                   'max_page'=> $max_page,'current_page'=> $current_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_alsin(Request $request ){

    if($request->alsin_type_id >0 && $request->alsin_type_id < 8){
      $alsin = DB::table('alsins')
                         ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                         ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                         ->select('alsins.id as alsin_id','alsin_types.id as alsin_type_id'
                                  ,'alsin_types.name as alsin_type_name','alsins.cost','alsin_types.picture_detail'
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
                                        ,DB::raw("(select count(alsin_items.id)
                                                FROM alsin_items
                                                WHERE (alsin_items.alsin_id = alsins.id)
                                                AND (alsin_items.status = 'Rusak')
                                              ) as rusak
                                           ")
                                           ,DB::raw("(select (available + not_available + rusak)
                                              ) as total_item
                                           ")
                                     )
                        ->Where('upjas.id', $request->upja_id )
                        ->Where('alsin_types.id', $request->alsin_type_id )
                        ->groupBy('alsins.id','alsin_types.id','alsin_types.name'
                                  ,'alsins.cost','alsin_types.picture_detail')
                        ->first();

      if($alsin == null){
        $final = array('message'=> 'alsin tidak dimiliki upja');
        return array('status' => 0 ,'result'=>$final);
      }
      $alsin_items = DB::table('alsin_items')
                         ->select('alsin_items.id as alsin_item_id', 'alsin_items.vechile_code',
                                  'alsin_items.description', 'alsin_items.status')
                        ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                        ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                        ->Where('alsin_items.alsin_id',  $alsin->alsin_id )
                        ->paginate(10);

      $max_page = round($alsin_items->total() / 10);
      $current_page =$alsin_items->currentPage();
      if($max_page == 0){
        $max_page = 1;
      }

      $final = array('alsin'=>$alsin, 'alsin_items'=>$alsin_items,
                     'current_page'=>$current_page,'max_page'=>$max_page);

      return array('status' => 1 ,'result'=>$final);
    }else if($request->alsin_type_id == 9){

      $alsin_items = DB::table('transaction_upja_rice_seeds')
                        ->select('rice_seeds.id', 'rice_seeds.name')
                        ->Join ('rice_seeds', 'rice_seeds.id', '=', 'transaction_upja_rice_seeds.rice_seed_id')
                        ->Where('transaction_upja_rice_seeds.upja_id',  $request->upja_id )
                        ->paginate(10);

      $max_page = round($alsin_items->total() / 10);
      $current_page =$alsin_items->currentPage();
      if($max_page == 0){
        $max_page = 1;
      }

      $final = array('alsin_items'=>$alsin_items,
                     'current_page'=>$current_page,'max_page'=>$max_page);

      return array('status' => 1 ,'result'=>$final);

    }else if($request->alsin_type_id == 11){

      $alsin_items = DB::table('transaction_upja_reparations')
                        ->select('alsin_types.id', 'alsin_types.name')
                        ->Join ('alsin_types', 'alsin_types.id', '=', 'transaction_upja_reparations.alsin_type_id')
                        ->Where('transaction_upja_reparations.upja_id',  $request->upja_id )
                        ->paginate(10);

      $max_page = round($alsin_items->total() / 10);
      $current_page =$alsin_items->currentPage();
      if($max_page == 0){
        $max_page = 1;
      }

      $final = array('alsin_items'=>$alsin_items,
                     'current_page'=>$current_page,'max_page'=>$max_page);

      return array('status' => 1 ,'result'=>$final);

    }else if($request->alsin_type_id == 12){

      $alsin_items = DB::table('trasansaction_upja_spare_parts')
                        ->select('spare_parts.id', 'spare_parts.name')
                        ->Join ('spare_parts', 'spare_parts.id', '=', 'trasansaction_upja_spare_parts.spare_part_id')
                        ->Where('trasansaction_upja_spare_parts.upja_id',  $request->upja_id )
                        ->paginate(10);
      $max_page = round($alsin_items->total() / 10);
      $current_page =$alsin_items->currentPage();
      if($max_page == 0){
        $max_page = 1;
      }
      $final = array('alsin_items'=>$alsin_items,
                     'current_page'=>$current_page,'max_page'=>$max_page);
      return array('status' => 1 ,'result'=>$final);

    }else if($request->alsin_type_id == 13){

      $alsin_items = DB::table('transaction_upja_trainings')
                        ->select('trainings.id', 'trainings.name')
                        ->Join ('trainings', 'trainings.id', '=', 'transaction_upja_trainings.training_id')
                        ->Where('transaction_upja_trainings.upja_id',  $request->upja_id )
                        ->paginate(10);

      $max_page = round($alsin_items->total() / 10);
      $current_page =$alsin_items->currentPage();
      if($max_page == 0){
        $max_page = 1;
      }

      $final = array('alsin_items'=>$alsin_items,
                     'current_page'=>$current_page,'max_page'=>$max_page);

      return array('status' => 1 ,'result'=>$final);
    }else if($request->alsin_type_id == 14 || $request->alsin_type_id == 15){
      $alsin = DB::table('alsins')
                         ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                         ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                         ->select('alsins.id as alsin_id','alsin_types.id as alsin_type_id'
                                  ,'alsin_types.name as alsin_type_name','alsins.cost','alsin_types.picture_detail'
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
                                        ,DB::raw("(select count(alsin_items.id)
                                                FROM alsin_items
                                                WHERE (alsin_items.alsin_id = alsins.id)
                                                AND (alsin_items.status = 'Rusak')
                                              ) as rusak
                                           ")
                                           ,DB::raw("(select (available + not_available + rusak)
                                              ) as total_item
                                           ")
                                     )
                        ->Where('upjas.id', $request->upja_id )
                        ->Where('alsin_types.id', $request->alsin_type_id )
                        ->groupBy('alsins.id','alsin_types.id','alsin_types.name'
                                  ,'alsins.cost','alsin_types.picture_detail')
                        ->first();

      if($alsin == null){
        $final = array('message'=> 'alsin tidak dimiliki upja');
        return array('status' => 0 ,'result'=>$final);
      }
      $alsin_items = DB::table('alsin_items')
                         ->select('alsin_items.id as alsin_item_id', 'alsin_items.vechile_code',
                                  'alsin_items.description', 'alsin_items.status')
                        ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                        ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                        ->Where('alsin_items.alsin_id',  $alsin->alsin_id )
                        ->paginate(10);

      $max_page = round($alsin_items->total() / 10);
      $current_page =$alsin_items->currentPage();
      if($max_page == 0){
        $max_page = 1;
      }

      $final = array('alsin'=>$alsin, 'alsin_items'=>$alsin_items,
                     'current_page'=>$current_page,'max_page'=>$max_page);

      return array('status' => 1 ,'result'=>$final);
    }

    $final = array( 'alsin_items'=>$alsin_items);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_alsin_item(Request $request ){

    $alsin_item = DB::table('alsin_items')
                       ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsin_items.id as alsin_item_id' ,'alsin_items.vechile_code'
                               ,'alsin_items.status','alsin_types.id as alsin_type_id'
                               ,'alsin_types.name as alsin_type_name' , 'upjas.id as upja_id'
                               ,'upjas.name as upja_name','alsin_items.description'
                                   )
                      ->Where('alsin_items.id', $request->alsin_item_id )
                      ->first();

    if($alsin_item == null){
      $final = array('message'=> 'alsin item tidak ditemukan');
      return array('status' => 0 ,'result'=>$final);
    }

    $transactions = DB::table('transaction_orders')
                       ->select('transaction_orders.id', 'transaction_orders.transport_cost'
                                , 'transaction_orders.total_cost', 'transaction_orders.status'
                                , 'upjas.id as upja_id', 'upjas.name as upja_name'
                                , 'farmers.id as farmer_id', 'farmers.name as farmer_name'
                                ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('transaction_order_types', 'transaction_order_types.transaction_order_id',
                              '=', 'transaction_orders.id')
                      ->Join ('transaction_order_children', 'transaction_order_children.transaction_order_type_id',
                              '=', 'transaction_order_types.id')
                      ->Join ('alsin_items', 'alsin_items.id', '=', 'transaction_order_children.alsin_item_id')
                      ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                      ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                      ->Join ('farmers', 'farmers.id', '=', 'transaction_orders.farmer_id')
                      ->Where('transaction_order_children.alsin_item_id',  $request->alsin_item_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.status'
                               , 'transaction_orders.delivery_time'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name'
                               , 'farmers.id', 'farmers.name')
                      ->paginate(10);

    $max_page = round($transactions->total() / 10);
    $current_page =$transactions->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('alsin_items'=>$alsin_item, 'transactions'=>$transactions,
                   'current_page'=>$current_page,'max_page'=>$max_page);
    return array('status' => 1 ,'result'=>$final);
  }
  public function show_detail_transaction(Request $request ){

    $transaction = DB::table('transaction_orders')
                       ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                  , 'transaction_orders.total_cost', 'transaction_orders.status'
                                  , 'transaction_orders.latitude', 'transaction_orders.longtitude'
                                  , 'transaction_orders.full_adress'
                                  , 'upjas.id as upja_id', 'upjas.name as upja_name'
                                  ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                  ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('upjas', 'upjas.id', '=', 'transaction_orders.upja_id')
                      ->Where('transaction_orders.id',  $request->transaction_order_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.status'
                               , 'transaction_orders.delivery_time'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name'
                               , 'transaction_orders.latitude', 'transaction_orders.longtitude'
                               , 'transaction_orders.full_adress')
                      ->first();

    if($transaction == null){
      $final = array('message'=> 'transaction tidak ditemukan');
      return array('status' => 0 ,'result'=>$final);
    }

    $alsins = DB::table('transaction_order_types')
                       ->select('transaction_order_types.id as transaction_order_type_id',
                                'alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name',
                                'transaction_order_types.alsin_item_total', 'alsin_types.alsin_other'
                                )
                      ->Join ('alsin_types', 'alsin_types.id', '=', 'transaction_order_types.alsin_type_id')
                      ->Where('transaction_order_types.transaction_order_id',  $request->transaction_order_id )
                      ->get();

    $rice = transaction_order_rice::select('transaction_order_rices.id as transaction_order_type_id',
                                              'alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name',
                                              DB::raw("(select null) as alsin_item_total"), 'alsin_types.alsin_other'
                                                 )
                                      ->where('transaction_order_rices.transaction_order_id',
                                            $request->transaction_order_id)
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_rices.alsin_type_id')
                                      ->limit(1)
                                      ->get();

    $rice_seed = transaction_order_rice_seed::select('transaction_order_rice_seeds.id as transaction_order_type_id',
                                                      'alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name',
                                                      DB::raw("(select null) as alsin_item_total"), 'alsin_types.alsin_other'
                                                 )
                                      ->Join ('rice_seeds', 'rice_seeds.id', '=',
                                              'transaction_order_rice_seeds.rice_seed_id')
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_rice_seeds.alsin_type_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->limit(1)
                                      ->get();


    $rmu = transaction_order_rmu::select('transaction_order_rmus.id as transaction_order_type_id',
                                                      'alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name',
                                                      DB::raw("(select null) as alsin_item_total"), 'alsin_types.alsin_other'
                                                 )
                                     ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                     ->Join ('alsin_types', 'alsin_types.id', '=',
                                            'transaction_order_rmus.alsin_type_id')
                                     ->limit(1)
                                     ->get();



    $reparation = transaction_order_reparation::
                                    select('transaction_order_reparations.id as transaction_order_type_id',
                                                      'alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name',
                                                      DB::raw("(select null) as alsin_item_total"), 'alsin_types.alsin_other'
                                                 )
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_reparations.alsin_type_order_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->limit(1)
                                      ->get();

    $training = transaction_order_training::
                                    select('transaction_order_trainings.id as transaction_order_type_id',
                                                      'alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name',
                                                      DB::raw("(select null) as alsin_item_total"), 'alsin_types.alsin_other'
                                                 )
                                    ->Join ('trainings', 'trainings.id', '=',
                                            'transaction_order_trainings.training_id')
                                    ->Join ('alsin_types', 'alsin_types.id', '=',
                                            'transaction_order_trainings.alsin_type_id')
                                    ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                    ->limit(1)
                                    ->get();


    $spare_part = transaction_order_spare_part::
                                      select('transaction_order_spare_parts.id as transaction_order_type_id',
                                                        'alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name',
                                                        DB::raw("(select null) as alsin_item_total"), 'alsin_types.alsin_other'
                                                   )
                                      ->Join ('spare_parts', 'spare_parts.id', '=',
                                              'transaction_order_spare_parts.spare_part_id')
                                      ->Join ('alsin_types', 'alsin_types.id', '=',
                                              'transaction_order_spare_parts.alsin_type_id')
                                      ->where('transaction_order_id',
                                            $request->transaction_order_id)
                                      ->limit(1)
                                      ->get();

    $temp = collect();
    $other_service = $temp->merge($rice)->merge($rice_seed)->merge($rmu)
                  ->merge($reparation)->merge($training)->merge($spare_part);

    $final = array('alsins'=>$alsins, 'other_service' =>$other_service);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_transaction_alsin(Request $request ){

    if($request->alsin_other == 0){

      $alsins = DB::table('transaction_order_children')
                         ->select('alsin_items.id as alsin_item_id', 'alsin_items.vechile_code'
                                  , 'alsin_items.status', 'alsin_items.description'
                                  )
                        ->Join ('transaction_order_types', 'transaction_order_types.id', '=',
                                'transaction_order_children.transaction_order_type_id')
                        ->Join ('alsin_items', 'alsin_items.id', '=', 'transaction_order_children.alsin_item_id')
                        ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                        ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                        ->Where('transaction_order_children.transaction_order_type_id',  $request->transaction_order_type_id )
                        ->paginate(5);

      $max_page = round($alsins->total() / 5);
      $current_page = $alsins->currentPage();
      if($max_page == 0){
        $max_page = 1;
      }
      $final = array('alsin_items'=>$alsins, 'current_page'=>$current_page,
                     'max_page'=>$max_page);

      return array('status' => 1 ,'result'=>$final);

    }else if($request->alsin_other == 1){

      switch($request->alsin_type_id){
        case 8:
          $alsins =  transaction_order_rmu::select('transaction_order_rmus.*',
                                                      'alsin_types.name as alsin_type_name',
                                                      'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other')
                                            ->Join ('alsin_types', 'alsin_types.id', '=',
                                                    'transaction_order_rmus.alsin_type_id')
                                            ->where('transaction_order_rmus.id',
                                                  $request->transaction_order_type_id)
                                            ->get();
            break;
        case 9:
          $alsins = transaction_order_rice_seed::select('transaction_order_rice_seeds.*'
                                                    , 'rice_seeds.name',
                                                      'alsin_types.name as alsin_type_name',
                                                      'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other')
                                            ->Join ('rice_seeds', 'rice_seeds.id', '=',
                                                    'transaction_order_rice_seeds.rice_seed_id')
                                            ->Join ('alsin_types', 'alsin_types.id', '=',
                                                    'transaction_order_rice_seeds.alsin_type_id')
                                            ->where('transaction_order_rice_seeds.id',
                                                  $request->transaction_order_type_id)
                                            ->get();
            break;
        case 10:
          $alsins = transaction_order_rice::select('transaction_order_rices.*',
                                                  'alsin_types.name as alsin_type_name',
                                                  'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other')
                                            ->Join ('alsin_types', 'alsin_types.id', '=', 'transaction_order_rices.alsin_type_id')
                                            ->where('transaction_order_rices.id',
                                                  $request->transaction_order_type_id)
                                            ->get();
            break;
        case 11:
          $alsins = transaction_order_reparation::
                                              select('transaction_order_reparations.id','transaction_order_reparations.transaction_order_id',
                                                    'transaction_order_reparations.cost',
                                                    'transaction_order_reparations.alsin_type_id as alsins_order_id',
                                                    'transaction_order_reparations.alsin_type_order_id as alsin_type_id'
                                                    , 'alsin_type_order.name'
                                                    , 'alsin_type_order.picture_detail as alsins_order_picture'
                                                    , 'alsin_type_source.name as alsin_type_name'
                                                    , 'alsin_type_source.picture_detail as alsins_picture'
                                                    , 'alsin_type_source.alsin_other')
                                            ->Join ('alsin_types as alsin_type_order', 'alsin_type_order.id', '=',
                                                    'transaction_order_reparations.alsin_type_id')
                                            ->Join ('alsin_types as alsin_type_source', 'alsin_type_source.id', '=',
                                                    'transaction_order_reparations.alsin_type_order_id')
                                            ->where('transaction_order_reparations.id',
                                                  $request->transaction_order_type_id)
                                            ->get();
            break;
        case 12:
          $alsins = transaction_order_spare_part::
                                              select('transaction_order_spare_parts.*'
                                                    , 'spare_parts.name',
                                                    'alsin_types.name as alsin_type_name',
                                                    'alsin_types.picture_detail as alsins_picture'
                                                    , 'alsin_types.alsin_other')
                                            ->Join ('spare_parts', 'spare_parts.id', '=',
                                                    'transaction_order_spare_parts.spare_part_id')
                                            ->Join ('alsin_types', 'alsin_types.id', '=',
                                                    'transaction_order_spare_parts.alsin_type_id')
                                            ->where('transaction_order_spare_parts.id',
                                                  $request->transaction_order_type_id)
                                            ->get();

            break;
        case 13:
          $alsins = transaction_order_training::
                                            select('transaction_order_trainings.*'
                                                  , 'trainings.name',
                                                  'alsin_types.name as alsin_type_name',
                                                  'alsin_types.picture_detail as alsins_picture'
                                                  , 'alsin_types.alsin_other')
                                          ->Join ('trainings', 'trainings.id', '=',
                                                  'transaction_order_trainings.training_id')
                                          ->Join ('alsin_types', 'alsin_types.id', '=',
                                                  'transaction_order_trainings.alsin_type_id')
                                          ->where('transaction_order_trainings.id',
                                                  $request->transaction_order_type_id)
                                            ->get();
            break;
        default:
        $final = array('message'=> 'alsin type tidak ditemukan');
        return array('status' => 0 ,'result'=>$final);
        break;
      }

      $final = array('alsin_items'=>$alsins);
      return array('status' => 1 ,'result'=>$final);
    }

  }

  // public function show_detail_transaction_other_service(Request $request ){
  //
  //   switch($request->alsin_type_id){
  //     case 8:
  //       $alsins =  transaction_order_rmu::select('transaction_order_rmus.*',
  //                                                   'alsin_types.name as alsin_type_name',
  //                                                   'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other')
  //                                         ->Join ('alsin_types', 'alsin_types.id', '=',
  //                                                 'transaction_order_rmus.alsin_type_id')
  //                                         ->where('transaction_order_rmus.id',
  //                                               $request->transaction_order_type_id)
  //                                         ->get();
  //         break;
  //     case 9:
  //       $alsins = transaction_order_rice_seed::select('transaction_order_rice_seeds.*'
  //                                                 , 'rice_seeds.name',
  //                                                   'alsin_types.name as alsin_type_name',
  //                                                   'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other')
  //                                         ->Join ('rice_seeds', 'rice_seeds.id', '=',
  //                                                 'transaction_order_rice_seeds.rice_seed_id')
  //                                         ->Join ('alsin_types', 'alsin_types.id', '=',
  //                                                 'transaction_order_rice_seeds.alsin_type_id')
  //                                         ->where('transaction_order_rice_seeds.id',
  //                                               $request->transaction_order_type_id)
  //                                         ->get();
  //         break;
  //     case 10:
  //       $alsins = transaction_order_rice::select('transaction_order_rices.*',
  //                                               'alsin_types.name as alsin_type_name',
  //                                               'alsin_types.picture_detail as alsins_picture', 'alsin_types.alsin_other')
  //                                         ->Join ('alsin_types', 'alsin_types.id', '=', 'transaction_order_rices.alsin_type_id')
  //                                         ->where('transaction_order_rices.id',
  //                                               $request->transaction_order_type_id)
  //                                         ->get();
  //         break;
  //     case 11:
  //       $alsins = transaction_order_reparation::
  //                                           select('transaction_order_reparations.id','transaction_order_reparations.transaction_order_id',
  //                                                 'transaction_order_reparations.cost',
  //                                                 'transaction_order_reparations.alsin_type_id as alsins_order_id',
  //                                                 'transaction_order_reparations.alsin_type_order_id as alsin_type_id'
  //                                                 , 'alsin_type_order.name as alsin_type_order_name'
  //                                                 , 'alsin_type_order.picture_detail as alsins_order_picture'
  //                                                 , 'alsin_type_source.name as alsin_type_name'
  //                                                 , 'alsin_type_source.picture_detail as alsins_picture'
  //                                                 , 'alsin_type_source.alsin_other')
  //                                         ->Join ('alsin_types as alsin_type_order', 'alsin_type_order.id', '=',
  //                                                 'transaction_order_reparations.alsin_type_id')
  //                                         ->Join ('alsin_types as alsin_type_source', 'alsin_type_source.id', '=',
  //                                                 'transaction_order_reparations.alsin_type_order_id')
  //                                         ->where('transaction_order_reparations.id',
  //                                               $request->transaction_order_type_id)
  //                                         ->get();
  //         break;
  //     case 12:
  //       $alsins = transaction_order_spare_part::
  //                                           select('transaction_order_spare_parts.*'
  //                                                 , 'spare_parts.name',
  //                                                 'alsin_types.name as alsin_type_name',
  //                                                 'alsin_types.picture_detail as alsins_picture'
  //                                                 , 'alsin_types.alsin_other')
  //                                         ->Join ('spare_parts', 'spare_parts.id', '=',
  //                                                 'transaction_order_spare_parts.spare_part_id')
  //                                         ->Join ('alsin_types', 'alsin_types.id', '=',
  //                                                 'transaction_order_spare_parts.alsin_type_id')
  //                                         ->where('transaction_order_spare_parts.id',
  //                                               $request->transaction_order_type_id)
  //                                         ->get();
  //         break;
  //     case 13:
  //       $alsins = transaction_order_training::
  //                                         select('transaction_order_trainings.*'
  //                                               , 'trainings.name',
  //                                               'alsin_types.name as alsin_type_name',
  //                                               'alsin_types.picture_detail as alsins_picture'
  //                                               , 'alsin_types.alsin_other')
  //                                       ->Join ('trainings', 'trainings.id', '=',
  //                                               'transaction_order_trainings.training_id')
  //                                       ->Join ('alsin_types', 'alsin_types.id', '=',
  //                                               'transaction_order_trainings.alsin_type_id')
  //                                       ->where('transaction_order_trainings.id',
  //                                               $request->transaction_order_type_id)
  //                                         ->get();
  //         break;
  //     default:
  //     $final = array('message'=> 'alsin type not found');
  //     return array('status' => 0 ,'result'=>$final);
  //     break;
  //   }
  //
  //   $final = array('alsin_items'=>$alsins);
  //   return array('status' => 1 ,'result'=>$final);
  // }

  public function show_all_upja_traction(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $limit_date = Carbon::now()->subDays(14)->format('Y-m-d');
    // 2021-03-11 
    $transactions_all = DB::table('upjas')
                       ->select('upjas.id as upja_id','upjas.name as upja_name','upjas.email','upjas.leader_name',
                               'upjas.class','upjas.legality','indoregion_provinces.name as province',
                               'indoregion_regencies.name as city','indoregion_districts.name as district',
                                 DB::raw("(select count(transaction_orders.created_at)
                                         FROM transaction_orders
                                         WHERE (transaction_orders.upja_id = upjas.id)
                                         AND (transaction_orders.status != 'Menunggu Penentuan Pembayaran')
                                         AND (transaction_orders.status != 'Menunggu Konfirmasi Petani')
                                         AND (transaction_orders.status != 'Transaksi ditolak Upja')
                                         AND (transaction_orders.status != 'Petani Menolak Harga')  
                                         AND (transaction_orders.status != 'Menungggu Konfirmasi Upja')
                                         AND (transaction_orders.created_at >= '$limit_date')
                                       ) as total_transaction
                                    ")
                                )
                      ->Join ('indoregion_provinces', 'indoregion_provinces.id', '=', 'upjas.province')
                      ->Join ('indoregion_regencies', 'indoregion_regencies.id', '=', 'upjas.city')
                      ->Join ('indoregion_districts', 'indoregion_districts.id', '=', 'upjas.district')
                      ->leftJoin ('transaction_orders', 'transaction_orders.upja_id', '=', 'upjas.id')
                      ->groupby('upjas.id','upjas.name','upjas.leader_name','upjas.class',
                              'upjas.legality','indoregion_provinces.name','indoregion_regencies.name',
                              'indoregion_districts.name','upjas.email')
                      ->orderByRaw('total_transaction asc')
                      ->orderBy('upjas.name','asc')
                      ->Where('upjas.province',  $request->provinces )
                      ->paginate(10);
                      // ->get();

    $max_page = round($transactions_all->total() / 10);
    $current_page =$transactions_all->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('transactions_all'=>$transactions_all,
                   'current_page'=>$current_page,
                   'max_page'=>$max_page
                  );
    return array('status' => 1 ,'result'=>$final);
  }

  public function send_upja_alert(Request $request ){

    $limit_date = Carbon::now()->subDays(14)->format('Y-m-d');

    $transactions = Upja::
                       select('upjas.id as upja_id','upjas.name as upja_name','upjas.email','upjas.leader_name',
                               'upjas.class','upjas.legality','indoregion_provinces.name as province',
                               'indoregion_regencies.name as city','indoregion_districts.name as district',
                                 DB::raw("(select count(transaction_orders.created_at)
                                         FROM transaction_orders
                                         WHERE (transaction_orders.upja_id = upjas.id)
                                         AND (transaction_orders.status != 'Menunggu Penentuan Pembayaran')
                                         AND (transaction_orders.status != 'Menunggu Konfirmasi Petani')
                                         AND (transaction_orders.status != 'Transaksi ditolak Upja')
                                         AND (transaction_orders.status != 'Petani Menolak Harga')
                                         AND (transaction_orders.status != 'Menungggu Konfirmasi Upja')
                                         AND (transaction_orders.created_at >= '$limit_date')
                                       ) as total_transaction
                                    ")
                                )
                      ->Join ('indoregion_provinces', 'indoregion_provinces.id', '=', 'upjas.province')
                      ->Join ('indoregion_regencies', 'indoregion_regencies.id', '=', 'upjas.city')
                      ->Join ('indoregion_districts', 'indoregion_districts.id', '=', 'upjas.district')
                      ->leftJoin ('transaction_orders', 'transaction_orders.upja_id', '=', 'upjas.id')
                      ->groupby('upjas.id','upjas.name','upjas.leader_name','upjas.class',
                              'upjas.legality','indoregion_provinces.name','indoregion_regencies.name',
                              'indoregion_districts.name','upjas.email')
                      ->orderBy('upjas.name','asc')
                      ->orderBy('upjas.id','asc')
                      ->having('total_transaction' ,0)
                      // ->orderByRaw('total_transaction','asc')
                      // ->where('upjas.id','2')
                      // ->orwhere('upjas.id','3')
                      ->get();
    
    for($i=0 ; $i < sizeof($transactions); $i++ ){

      if(filter_var($transactions[$i]->email, FILTER_VALIDATE_EMAIL)){
          //send email
          Mail::to($transactions[$i]->email)->send(new Upja_Alert($transactions[$i]));

      }else  if(preg_match('/^[0-9]{3,15}+$/',$transactions[$i]->email)){

          $userkey = 'd27a72ddaf0b';
          $passkey = '4ba83675fd17f25c721dbb6d';
          $telepon = $transactions[$i]->email;
          $message = 'Peringatan! Upja anda tidak pernah melakukan transaksi! Mohon untuk segera melakukan transaksi!';
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
    }

    $final = array('message'=> 'send upja alert berhasil');
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_all_transaction(Request $request ){

    if($request->status == "" ){
      $transactions = DB::table('transaction_orders')
                         ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                  , 'transaction_orders.total_cost', 'transaction_orders.status'
                                  , 'upjas.id as upja_id', 'upjas.name as upja_name'
                                  ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                  ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                  )
                        ->Join ('upjas', 'upjas.id', '=', 'transaction_orders.upja_id')
                        ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                                 , 'transaction_orders.total_cost', 'transaction_orders.status'
                                 , 'transaction_orders.delivery_time'
                                 , 'transaction_orders.created_at', 'upjas.id', 'upjas.name')
                        ->paginate(5);
    }else{
      $transactions = DB::table('transaction_orders')
                         ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                  , 'transaction_orders.total_cost', 'transaction_orders.status'
                                  , 'upjas.id as upja_id', 'upjas.name as upja_name'
                                  ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                  ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                  )
                        ->Join ('upjas', 'upjas.id', '=', 'transaction_orders.upja_id')
                        ->Where('transaction_orders.status',  $request->status )
                        ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                                 , 'transaction_orders.total_cost', 'transaction_orders.status'
                                 , 'transaction_orders.delivery_time'
                                 , 'transaction_orders.created_at', 'upjas.id', 'upjas.name')
                        ->paginate(5);
    }

    $max_page = round($transactions->total() / 5);
    $current_page =$transactions->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('transactions'=>$transactions,
                   'current_page'=>$current_page,'max_page'=>$max_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function import_spare_part(Request $request ){

    // upload doc
    $validator = \Validator::make($request->all(), [
    'name' => 'file|required|mimes:csv,xls,xlsx', // max 7MB
    ], [
      'name.required' => 'spare part file belum dipilih',
      'name.file' => 'spare part file bukan file',
      'name.mimes' => 'spare part file bukan csv / xls / xlsx',
    ]);

  if ($validator->fails()) {
      return array('status' => 0,'message'=>$validator->errors()->first()) ;
  }

    // menangkap file excel
    $file = $request->file('name');

    // membuat nama file unik
    $nama_file = rand().$file->getClientOriginalName();

    // upload ke folder file_siswa di dalam folder public
    // $file->move('file_users',$nama_file);
    $upload = Storage::putFile(
        'public/admin/spare_part/' ,
        $request->file('name')
    );

    $storageName = basename($upload);
    $temp_path = Storage::url('admin/spare_part/' . $storageName);
    $path_url_ktp = env('APP_URL') . ''. $temp_path ;

    Excel::import(new spare_part_Excel, request()->file('name'));

    $final = array('message'=> "import berhasil");
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_spare_part_type(Request $request ){

    $check_alsin_type_id = Helper::check_alsin_type($request->alsin_type_id);

    if($check_alsin_type_id == null){
      $final = array('message'=> "alsin type tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }

    $spare_part_types = DB::table('spare_part_types')
                       ->select('spare_part_types.id as spare_part_type_id', 'spare_part_types.name
                                  as spare_part_type_name'
                                )
                      ->Where('spare_part_types.alsin_type_id',  $request->alsin_type_id )
                      ->paginate(10);

    $max_page = round($spare_part_types->total() / 10);
    $current_page =$spare_part_types->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('spare_part_types'=> $spare_part_types,
                   'current_page'=>$current_page,'max_page'=>$max_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_spare_part(Request $request ){

    $check_alsin_type_id = Helper::check_spare_part_type($request->spare_part_type_id);

    if($check_alsin_type_id == null){
      $final = array('message'=> "spare type tidak ditemukan");
      return array('status' => 0 ,'result'=>$final);
    }

    $spare_part = DB::table('spare_parts')
                       ->select('spare_parts.id as spare_part_id', 'spare_parts.name as spare_part_name'
                                , 'spare_parts.kode_produk', 'spare_parts.part_number'
                                )
                      ->Where('spare_parts.spare_part_type_id',  $request->spare_part_type_id )
                      ->paginate(10);

    $max_page = round($spare_part->total() / 10);
    $current_page =$spare_part->currentPage();
    if($max_page == 0){
      $max_page = 1;
    }

    $final = array('spare_parts'=> $spare_part,
                   'current_page'=>$current_page,'max_page'=>$max_page);
    return array('status' => 1 ,'result'=>$final);
  }

  public function update_spare_part_type(Request $request ){

    $spare_part = spare_part_type::find($request->spare_part_type_id);
    $spare_part->name = $request->name;
    $spare_part->save();

    $final = array('message'=> "update berhasil");
    return array('status' => 1 ,'result'=>$final);
  }

  public function delete_spare_part(Request $request ){

    $spare_part = spare_part::find($request->spare_part_id);
    $spare_part->delete();

    $final = array('message'=> "delete berhasil");
    return array('status' => 1 ,'result'=>$final);
  }

  public function add_new_location(Request $request ){

    if($request->district_id != ""){

        $last_village = DB::table('indoregion_villages')
                            ->select('indoregion_villages.id' )
                            ->orderBy('id','desc')
                            ->first();
      
        $add = $last_village->id+1;

        $new_location = new Village();
        $new_location->id =  $add;
        $new_location->district_id = $request->district_id;
        $new_location->name = $request->name;
        $new_location->save();

    } else if($request->city_id != ""){

      $last_village = DB::table('indoregion_districts')
                      ->select('indoregion_districts.id' )
                      ->orderBy('id','desc')
                      ->first();

      $add = $last_village->id+1;

      $new_location = new District();
      $new_location->id =  $add;
      $new_location->regency_id = $request->city_id;
      $new_location->name = $request->name;
      $new_location->save();

    } else if($request->province_id != ""){

      $last_village = DB::table('indoregion_regencies')
                      ->select('indoregion_regencies.id' )
                      ->orderBy('id','desc')
                      ->first();

      $add = $last_village->id+1;

      $new_location = new Regency();
      $new_location->id =  $add;
      $new_location->province_id = $request->province_id;
      $new_location->name = $request->name;
      $new_location->save();
    }else{

      $final = array('message'=> "create data gagal" );
      return array('status' => 0 ,'result'=>$final);
    }

    $final = array('message'=> "create data berhasil");
    return array('status' => 1 ,'result'=>$final);
  }
}
