<?php


namespace App\Helpers;

use Request;
use App\Models\Upja;
use App\Models\Farmer;

class LogActivity
{
	public static function check_farmer($farmer_id)
	{
		return Farmer::find($farmer_id);
	}

	public static function check_upja($upja_id)
	{
		return Upja::find($upja_id);
	}
}
