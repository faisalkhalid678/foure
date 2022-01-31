<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class Sector extends Model {

    use HasFactory;

    public $timestamps = false;

    public function getSectors($where) {
        $query = DB::table('sectors')
                ->select('sectors.id as sector_id', 'sectors.sector_name', 'sectors.region_id', 'regions.region_name', 'sectors.status')
                ->join('regions', 'sectors.region_id', '=', 'regions.id')
                ->where($where);
        if (!empty($where)) {
            $sectorsData = $query->first();
        } else {
            $sectorsData = $query->get()->toArray();
        }
        return $sectorsData;
    }

    public function addSectors($input) {
        $isExist = $this->getSectors(array('sector_name' => $input->sector_name));
        if ($isExist === null) {
            $isExist = $this->getSectors(array('sectors.id' => $input->sector_id));
        }
        if ($isExist === null) {
            $this->region_id = $input->region_id;
            $this->sector_name = $input->sector_name;
            $this->date_created = date(getConstant('DATETIME_DB_FORMAT'));
            $this->date_updated = date(getConstant('DATETIME_DB_FORMAT'));
            $return = $this->save();
            $message = "Sector Added Successfully!";
        } else {
            $update['region_id'] = $input->region_id;
            $update['sector_name'] = $input->sector_name;
            $update['status'] = getConstant('STATUS_ACTIVE');
            $update['date_updated'] = date(getConstant('DATETIME_DB_FORMAT'));
            $return = $this->where('id', $isExist->sector_id)->update($update);
            $message = "Sector Updated Successfully!";
        }

        return array(
            'status' => TRUE,
            'message' => $message
        );
    }

}
