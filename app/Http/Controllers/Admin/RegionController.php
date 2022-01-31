<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Region;

class RegionController extends Controller
{
    
    public function index()
    {
        $region =  new Region();
        $regions = $region->getRegions(array());
        if(!empty($regions)){
            $return = array(
                'status' => '200',
                'message' => 'Regions Data Retrieved',
                'data' => $regions
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Regions Data Not Retrieved',
                'data' => new \stdClass()
            );
        }
        
        return response()->json($return);
    }

    
    public function addregion(Request $request)
    {
        //Form Validation Rules...
        $rules = [
            'region_id' => 'required',
            'region_name' => 'required',
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
        $region = new Region();
        $addRegion = $region->addRegions($request);
        if($addRegion['status']){
        $return = array(
                'status' => '200',
                'message' => $addRegion['message'],
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

   
    public function destroy($id)
    {
       $region = new Region();
        $regionDel = $region->where('id',$id)->update(array('status' => 'Deleted'));
        if($regionDel){
        $return = array(
                'status' => '200',
                'message' => 'Region Deleted Successfully',
                'data' => new \stdClass()
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Region Not Deleted',
                'data' => new \stdClass()
            );
        }
        
        return response()->json($return);
    }
}
