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
  Route::post('/testing', array('middleware' => 'cors', 'uses' => 'General_Controller@testing' ));
  Route::get('/province', array('middleware' => 'cors', 'uses' => 'General_Controller@province' ));
  Route::get('/city/{province_id}', array('middleware' => 'cors', 'uses' => 'General_Controller@city' ));
  Route::get('/city', array('middleware' => 'cors', 'uses' => 'General_Controller@city' ));
  Route::get('/district/{city_id}', array('middleware' => 'cors', 'uses' => 'General_Controller@district' ));
  Route::get('/district', array('middleware' => 'cors', 'uses' => 'General_Controller@district' ));
  Route::get('/village/{district_id}', array('middleware' => 'cors', 'uses' => 'General_Controller@village' ));
  Route::get('/village', array('middleware' => 'cors', 'uses' => 'General_Controller@village' ));

  Route::group(['middleware' => ['assign.guard:farmer','jwt.farmer']],function ()
  {
    Route::get('/doc_testing', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@doc_testing' ));
  });
});

Route::group(['prefix' => 'spare_part'],function ()
{
  // spare part
  Route::get('/show_alsin_type', array('middleware' => 'cors', 'uses' => 'General_Controller@show_alsin_type' ));
  Route::get('/show_spare_part_type/{alsin_type_id}', array('middleware' => 'cors', 'uses' => 'General_Controller@show_spare_part_type' ));
  Route::get('/show_spare_part_type', array('middleware' => 'cors', 'uses' => 'General_Controller@show_spare_part_type' ));
  Route::get('/show_spare_part/{spare_part_type_id}/{key_search?}', array('middleware' => 'cors', 'uses' => 'General_Controller@show_spare_part_search' ));
  Route::get('/show_spare_part', array('middleware' => 'cors', 'uses' => 'General_Controller@show_spare_part_search' ));
});


Route::get('/show_training', array('middleware' => 'cors', 'uses' => 'General_Controller@show_training' ));
Route::get('/show_spare_part', array('middleware' => 'cors', 'uses' => 'General_Controller@show_spare_part' ));
Route::get('/show_rice_seed', array('middleware' => 'cors', 'uses' => 'General_Controller@show_rice_seed' ));
Route::get('/show_reparation', array('middleware' => 'cors', 'uses' => 'General_Controller@show_reparation' ));

Route::group(['prefix' => 'farmer'],function ()
{
  Route::post('/login', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@login' ));
  Route::post('/register',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@register' ) );
  Route::post('/forget_change_password',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@forget_change_password' ) );
  Route::post('/submit_otp', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@submit_otp' ));
  Route::post('/reset_otp', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@reset_otp' ));
  Route::post('/resend_otp', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@resend_otp' ));
  Route::post('/forget_password',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@forget_password' ) );
  Route::get('/show_upja/{village_id}', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_upja' ));
  Route::get('/show_detail_upja/{upja_id}',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_detail_upja' ) );
  Route::get('/show_all_alsin',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_all_alsin' ) );
  Route::put('/update_token',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@update_token' ) );
  Route::delete('/delete_token',array('middleware' => 'cors', 'uses' => 'Farmer_Controller@delete_token' ) );

  Route::group(['middleware' => ['assign.guard:farmer','jwt.farmer']],function ()
  {
    //profile
    Route::get('/show_detail_profile', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_detail_profile' ));
    Route::put('/update_user', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@update_user' ));
    Route::put('/change_password', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@change_password' ));
    // alsin
    Route::post('/order_alsin', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@order_alsin' ));
    Route::put('/accept_pricing', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@accept_pricing' ));
    Route::put('/decline_pricing', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@decline_pricing' ));
    Route::delete('/delete_order', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@delete_order' ));
    Route::get('/show_transaction', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_transaction' ));
    Route::get('/show_detail_transaction/{transaction_order_id}', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_detail_transaction' ));
    Route::get('/show_upja_raparation/{upja_id}', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_upja_raparation' ));
    Route::get('/show_upja_rice_seed/{upja_id}', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_upja_rice_seed' ));
    Route::get('/show_upja_training/{upja_id}', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_upja_training' ));
    Route::get('/show_upja_spare_part/{upja_id}', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_upja_spare_part' ));

    //unused
    Route::get('/show_detail_transaction_alsin/{transaction_order_id}/{alsin_type_id}', array('middleware' => 'cors', 'uses' => 'Farmer_Controller@show_detail_transaction_alsin' ));
  });
});

Route::group(['prefix' => 'upja'],function ()
{
  Route::post('/login', array('middleware' => 'cors', 'uses' => 'Upja_Controller@login' ));
  Route::post('/register', array('middleware' => 'cors', 'uses' => 'Upja_Controller@register' ));
  Route::post('/submit_otp', array('middleware' => 'cors', 'uses' => 'Upja_Controller@submit_otp' ));
  Route::post('/reset_otp', array('middleware' => 'cors', 'uses' => 'Upja_Controller@reset_otp' ));
  Route::post('/resend_otp', array('middleware' => 'cors', 'uses' => 'Upja_Controller@resend_otp' ));
  Route::post('/forget_password',array('middleware' => 'cors', 'uses' => 'Upja_Controller@forget_password' ) );
  Route::post('/change_password',array('middleware' => 'cors', 'uses' => 'Upja_Controller@change_password' ) );
  Route::get('/show_all_alsin_type',array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_all_alsin_type' ));

  Route::group(['middleware' => ['assign.guard:upja','jwt.upja']],function ()
  {
    // upja
    Route::get('/show_detail_upja', array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_detail_upja' ));
    Route::put('/update_upja',array('middleware' => 'cors', 'uses' =>  'Upja_Controller@update_upja' ));
    // alsin
    Route::post('/insert_alsin',array('middleware' => 'cors', 'uses' => 'Upja_Controller@insert_alsin' ) );
    Route::get('/show_all_alsin',array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_all_alsin' ));
    Route::get('/show_detail_alsin', array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_detail_alsin' ));
    Route::get('/show_alsin_item_available',array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_alsin_item_available' ));

    Route::put('/update_alsin', array('middleware' => 'cors', 'uses' => 'Upja_Controller@update_alsin' ));
    Route::delete('/delete_alsin', array('middleware' => 'cors', 'uses' => 'Upja_Controller@delete_alsin' ));
    Route::put('/update_alsin_item', array('middleware' => 'cors', 'uses' => 'Upja_Controller@update_alsin_item' ));
    Route::delete('/delete_alsin_item', array('middleware' => 'cors', 'uses' => 'Upja_Controller@delete_alsin_item' ));
    // transaction
    Route::get('/show_all_transaction',array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_all_transaction' ));
    Route::get('/show_form_pricing',array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_form_pricing' ));
    Route::get('/show_detail_transaction',array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_detail_transaction' ));
    Route::put('/update_status_transaction',array('middleware' => 'cors', 'uses' => 'Upja_Controller@update_status_transaction' ) );

    // spare_part
    Route::get('/show_spare_part_upja',array('middleware' => 'cors', 'uses' => 'Upja_Controller@show_spare_part_upja' ));
    Route::post('/insert_spare_part_upja',array('middleware' => 'cors', 'uses' => 'Upja_Controller@insert_spare_part_upja' ));
    Route::delete('/delete_spare_part_upja',array('middleware' => 'cors', 'uses' => 'Upja_Controller@delete_spare_part_upja' ));
  });
});

Route::group(['prefix' => 'admin'],function ()
{
  Route::post('/login', array('middleware' => 'cors', 'uses' => 'Admin_Controller@login' ));

  Route::group(['middleware' => ['assign.guard:admin','jwt.admin']],function ()
  {
    Route::get('/show_farmer', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_farmer' ));
    Route::get('/show_upja', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_upja' ));
    Route::get('/show_detail_farmer', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_detail_farmer' ));
    Route::get('/show_detail_upja', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_detail_upja' ));
    Route::get('/show_detail_alsin', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_detail_alsin' ));
    Route::get('/show_detail_alsin_item', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_detail_alsin_item' ));

    // transaction
    Route::get('/show_all_transaction', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_all_transaction' ));
    Route::get('/show_detail_transaction', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_detail_transaction' ));
    Route::get('/show_detail_transaction_alsin', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_detail_transaction_alsin' ));
    // Route::get('/show_detail_transaction_other_service', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_detail_transaction_other_service' ));
    Route::get('/show_all_upja_traction', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_all_upja_traction' ));
    Route::post('/send_upja_alert', array('middleware' => 'cors', 'uses' => 'Admin_Controller@send_upja_alert' ));

    // spare part
    Route::post('/import_spare_part', array('middleware' => 'cors', 'uses' => 'Admin_Controller@import_spare_part' ));
    Route::get('/show_spare_part_type', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_spare_part_type' ));
    Route::get('/show_spare_part', array('middleware' => 'cors', 'uses' => 'Admin_Controller@show_spare_part' ));
    Route::put('/update_spare_part_type', array('middleware' => 'cors', 'uses' => 'Admin_Controller@update_spare_part_type' ));
    Route::delete('/delete_spare_part', array('middleware' => 'cors', 'uses' => 'Admin_Controller@delete_spare_part' ));
  });
});

Route::group(['prefix' => 'lab_uji'],function ()
{
  Route::post('/register', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@register' ));
  Route::post('/login', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@login' ));
  Route::post('/forget_password', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@forget_password' ));
  Route::put('/update_token',array('middleware' => 'cors', 'uses' => 'lab_uji_controller@update_token' ) );
  Route::delete('/delete_token',array('middleware' => 'cors', 'uses' => 'lab_uji_controller@delete_token' ) );

  Route::group(['middleware' => ['assign.guard:lab_uji','jwt.lab_uji']],function ()
  {
    Route::get('/show_detail_lab_uji', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@show_detail_lab_uji' ));
    Route::put('/create_company_profile', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@create_company_profile' ));
    Route::post('/upload_doc_perorangan', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@upload_doc_perorangan' ));
    Route::post('/upload_doc_dalam_negeri', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@upload_doc_dalam_negeri' ));
    Route::post('/upload_doc_import', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@upload_doc_import' ));
    Route::post('/create_Form', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@create_Form' ));
    Route::get('/show_jadwal_uji', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@show_jadwal_uji' ));
    Route::post('/send_billing', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@send_billing' ));
    Route::post('/upload_billing', array('middleware' => 'cors', 'uses' => 'lab_uji_controller@upload_billing' ));
  });
});

Route::group(['prefix' => 'master'],function ()
{
  Route::post('/login', array('middleware' => 'cors', 'uses' => 'master_controller@login' ));

  Route::group(['middleware' => ['assign.guard:master','jwt.master']],function ()
  {
    Route::get('/show_lab_uji', array('middleware' => 'cors', 'uses' => 'master_controller@show_lab_uji' ));
    Route::get('/show_detail_lab_uji', array('middleware' => 'cors', 'uses' => 'master_controller@show_detail_lab_uji' ));
    Route::put('/change_status_doc_file', array('middleware' => 'cors', 'uses' => 'master_controller@change_status_doc_file' ));
    Route::put('/change_status_form', array('middleware' => 'cors', 'uses' => 'master_controller@change_status_form' ));
    Route::post('/create_jadwal_uji', array('middleware' => 'cors', 'uses' => 'master_controller@create_jadwal_uji' ));
    Route::post('/change_status_jadwal_uji', array('middleware' => 'cors', 'uses' => 'master_controller@change_status_jadwal_uji' ));
  });
});
