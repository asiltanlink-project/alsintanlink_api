<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use App\Models\Upja;
use App\Models\Alsin;
use App\Models\Farmer;
use App\Models\Alsin_item;
use App\Models\Alsin_type;
use Illuminate\Http\Request;
use App\Models\Transaction_order;
use Illuminate\Support\Facades\DB;
use App\Helpers\LogActivity as Helper;
use App\Models\Transaction_order_child;
use Tymon\JWTAuth\Exceptions\JWTException;

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

             $fixed_user = Farmer::select('id','phone_verify','phone_number')->find($user->id);
             $final = array('message' => 'login sukses','token' => $token ,'farmer' => $fixed_user);
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
          'name' => 'required',
          'phone_number' => 'required|unique:farmers',
          'password' => 'required',
      ];
  }

  public function register(Request $request){

    $validator = \Validator::make($request->all(), $this->rules());

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
    $blog->password = Hash::make($request->password);
    $blog->save();

    //send email
    // Mail::to($blog->email)->send(new Verify($blog));

    $final = array('message'=>'register succsess', 'farmer'=>$blog);
    return array('status' => 1,'result'=>$final);
  }

  public function show_upja(Request $request ){

    $check_district = Helper::check_district($request->district);

    if($check_district == null){
      $final = array('message'=> "district not found");
      return array('status' => 0 ,'result'=>$final);
    }

    $upja = Upja::select('id','name','leader_name','village','class')
                    ->where('district', $request->district )
                    ->get();
    $final = array('upjas'=>$upja);
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_detail_upja(Request $request ){

    $check_upja = Helper::check_upja($request->upja_id);
    if($check_upja == null){
      return array('status' => 0 ,'result'=>null);
    }

    $upja = Upja::select('id','name','leader_name','village','class')
                    ->Where('upjas.id', $request->upja_id )
                    ->first();

    $alsintan = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id','alsin_types.name'
                                ,'alsin_types.picture as alsin_type_picture'
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
                                   )
                      ->Where('upjas.id', $request->upja_id )
                      ->Where('alsin_types.alsin_other' , 0 )
                      ->groupBy('alsin_id','alsin_types.id','alsin_types.name','alsin_types.picture')
                      ->get();

    $other_service = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id','alsin_types.name'
                                ,'alsin_types.picture as alsin_type_picture'
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
                                   )
                      ->Where('upjas.id', $request->upja_id )
                      ->Where('alsin_types.alsin_other' , 1 )
                      ->groupBy('alsin_id','alsin_types.id','alsin_types.name','alsin_types.picture')
                      ->get();

    $final = array('upja' => $upja ,'alsintan'=>$alsintan,'other_service'=>$other_service);
    return array('status' => 1 ,'result'=>$final);
  }

  public function generate_price(Request $request ){

    $rent_price = 0;
    $transport_price = 0;
    $alsin_final = [];

    for($i = 0 ; $i < sizeof ($request->alsin_colletion) ; $i ++){

      $alsin = Alsin::select('alsins.id','alsins.cost', 'alsin_types.land_area'
                          ,'alsin_types.name'
                          ,DB::raw('(select 0)  as total_item')
                          ,DB::raw('(select 0)  as price_total')
                      )
                      ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                      ->where('upja_id', $request->alsin_colletion[$i]['upja_id'] )
                      ->where('alsin_type_id', $request->alsin_colletion[$i]['alsin_type_id'] )
                      ->first();

    $alsintan = DB::table('alsins')
                       ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                       ->Join ('upjas', 'upjas.id', '=', 'alsins.upja_id')
                       ->select('alsins.id as alsin_id','alsin_types.id','alsin_types.name'
                                ,DB::raw("(select count(alsin_items.id)
                                        FROM alsin_items
                                        WHERE (alsin_items.alsin_id = alsins.id)
                                        AND (alsin_items.status = 0)
                                      ) as available
                                   ")
                                   )
                      ->Where('upjas.id', $request->alsin_colletion[$i]['upja_id']  )
                      ->Where('alsin_types.id', $request->alsin_colletion[$i]['alsin_type_id']  )
                      ->groupBy('alsin_id','alsin_types.id','alsin_types.name')
                      ->first();

      $alsin->total_item = $request->alsin_colletion[$i]['area']  / $alsin->land_area   ;

      if($alsintan->available < $alsin->total_item ){

        return array('status' => 0 ,'result'=> $alsintan->name . " Stock not Enough! You Need "
                                              . $alsin->total_item . " pcs"  );
      }

      $alsin->price_total = $alsin->cost * ($request->alsin_colletion[$i]['area']  / $alsin->land_area   );
      $alsin_final[$i] = $alsin;

      $rent_price += $alsin->price_total;
    }

    $total_price = $rent_price + $transport_price;

    $final = array('alsin_list' => $alsin_final ,'rent_price'=>$rent_price
                  ,'transport_price'=>$transport_price,'total_price'=>$total_price);
    return array('status' => 1 ,'result'=>$final);
  }

  public function order_alsin(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    // check stock larger than request
    for($i = 0 ; $i < sizeof ($request->alsins)  ; $i ++){

      // get available alsin
      $alsintan = Alsin_item::select('alsin_items.id')
                              ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                              ->Where('alsins.upja_id', $request->upja_id)
                              ->Where('alsins.alsin_type_id', $request->alsins[$i]['alsin_type_id'])
                              ->Where('alsin_items.status', 0  )
                              ->get();

      if(sizeof($alsintan) <  $request->alsins[$i]['total_item']){

        $alsintan_type = Alsin_type::select('alsin_types.name')
                                ->Where('alsin_types.id', $request->alsins[$i]['alsin_type_id'])
                                ->first();

        $final = array('message'=> 'Jumlah ' . $alsintan_type->name . ' Kurang dari yang diminta' );
        return array('status' => 0 ,'result'=>$final);
      }
    }

    // create order
    $transaction_order = new Transaction_order ;
    $transaction_order->farmer_id = $user_id;
    $transaction_order->transport_cost = $request->transport_cost;
    $transaction_order->total_cost = $request->total_cost;
    $transaction_order->save();

    for($i = 0 ; $i < sizeof ($request->alsins)  ; $i ++){

      // get available alsin
      $alsintan = Alsin_item::select('alsin_items.id')
                              ->Join ('alsins', 'alsins.id', '=', 'alsin_items.alsin_id')
                              ->Where('alsins.upja_id', $request->upja_id)
                              ->Where('alsins.alsin_type_id', $request->alsins[$i]['alsin_type_id'])
                              ->Where('alsin_items.status', 0  )
                              ->get();

      // looping for order alsin per item
      for($j = 0 ; $j < $request->alsins[$i]['total_item'] ; $j ++){

        // create detail alsin request
        $transaction_order_child = new Transaction_order_child ;
        $transaction_order_child->transaction_order_id = $transaction_order->id;
        $transaction_order_child->alsin_item_id	 = $alsintan[$j]->id;
        $transaction_order_child->land_area_range = $request->alsins[$i]['land_area_range'];
        $transaction_order_child->cost = $request->alsins[$i]['cost'];
        $transaction_order_child->save();
      }
    }

    $final = array('message'=> 'Order Succsess'  );
    return array('status' => 1 ,'result'=>$final);
  }

  public function show_transaction(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

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
                      ->Where('transaction_orders.farmer_id',  $user_id )
                      ->groupby('transaction_orders.id', 'transaction_orders.transport_cost'
                               , 'transaction_orders.total_cost', 'transaction_orders.payment_yn'
                               , 'transaction_orders.payment_yn', 'transaction_orders.delivery_time'
                               , 'transaction_orders.created_at', 'upjas.id', 'upjas.name')
                      ->get();

    $final = array('meta'=>sizeof($transactions), 'transactions'=>$transactions);
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
      return array('status' => 0 ,'result'=> $final);
    }

    $check_order = Helper::check_order($request->transaction_order_id);

    if($check_order == null){
      $final = array('message' => 'transaction type tidak ditemukan');
      return array('status' => 0 ,'result'=> $final);
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
