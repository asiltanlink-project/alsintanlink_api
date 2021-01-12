<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use App\Models\Upja;
use App\Models\Admin;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Helpers\LogActivity as Helper;
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
         $final = array('message' => 'login sukses','token' => $token,'admin' => $fixed_user);
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

    $check_district = Helper::check_district($request->district);

    if($check_district == null){
      $final = array('message'=> "district not found");
      return array('status' => 0 ,'result'=>$final);
    }

    $upja = Upja::select('id as upja_id','name as upja_name','leader_name','village','class')
                    ->where('district', $request->district )
                    ->get();
    $final = array('upjas'=>$upja);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_upja(Request $request ){

    $upja = Upja::select('id as upja_id','name as upja_name','leader_name','village','class')
                    ->where('id', $request->upja_id )
                    ->first();

    if($upja == null){
      $final = array('message'=> "upja not found");
      return array('status' => 0 ,'result'=>$final);
    }

    $alsins = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id as alsin_type_id','alsin_types.name as alsin_type_name'
                                ,'alsins.cost','alsins.picture'
                                ,DB::raw("(select count(alsin_items.id)
                                        FROM alsin_items
                                        WHERE (alsin_items.alsin_id = alsins.id)
                                        AND (alsin_items.status = 0)
                                      ) as available
                                   ")
                                   ,DB::raw("(select count(alsin_items.id)
                                           FROM alsin_items
                                           WHERE (alsin_items.alsin_id = alsins.id)
                                           AND (alsin_items.status = 1)
                                         ) as not_available
                                      ")
                                      ,DB::raw("(select (available + not_available)
                                            ) as total_item
                                         ")
                                   )
                      ->Where('upjas.id', $request->upja_id )
                      ->groupBy('alsins.id','alsin_types.id','alsin_types.name'
                                ,'alsins.cost','alsins.picture')
                      ->get();

  $transactions = DB::table('transaction_orders')
                     ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                              , 'transaction_orders.total_cost', 'transaction_orders.payment_yn'
                              , 'transaction_orders.payment_yn', 'farmers.id as farmer_id', 'farmers.name as farmer_name'
                              ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                              ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                              )
                    ->Join ('transaction_order_children', 'transaction_order_children.transaction_order_id',
                            '=', 'transaction_orders.id')
                    ->Join ('alsin_items', 'alsin_items.id', '=', 'transaction_order_children.alsin_item_id')
                    ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                    ->Join ('farmers', 'farmers.id', '=', 'transaction_orders.farmer_id')
                    ->Where('alsins.upja_id',  $request->upja_id )
                    ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                             , 'transaction_orders.total_cost', 'transaction_orders.payment_yn'
                             , 'transaction_orders.payment_yn', 'transaction_orders.delivery_time'
                             , 'transaction_orders.created_at', 'farmers.id', 'farmers.name')
                    ->get();

    $final = array('upja'=>$upja ,'alsins' =>$alsins,'transactions' =>$transactions );
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_farmer(Request $request ){

    $check_district = Helper::check_district($request->district);

    if($check_district == null){
      $final = array('message'=> "district not found");
      return array('status' => 0 ,'result'=>$final);
    }

    $farmers = Farmer::select('id as farmer_id','name as farmer_name','phone_number','phone_verify')
                    ->where('district', $request->district )
                    ->get();

    $final = array('farmers'=>$farmers);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_farmer(Request $request ){

    $farmer = Farmer::select('id as farmer_id','name as farmer_name','phone_number','phone_verify'
                            ,'province','city','district')
                    ->where('id', $request->farmer_id )
                    ->first();

    if($farmer == null){
      $final = array('message'=> "farmer not found");
      return array('status' => 0 ,'result'=>$final);
    }

    $transactions = DB::table('transaction_orders')
                       ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                , 'transaction_orders.total_cost', 'transaction_orders.payment_yn'
                                , 'transaction_orders.payment_yn', 'upjas.id as upja_id', 'upjas.name as upja_name'
                                ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('transaction_order_children', 'transaction_order_children.transaction_order_id',
                              '=', 'transaction_orders.id')
                      ->Join ('alsin_items', 'alsin_items.id', '=', 'transaction_order_children.alsin_item_id')
                      ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                      ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                      ->Where('transaction_orders.farmer_id',  $request->farmer_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.payment_yn'
                               , 'transaction_orders.payment_yn', 'transaction_orders.delivery_time'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name')
                      ->get();

    $transaction = array('meta'=>sizeof($transactions), 'transactions'=>$transactions);
    $final = array('farmer'=>$farmer, 'transactions'=>$transaction);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_alsin(Request $request ){

    $alsin = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id as alsin_type_id'
                                ,'alsin_types.name as alsin_type_name','alsins.cost','alsins.picture'
                                ,DB::raw("(select count(alsin_items.id)
                                        FROM alsin_items
                                        WHERE (alsin_items.alsin_id = alsins.id)
                                        AND (alsin_items.status = 0)
                                      ) as available
                                   ")
                                   ,DB::raw("(select count(alsin_items.id)
                                           FROM alsin_items
                                           WHERE (alsin_items.alsin_id = alsins.id)
                                           AND (alsin_items.status = 1)
                                         ) as not_available
                                      ")
                                      ,DB::raw("(select (available + not_available)
                                            ) as total_item
                                         ")
                                   )
                      ->Where('upjas.id', $request->upja_id )
                      ->Where('alsin_types.id', $request->alsin_type_id )
                      ->groupBy('alsins.id','alsin_types.id','alsin_types.name'
                                ,'alsins.cost','alsins.picture')
                      ->first();

    if($alsin == null){
      $final = array('message'=> 'upja tidak ditemukan');
      return array('status' => 0 ,'result'=>$final);
    }
    $alsin_items = DB::table('alsin_items')
                       ->select('alsin_items.id as alsin_item_id', 'alsin_items.vechile_code', 'alsin_items.status')
                      ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                      ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                      ->Where('alsin_items.alsin_id',  $alsin->alsin_id )
                      ->get();

    $final = array('alsin'=>$alsin, 'alsin_items'=>$alsin_items);
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
                               ,'upjas.name as upja_name'
                                   )
                      ->Where('alsin_items.id', $request->alsin_item_id )
                      ->first();

    if($alsin_item == null){
      $final = array('message'=> 'alsin item tidak ditemukan');
      return array('status' => 0 ,'result'=>$final);
    }

    $transactions = DB::table('transaction_orders')
                       ->select('transaction_orders.id', 'transaction_orders.transport_cost'
                                , 'transaction_orders.total_cost', 'transaction_orders.payment_yn'
                                , 'transaction_orders.payment_yn', 'upjas.id as upja_id', 'upjas.name as upja_name'
                                , 'farmers.id as farmer_id', 'farmers.name as farmer_name'
                                ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('transaction_order_children', 'transaction_order_children.transaction_order_id',
                              '=', 'transaction_orders.id')
                      ->Join ('alsin_items', 'alsin_items.id', '=', 'transaction_order_children.alsin_item_id')
                      ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                      ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                      ->Join ('farmers', 'farmers.id', '=', 'transaction_orders.farmer_id')
                      ->Where('transaction_order_children.alsin_item_id',  $request->alsin_item_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.payment_yn'
                               , 'transaction_orders.payment_yn', 'transaction_orders.delivery_time'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name'
                               , 'farmers.id', 'farmers.name')
                      ->get();

    $final = array('alsin_items'=>$alsin_item, 'transactions'=>$transactions);
    return array('status' => 1 ,'result'=>$final);
  }
  public function show_detail_transaction(Request $request ){

    $transaction = DB::table('transaction_orders')
                       ->select('transaction_orders.id as transaction_order_id', 'transaction_orders.transport_cost'
                                  , 'transaction_orders.total_cost', 'transaction_orders.payment_yn'
                                  , 'transaction_orders.payment_yn', 'upjas.id as upja_id', 'upjas.name as upja_name'
                                  ,DB::raw('DATE_FORMAT(transaction_orders.delivery_time, "%d-%b-%Y") as delivery_time')
                                  ,DB::raw('DATE_FORMAT(transaction_orders.created_at, "%d-%b-%Y") as order_time')
                                )
                      ->Join ('transaction_order_children', 'transaction_order_children.transaction_order_id',
                              '=', 'transaction_orders.id')
                      ->Join ('alsin_items', 'alsin_items.id', '=', 'transaction_order_children.alsin_item_id')
                      ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                      ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                      ->Where('transaction_orders.id',  $request->transaction_order_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.payment_yn'
                               , 'transaction_orders.payment_yn', 'transaction_orders.delivery_time'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name')
                      ->first();

    if($transaction == null){
      $final = array('message'=> 'transaction tidak ditemukan');
      return array('status' => 0 ,'result'=>$final);
    }

    $alsins = DB::table('transaction_order_children')
                       ->select('alsin_types.id as alsin_type_id', 'alsin_types.name as alsin_type_name'
                                  , DB::raw('count(transaction_order_children.id) as total_alsin')
                                )
                      ->Join ('alsin_items', 'alsin_items.id', '=', 'transaction_order_children.alsin_item_id')
                      ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                      ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                      ->Where('transaction_order_children.transaction_order_id',  $request->transaction_order_id )
                      ->groupby('alsin_types.id', 'alsin_types.name')
                      ->get();

    $final = array('transaction'=>$transaction, 'alsins'=>$alsins);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_transaction_alsin(Request $request ){

    $check_alsin_type = Helper::check_alsin_type($request->alsin_type_id);

    if($check_alsin_type == null){
      $final = array('message' => 'alsin type tidak ditemukan');
      return array('status' => 0 ,'result'=> 'alsin type tidak ditemukan');
    }

    $check_order = Helper::check_order($request->transaction_order_id);

    if($check_order == null){
      $final = array('message' => 'transaction type tidak ditemukan');
      return array('status' => 0 ,'result'=> 'transaction type tidak ditemukan');
    }

    $alsins = DB::table('transaction_order_children')
                       ->select('alsin_items.id as alsin_item_id', 'alsin_items.vechile_code'
                                , 'alsin_items.status', 'transaction_order_children.cost'
                                )
                      ->Join ('alsin_items', 'alsin_items.id', '=', 'transaction_order_children.alsin_item_id')
                      ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                      ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                      ->Where('transaction_order_children.transaction_order_id',  $request->transaction_order_id )
                      ->Where('alsin_types.id',  $request->alsin_type_id )
                      ->get();

    $final = array('alsin_items'=>$alsins);
    return array('status' => 1 ,'result'=>$final);
  }
}
