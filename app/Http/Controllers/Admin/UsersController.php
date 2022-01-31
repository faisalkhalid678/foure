<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Auth;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = new User();
        $users = $user->getUsers(array());
        if($users){
        $return = array(
                'status' => '200',
                'message' => 'Users Listing',
                'data' => $users
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Users Not Found',
                'data' => new \stdClass()
            );
        }
        
        return response()->json($return);
    }

    
    
    public function addUser(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required',
            'role_id' => 'required',
        ];
        
        if($request->has('user_id') && $request->user_id === 0){
            $rules['password'] = 'required';
        }

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
       
        $user = new User();
        $addUser = $user->addUser($request);
        if($addUser['status'] === TRUE){
        $return = array(
                'status' => '200',
                'message' => $addUser['message'],
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
        $user = new User();
        $update = array(
            'status' => $request->status
        );
        $updated = $user->where('id',$id)->update($update);
        if($updated){
        $return = array(
                'status' => '200',
                'message' => 'Users '. ucfirst($request->status).' Successfully',
                'data' => new \stdClass()
            );
        }else{
            $return = array(
                'status' => '400',
                'message' => 'Users Not '. ucfirst($request->status).' Successfully',
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
