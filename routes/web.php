<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    // return view('welcome');
    return Redirect::to('https://play.google.com/store/apps/details?id=com.alsintanlink.mobileapp');
});

Route::group(['prefix' => 'general'],function ()
{
  Route::get('/upja_verif_succsess/{upja_id}', array('middleware' => 'cors', 'uses' => 'Upja_Controller@upja_verif_succsess' ));
  Route::get('/lab_uji_verif_succsess/{lab_uji_id}', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@lab_uji_verif_succsess' ));
  Route::get('/farmer_forget_form/{token}', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@farmer_forget_form' ));
  Route::get('/upja_forget_form/{token}', array('middleware' => 'cors', 'uses' => 'Upja_Controller@upja_forget_form' ));
  Route::get('/lab_uji_forget_password_form/{lab_uji_id}', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@lab_uji_forget_password_form' ));
  Route::get('/farmer_forget_password_succsess', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@farmer_forget_password_succsess' ));
  Route::get('/upja_forget_password_succsess', array('middleware' => 'cors', 'uses' => 'Upja_Controller@upja_forget_password_succsess' ));

});
