<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class Area extends Model
{
    use HasFactory;
    public $timestamps = false;

    public function getAreas($where) {
        $query = DB::table('areas')
                ->select('areas.id as area_id', 'areas.area_name', 'areas.sector_id', 'sectors.sector_name', 'areas.status')
                ->join('sectors', 'areas.sector_id', '=', 'sectors.id')
                ->where($where);
        if (!empty($where)) {
            $areasData = $query->first();
        } else {
            $areasData = $query->get()->toArray();
        }
        return $areasData;
    }

    public function addAreas($input) {
        $isExist = $this->getAreas(array('area_name' => $input->area_name));
        if ($isExist === null) {
            $isExist = $this->getAreas(array('areas.id' => $input->area_id));
        }
        if ($isExist === null) {
            $this->sector_id = $input->sector_id;
            $this->area_name = $input->area_name;
            $this->date_created = date(getConstant('DATETIME_DB_FORMAT'));
            $this->date_updated = date(getConstant('DATETIME_DB_FORMAT'));
            $return = $this->save();
            $message = "Area Added Successfully!";
        } else {
            $update['sector_id'] = $input->sector_id;
            $update['area_name'] = $input->area_name;
            $update['status'] = getConstant('STATUS_ACTIVE');
            $update['date_updated'] = date(getConstant('DATETIME_DB_FORMAT'));
            $return = $this->where('id', $isExist->area_id)->update($update);
            $message = "Area Updated Successfully!";
        }

        return array(
            'status' => TRUE,
            'message' => $message
        );
    }
}
