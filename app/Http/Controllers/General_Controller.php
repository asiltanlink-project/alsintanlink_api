<?php

namespace App\Http\Controllers;

use App\Models\Upja;
use \Mailjet\Resources;
use App\Models\Regency;
use App\Mail\Upja_Verif;
use App\Models\Province;
use App\Models\District;
use App\Models\Village;
use App\Models\rice_seed;
use App\Models\spare_part;
use App\Models\training;
use App\Models\Alsin_type;
use App\Models\transaction_order;
use Illuminate\Http\Request;
use App\Models\spare_part_type;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Mailjet\LaravelMailjet\Facades\Mailjet;

class General_Controller extends Controller
{
  public function province(Request $request ){

     $province = Province::distinct('name')->get();
     
     $final = array('provinces'=>$province, 'provinces'=>$province);
     return array('status' => 1 ,'result'=>$final);
  }

  public function city(Request $request ){

     $province = Province::where('id', $request->province_id)->first();
      
     if($province == null){
       $final = array('message'=>'province_id not found');
       return array('status' => 0 ,'result'=>$final);
     }
   //   $regencies = $province->regencies;

     $regencie = Regency::where('province_id', $request->province_id)->groupBy('name')->get();

     $final = array('citys'=>$regencie);
     return array('status' => 1 ,'result'=>$final);

  }

  public function district(Request $request ){

     $regencie = Regency::where('id', $request->city_id)->first();
     if($regencie == null){
       $final = array('message'=>'city_id not found');
       return array('status' => 0 ,'result'=>$final);
     }
   //   $district = $regencie->districts;
     $district = District::where('regency_id', $request->city_id)->groupBy('name')->get();

     $final = array('districts'=>$district);
     return array('status' => 1 ,'result'=>$final);
  }

  public function village(Request $request ){

     $regencie = District::where('id', $request->district_id)->first();
     if($regencie == null){
       $final = array('message'=>'district not found');
       return array('status' => 0 ,'result'=>$final);
     }
   //   $district = $regencie->villages;
     $district = Village::select('id','name')->where('district_id', $request->district_id)->groupBy('name')->get();

   //   $district = DB::table('indoregion_villages')
   //                     ->select('id', 'name')
   //                    ->Where('district_id',  $request->district_id )
   //                    ->groupBy('name')
   //                    ->get();

     $final = array('villages'=>$district);
     return array('status' => 1 ,'result'=>$final);
  }

  public function testing(Request $request ){

    // $tokenList = DB::table('transaction_notif_tokens')
    //               ->where('transaction_notif_tokens.farmer_id', 37)
    //               ->pluck('transaction_notif_tokens.token')
    //                ->all();
    //   $this->PostNotifMultiple('halo',$tokenList, 'ini body' ,1, $tokenList );
    //
    //  $final = array('districts'=>$tokenList);
    //  return array('status' => 1 ,'result'=>$final);

    // $tokenList = transaction_order::
    //               where('status', 'Menunggu Alsin dikirim')
    //                ->get();
    //  for($i = 0 ; $i < sizeof($tokenList) ; $i++){
    //    $tokenList[$i]->status = 'Pekerjaan Siap Dilaksanakan';
    //    $tokenList[$i]->save();
    //  }
    //
    //  return 'sa';
  }

  public function PostNotifMultiple($title,$tokenList, $body,$tag, $datauser ){

        $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
        //$token = $request->token;

        // tag
        // 0 direct to tarnsaction

        $notification = [
            'title' => $title,
            'body' => $body,
            'tag' => $tag,
            'sound' => true,
           'priority'   => "high",
        ];

        $data = [
          'title' => $title,
          'body' => $body,
          'tag' => $tag,
        ];

        $extraNotificationData = ["message" => $notification,"moredata" =>'dd'];

        $fcmNotification = [
            'registration_ids' => $tokenList, //multple token array
            // 'to'        => 'eeCCIOhO0-c:APA91bET3MCZ_ocp6LAQuNUlOyhC_ptw9dmSz-lPyC9f8UawScAirBuOqaIx1XVnaQUlkWYv5lLARClux6ft1wtiu4-jn4p4jGe6K2aq2_qUVGubtl1N3PvrrwlVrDQkVNi9R5yQw7Fv', //single token
            'notification' => $notification,
            // 'data' => $datauser,
        ];

        $headers = [
            'Authorization: key=AAAAQ8tyohs:APA91bFyh9QoQUngdhjbQwwi4LAK6ruQTY443X-VNythOzZE8U7HdosLpu0saGfMBSsnm29a4ZmMP0Mns_ul2eCGJmaoqXgxU2uC4V9nZaCS7TCbKj7Fq_8YjoNpVrpX5GZWg0mcIsMa',
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fcmNotification));
        $result = curl_exec($ch);
        curl_close($ch);

  }

  public function show_training(Request $request ){

     $training = training::All();

     $final = array('trainings'=>$training);
     return array('status' => 1 ,'result'=>$final);
  }

  public function show_spare_part(Request $request ){

      $spare_part = spare_part::select('spare_parts.*','alsin_types.name')
                           ->Join('alsin_types', 'alsin_types.id', '=', 'spare_parts.alsin_type_id')
                           ->get();

     $final = array('spare_parts'=>$spare_part);
     return array('status' => 1 ,'result'=>$final);
  }

  public function show_rice_seed(Request $request ){

     $rice_seed = rice_seed::All();

     $final = array('rice_seeds'=>$rice_seed);
     return array('status' => 1 ,'result'=>$final);
  }

  public function show_reparation(Request $request ){

     $rice_seed = Alsin_type::where('alsin_other',0)->get();

     $final = array('alsin_type'=>$rice_seed);
     return array('status' => 1 ,'result'=>$final);
  }

  public function show_alsin_type(Request $request ){

     $rice_seed = Alsin_type::where('alsin_other',0)->get();

     $final = array('alsin_types'=>$rice_seed);
     return array('status' => 1 ,'result'=>$final);
  }

  public function show_spare_part_type(Request $request ){

     $rice_seed = spare_part_type::where('alsin_type_id',$request->alsin_type_id )
                                  ->get();

     $final = array('spare_part_types'=>$rice_seed);
     return array('status' => 1 ,'result'=>$final);
  }

  public function show_spare_part_search(Request $request ){

      if($request->key_search == null){
        $rice_seed = spare_part::where('spare_part_type_id',$request->spare_part_type_id )
                                     ->get();
      }else{
        $rice_seed = spare_part::where('spare_part_type_id',$request->spare_part_type_id )
                                 ->where('name', 'like', '%' . $request->key_search . '%')
                                 ->get();
      }

     $final = array('spare_parts'=>$rice_seed);
     return array('status' => 1 ,'result'=>$final);
  }

  public function admin(Request $request ){

     return Redirect::to('https://drive.google.com/file/d/1T26SHZujX0vUQEpefQcLQMuik9x0l3pV/view?usp=sharing');
  }

  public function upja(Request $request ){

     return Redirect::to('https://drive.google.com/file/d/1U6E-i-lgFb-42tl48xSxc4_NaXkRtcln/view?usp=sharing');
  }

  public function farmer(Request $request ){

     return Redirect::to('https://drive.google.com/file/d/1OUF9sRkHqvsE2LT10BBM9VXuFy0BlHXW/view?usp=sharing');
  }
  public function master(Request $request ){

     return Redirect::to('https://drive.google.com/file/d/1npjvvfH80SzmAc5Nrvd-HAeA5_lFw7BQ/view?usp=sharing');
  }
  public function lab_uji(Request $request ){

     return Redirect::to('https://drive.google.com/file/d/1o4Kn1YMl6ljCsnG1sbmRoSJ5jmfSkNmp/view?usp=sharing');
  }

}
