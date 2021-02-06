<?php


namespace App\Helpers;

use Request;
use App\Models\Upja;
use App\Models\Farmer;
use App\Models\Regency;
use App\Models\District;
use App\Models\Province;
use App\Models\Alsin_item;
use App\Models\Alsin_type;
use App\Models\Transaction_order;

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

	public static function check_alsin_item($alsin_item_id)
	{
		return Alsin_item::find($alsin_item_id);
	}

	public static function check_alsin_type($alsin_type_id)
	{
		return Alsin_type::find($alsin_type_id);
	}

	public static function check_order($transaction_order_id)
	{
		return Transaction_order::find($transaction_order_id);
	}

	public static function check_prov($prov_id)
	{
		return Province::find($prov_id);
	}

	public static function check_district($distrcit_id)
	{
		return District::find($distrcit_id);
	}

	public static function check_city($city_id)
	{
		return Regency::find($city_id);
	}
}
