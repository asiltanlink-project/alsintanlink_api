<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\Regency;
use App\Models\District;
use App\Models\Village;
use Illuminate\Http\Request;

class General_Controller extends Controller
{
  public function province(Request $request ){

     $province = Province::all();

     $final = array('provinces'=>$province);
     return array('status' => 1 ,'result'=>$final);
  }

  public function city(Request $request ){

     $province = Province::where('id', $request->province_id)->first();
     $regencies = $province->regencies;

     $final = array('citys'=>$regencies);
     return array('status' => 1 ,'result'=>$final);

  }

  public function district(Request $request ){

     $regencie = Regency::where('id', $request->city_id)->first();
     $district = $regencie->districts;

     $final = array('districts'=>$district);
     return array('status' => 1 ,'result'=>$final);
  }
}
