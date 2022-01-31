<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model {

    use HasFactory;

    public $timestamps = false;

    public function getRegions($where) {
        if (!empty($where)) {
            $regionsData = $this->where($where)->first();
        } else {
            $regionsData = $this
                    ->where('status','<>','Deleted')
                    ->where($where)
                    ->get()->toArray();
        }
        return $regionsData;
    }
    
    public function addRegions($input) {
        $isExist = $this->getRegions(array('region_name' => $input->region_name));
        if($isExist === null){
            $isExist = $this->getRegions(array('id' => $input->region_id));
        }
        if ($isExist === null) {
            $this->region_name = $input->region_name;
            $this->date_created = date(getConstant('DATETIME_DB_FORMAT'));
            $this->date_updated = date(getConstant('DATETIME_DB_FORMAT'));
            $return = $this->save();
            $message = "Region Added Successfully!";
        } else {
            $update['region_name'] = $input->region_name;
            $update['status'] = getConstant('STATUS_ACTIVE');
            $update['date_updated'] = date(getConstant('DATETIME_DB_FORMAT'));
            $return = $this->where('id',$isExist->id)->update($update);
            $message = "Region Updated Successfully!";
        }
        
        return array(
            'status' => TRUE,
            'message' => $message
        );
    }

}
