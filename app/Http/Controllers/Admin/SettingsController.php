<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request) {
        $setting = new Setting();
        $where['setting_status'] = getConstant('STATUS_ACTIVE');
        if($request->has('setting_code')){
            $where['setting_code'] = $request->setting_code;
        }
        if($request->has('setting_type')){
            $where['setting_type'] = $request->setting_type;
        }
        $settingsData = $setting->where($where)->orderBy('id','ASC')->get()->toArray();
        if (!empty($settingsData)) {
            $return = array(
                'status' => '200',
                'message' => 'All Settings Data',
                'data' => $settingsData
            );
        } else {
            $return = array(
                'status' => '200',
                'message' => 'Setting Data is Empty',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }

    public function update(Request $request) {
        //Form Validation Rules...
        $rules = [
            'label' => 'required',
            'setting_code' => 'required',
            'setting_value' => 'required',
            'setting_type' => 'required',
        ];

        //Check validation for the inputs and return response in case error occured
        $validate = $this->checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'data' => []
            );
            return response()->json($return);
        }

        $setting = new Setting();
        $isExist = $setting->where(['setting_code' => $request->setting_code, 'setting_status' => 'Active'])->first();
        if ($isExist === null) {
            $setting->label = $request->label;
            $setting->setting_code = $request->setting_code;
            $setting->setting_value = $request->setting_value;
            $setting->setting_type = $request->setting_type;
            if ($setting->save()) {
                $return = array(
                    'status' => '200',
                    'message' => 'Setting Saved Successfully',
                    'data' => new \stdClass()
                );
            } else {
                $return = array(
                    'status' => '400',
                    'message' => 'There were some errors',
                    'data' => new \stdClass()
                );
            }
        } else {
            $settingUpdate = array(
                'label' => $request->label,
                'setting_code' => $request->setting_code,
                'setting_value' => $request->setting_value,
                'setting_type' => $request->setting_type
            );

            $settingUpdate = $setting->where('id', $isExist->id)->update($settingUpdate);
            if ($settingUpdate) {
                $return = array(
                    'status' => '200',
                    'message' => 'Setting Updated Successfully',
                    'data' => new \stdClass()
                );
            } else {
                $return = array(
                    'status' => '400',
                    'message' => 'There were some errors',
                    'data' => new \stdClass()
                );
            }
           
        }
         return response()->json($return);
    }

    public function destroy($id) {
        //
    }

    //This Function checks the form validation and if fails return validation errors...
    public function checkValidation($request, $rules) {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $errors = $validator->errors();
            return $errors;
        } else {
            return False;
        }
    }

}
