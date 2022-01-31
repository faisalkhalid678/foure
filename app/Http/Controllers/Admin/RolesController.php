<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use App\Models\Role_right;

class RolesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $role = new Role();
        $id = "";
        $role_code = "";
        $roles = $role->getRoles($id, $role_code);
        if($roles){
        $return = array(
                'status' => '200',
                'message' => 'Roles Listing',
                'data' => $roles
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Roles Not Found',
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
    public function addroles(Request $request)
    {
        $rules = [
            'role_id' => 'required',
            'role_name' => 'required',
            'role_code' => 'required',
            'rights' => 'required',
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
       
        $role = new Role();
        $addRole = $role->addRoles($request);
        if($addRole['status'] === TRUE){
        $return = array(
                'status' => '200',
                'message' => $addRole['message'],
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
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
        $role = new Role();
        $update = array(
            'status' => $request->status
        );
        $updated = $role->where('id',$id)->update($update);
        if($updated){
        $return = array(
                'status' => '200',
                'message' => 'Roles '. ucfirst($request->status).' Successfully',
                'data' => new \stdClass()
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Roles Not '. ucfirst($request->status).' Successfully',
                'data' => new \stdClass()
            );
        }
        return response()->json($return);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
