<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;
    use HasFactory;
    
    public function getSettingByCode($code){
        $setting = $this->where(['setting_code' => $code, 'setting_status' => 'Active'])->first();
        if($setting !== null){
            return $setting;
        }else{
            return False;
        }
    }
}
