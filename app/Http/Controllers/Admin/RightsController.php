<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Right;
use Illuminate\Support\Facades\Validator;

class RightsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $right =  new Right();
        $id = "";
        $right_code = "";
        $rights = $right->getRights($id, $right_code);
        if(!empty($rights)){
            $return = array(
                'status' => '200',
                'message' => 'Rights Data Retrieved',
                'data' => $rights
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Rights Data Not Retrieved',
                'data' => new \stdClass()
            );
        }
        
        return response()->json($return);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function addrights(Request $request)
    {
        //Form Validation Rules...
        $rules = [
            'right_id' => 'required',
            'parent_id' => 'required',
            'right_name' => 'required',
            'right_code' => 'required',
            
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
        $right = new Right();
        $addRight = $right->addRights($request);
        if($addRight['status']){
        $return = array(
                'status' => '200',
                'message' => $addRight['message'],
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
        $right = new Right();
        $rightDel = $right->where('id',$id)->delete();
        if($rightDel){
        $return = array(
                'status' => '200',
                'message' => 'Rights Deleted Successfully',
                'data' => new \stdClass()
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Rights Not Deleted',
                'data' => new \stdClass()
            );
        }
        
        return response()->json($return);
    }
}
