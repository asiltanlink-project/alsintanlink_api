<?php

namespace App\Models\Excel;

use App\Models\spare_part;
use App\Models\spare_part_type;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;

class spare_part_Excel implements ToModel,WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        if($row['type'] == 'Item'){

              $check_spare_part_type = spare_part::
                                        where('name',$row['name'])
                                        ->first();

              if($check_spare_part_type == null){
                return new spare_part([
                  'name' => $row['name'],
                  'kode_produk' => $row['kode_produk'],
                  'part_number' => $row['part_number'],
                  'spare_part_type_id' => $this->convert_spare_part_type($row['spare_part_type'])
              ]);

              return;
          }
             
        }else if($row['type'] == 'Jenis'){

              $check_spare_part_type = spare_part_type::
                                        where('name',$row['name'])
                                        ->first();

              if($check_spare_part_type == null){
                return new spare_part_type([
                  'name' => $row['name'],
                  'alsin_type_id' => $this->convert_alsin_type($row['alsin_type'])
              ]);

              return;
          }
          
        }
    }

    private function convert_alsin_type($alsin_type_name){

      switch ($alsin_type_name) {
        case "Traktor Roda 2":
          return 1;
          break;
        case "Traktor Roda 4":
          return 2;
          break;
        case "Pompa":
          return 3;
          break;
        case "Transplanter":
          return 4;
          break;
        case "Power Weeder":
          return 5;
          break;
        case "Combine Harvester":
          return 6;
          break;
        case "Dryer":
          return 7;
          break;
      }
    }

    private function convert_spare_part_type($spare_part_type_name){

      $spare_part_id = spare_part_type::where('name',$spare_part_type_name)
                                        ->first();
      return $spare_part_id->id;
    }
}
