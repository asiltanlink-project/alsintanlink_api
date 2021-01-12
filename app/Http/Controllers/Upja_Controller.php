<?php

namespace App\Http\Controllers;

use Hash;
use Config;
use JWTAuth;
use App\Models\Upja;
use App\Models\Alsin;
use App\Models\Alsin_item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

             $final = array('message' => 'login sukses','token' => $token, 'upja' => $fixed_user);
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
    $blog->password = Hash::make($request->password);
    $blog->save();

    //send email
    // Mail::to($blog->email)->send(new Verify($blog));

    $final = array('message'=>'register succsess', 'upja'=>$blog);
    return array('status' => 1,'result'=>$final);
  }

  public function show_detail_upja(Request $request ){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    $upja = Upja::select('id','email','name','leader_name','village','class',
                         'province','city','district')
                  ->find( $user_id );

    $final = array('upja'=>$upja);
    return array('status' => 1 ,'result'=>$final);
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
    $blog->leader_name = $request->leader_name;
    $blog->village = $request->village;
    $blog->class = $request->class;
    $blog->save();

    $final = array('message'=>'update succsess', 'upja'=>$blog);
    return array('status' => 1,'result'=>$final);
  }


  public function insert_alsin(Request $request){

    $token = JWTAuth::getToken();
    $fixedtoken = JWTAuth::setToken($token)->toUser();
    $user_id = $fixedtoken->id;

    for($i = 0; $i < sizeof($request->alsins) ; $i ++){

      $check_alsin = Alsin::select('alsin_types.name')
                          ->Join ('alsin_types', 'alsin_types.id', '=', 'alsins.alsin_type_id')
                          ->where('upja_id', $user_id)
                          ->where('alsin_type_id', $request->alsins[$i]["alsin_type_id"])
                          ->first();

      if($check_alsin != null){
        $final = array('message'=>'alsin dengan tipe ' . $check_alsin->name . ' sudah ada. silahkan update!');
        return array('status' => 0,'result'=>$final);
      }
      $alsin = new Alsin;
      $alsin->upja_id = $user_id;
      $alsin->alsin_type_id = $request->alsins[$i]["alsin_type_id"];
      $alsin->cost = $request->alsins[$i]["cost"];
      $alsin->save();

      for($j = 0; $j < $request->alsins[$i]["total_item"] ; $j ++){

        $alsin_item = new Alsin_item;
        $alsin_item->alsin_id = $alsin->id;
        $alsin_item->save();
      }
    }

    $final = array('message'=>'update succsess');
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
                      ->Where('upjas.id', $user_id )
                      // ->Where('alsin_types.alsin_other' , 0 )
                      ->groupBy('alsins.id','alsin_types.id','alsin_types.name'
                                ,'alsins.cost','alsins.picture')
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
                      ->Where('upjas.id', $user_id )
                      ->Where('alsin_types.id', $request->alsin_type_id )
                      ->groupBy('alsins.id','alsin_types.id','alsin_types.name'
                                ,'alsins.cost','alsins.picture')
                      ->first();

    $alsin_items = DB::table('alsin_items')
                       ->select('alsin_items.id', 'alsin_items.vechile_code', 'alsin_items.status')
                      ->Where('alsin_items.alsin_id',  $alsin->alsin_id )
                      ->get();

    $final = array('alsin'=>$alsin, 'alsin_items'=>$alsin_items);
    return array('status' => 1 ,'result'=>$final);
  }
}
