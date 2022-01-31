<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Right extends Model {

    public $timestamps = false;
    use HasFactory;

    public function getRights($id, $right_code) {
        $where['status'] = getConstant('STATUS_ACTIVE');
        if ($right_code !== "") {
            $where['right_code'] = $right_code;
        }
        if ($id !== "") {
            $where['id'] = $id;
        }
        if ($id !== "" || $right_code !== "") {
            $rightsData = $this->where($where)->first();
        } else {
            $rightsData = $this->where($where)->get()->toArray();
        }
        return $rightsData;
    }

    public function addRights($input) {
        
        $isExist = $this->getRights($input->right_id, $right_code="");
        if ($isExist === null &&  $input->right_id === "0") {
            $this->parent_id = $input->parent_id;
            $this->right_name = $input->right_name;
            $this->right_code = $input->right_code;
            $this->date_created = date(getConstant('DATETIME_DB_FORMAT'));
            $return = $this->save();
            $message = "Right Added Successfully!";
        } else {
            $update['parent_id'] = $input->parent_id;
            $update['right_name'] = $input->right_name;
            $update['right_code'] = $input->right_code;
            $return = $this->where('id',$isExist->id)->update($update);
            $message = "Right Updated Successfully!";
        }
        
        return array(
            'status' => TRUE,
            'message' => $message
        );
    }

}
