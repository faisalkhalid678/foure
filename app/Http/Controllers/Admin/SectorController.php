<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sector;

class SectorController extends Controller
{
    
    public function index()
    {
        $sector =  new Sector();
        $sectors = $sector->getSectors(array());
        if(!empty($sectors)){
            $return = array(
                'status' => '200',
                'message' => 'Sectors Data Retrieved',
                'data' => $sectors
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Sectors Data Not Retrieved',
                'data' => new \stdClass()
            );
        }
        
        return response()->json($return);
    }

    public function addsector(Request $request)
    {
        //Form Validation Rules...
        $rules = [
            'region_id' => 'required',
            'sector_id' => 'required',
            'sector_name' => 'required',
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
        $sector = new Sector();
        $addSector = $sector->addSectors($request);
        if($addSector['status']){
        $return = array(
                'status' => '200',
                'message' => $addSector['message'],
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
        $sector = new Sector();
        $update = array(
            'status' => $request->status
        );
        $updated = $sector->where('id',$id)->update($update);
        if($updated){
        $return = array(
                'status' => '200',
                'message' => 'Sector '. ucfirst($request->status).' Successfully',
                'data' => new \stdClass()
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Sector Not '. ucfirst($request->status).' Successfully',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }
}
