<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'location'],function ()
{
  Route::get('/province', array('middleware' => 'cors', 'uses' => 'General_Controller@province' ));
  Route::get('/city/{province_id}', array('middleware' => 'cors', 'uses' => 'General_Controller@city' ));
  Route::get('/district/{city_id}', array('middleware' => 'cors', 'uses' => 'General_Controller@district' ));

});

Route::group(['prefix' => 'farmer'],function ()
{
  Route::post('/login', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@login' ));
  Route::post('/register',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@register' ) );
  Route::post('/forget_password',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@forget_password' ) );

  Route::group(['middleware' => ['assign.guard:farmer','jwt.farmer']],function ()
  {
    Route::get('/show_upja/{district}', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_upja' ));
    Route::get('/show_detail_upja/{upja_id}',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_detail_upja' ) );
    Route::post('/generate_price', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@generate_price' )); // unused
    Route::post('/order_alsin', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@order_alsin' ));
  });
});

Route::group(['prefix' => 'upja'],function ()
{
  Route::post('/login', array('middleware' => 'cors', 'uses' => 'Upja_Controller@login' ));
  Route::post('/register', array('middleware' => 'cors', 'uses' => 'Upja_Controller@register' ));
  Route::post('/forget_password',array('middleware' => 'cors', 'uses' => 'Upja_Controller@forget_password' ) );

  Route::group(['middleware' => ['assign.guard:upja','jwt.upja']],function ()
  {
    // upja
    Route::get('/show_detail_upja', array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_detail_upja' ));
    Route::put('/update_upja',array('middleware' => 'cors', 'uses' =>  'Upja_Controller@update_upja' ));
    // alsin
    Route::post('/insert_alsin',array('middleware' => 'cors', 'uses' => 'Upja_Controller@insert_alsin' ) );
    Route::get('/show_all_alsin',array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_all_alsin' ));
    Route::get('/show_detail_alsin/{alsin_type_id}', array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_detail_alsin' ));

    Route::put('/update_alsin', array('middleware' => 'cors', 'uses' => 'Upja_Controller@update_alsin' ));
    Route::delete('/delete_alsin', array('middleware' => 'cors', 'uses' => 'Upja_Controller@delete_alsin' ));
    Route::put('/update_alsin_item', array('middleware' => 'cors', 'uses' => 'Upja_Controller@update_alsin' ));
    Route::delete('/delete_alsin_item', array('middleware' => 'cors', 'uses' => 'Upja_Controller@delete_alsin' ));
  });
});

Route::group(['prefix' => 'admin'],function ()
{
  Route::post('/login', array('middleware' => 'cors', 'uses' => 'Admin_Controller@login' ));

  Route::group(['middleware' => ['assign.guard:admin','jwt.admin']],function ()
  {
    Route::get('/show_farmer/{district}', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_farmer' ));
    Route::get('/show_upja/{district}', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_upja' ));
    Route::get('/show_detail_farmer/{farmer_id}', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_detail_farmer' ));
    Route::get('/show_detail_upja/{upja_id}', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_detail_upja' ));
  });
});
