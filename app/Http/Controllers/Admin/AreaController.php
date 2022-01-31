<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Area;

class AreaController extends Controller
{
   public function index()
    {
        $area =  new Area();
        $areas = $area->getAreas(array());
        if(!empty($areas)){
            $return = array(
                'status' => '200',
                'message' => 'Areas Data Retrieved',
                'data' => $areas
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Areas Data Not Retrieved',
                'data' => new \stdClass()
            );
        }
        
        return response()->json($return);
    }

    public function addarea(Request $request)
    {
        //Form Validation Rules...
        $rules = [
            'sector_id' => 'required',
            'area_id' => 'required',
            'area_name' => 'required',
        ];
        //Check validation for the inputs and return response in case error occured
        $validate = checkValidation($request, $rules);
        if ($validate) {
            $errors = $validate;
            $return = array(
                'status' => '400',
                'message' => 'validation error occured',
                'errors' => $errors,
                'result' => []
            );
            return response()->json($return);
        }
        $area =  new Area();
        $addArea = $area->addAreas($request);
        if($addArea['status']){
        $return = array(
                'status' => '200',
                'message' => $addArea['message'],
                'data' => new \stdClass()
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Operation Unsuccessful!',
                'data' => new \stdClass()
            );
        }
        
        return response()->json($return);
    }

   
    public function update(Request $request, $id)
    {
        $rules = [
            'status' => 'required',
        ];

        //Check validation for the inputs and return response in case error occured
        $validate = checkValidation($request, $rules);
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
        $area = new Area();
        $update = array(
            'status' => $request->status
        );
        $updated = $area->where('id',$id)->update($update);
        if($updated){
        $return = array(
                'status' => '200',
                'message' => 'Area '. ucfirst($request->status).' Successfully',
                'data' => new \stdClass()
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Area Not '. ucfirst($request->status).' Successfully',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }
}
