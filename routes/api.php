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
  Route::get('/province', 'General_Controller@province');
  Route::get('/city/{province_id}', 'General_Controller@city');
  Route::get('/district/{city_id}', 'General_Controller@district');

});

Route::group(['prefix' => 'farmer'],function ()
{
  Route::post('/login', 'Farmer_Controller@login');
  Route::post('/register', 'farmer_controller@register');
  Route::post('/forget_password', 'farmer_controller@forget_password');

  Route::group(['middleware' => ['assign.guard:farmer','jwt.farmer']],function ()
  {
    Route::get('/show_upja/{district}', 'Farmer_Controller@show_upja');
    Route::get('/show_detail_upja/{upja_id}', 'Farmer_Controller@show_detail_upja');
    Route::post('/generate_price', 'Farmer_Controller@generate_price');
    Route::post('/order_alsin', 'Farmer_Controller@order_alsin');
  });
});

Route::group(['prefix' => 'upja'],function ()
{
  Route::post('/login', 'Upja_Controller@login');
  Route::post('/register', 'Upja_Controller@register');
  Route::post('/forget_password', 'Upja_Controller@forget_password');

  Route::group(['middleware' => ['assign.guard:upja','jwt.upja']],function ()
  {
    Route::get('/test', 'Upja_Controller@test');
  });
});

Route::group(['prefix' => 'admin'],function ()
{
  Route::post('/login', 'Admin_Controller@login');

  Route::group(['middleware' => ['assign.guard:admin','jwt.admin']],function ()
  {
    Route::get('/show_farmer/{district}', 'Admin_Controller@show_farmer');
    Route::get('/show_upja/{district}', 'Admin_Controller@show_upja');
    Route::get('/show_detail_farmer/{farmer_id}', 'Admin_Controller@show_detail_farmer');
    Route::get('/show_detail_upja/{upja_id}', 'Admin_Controller@show_detail_upja');
  });
});
