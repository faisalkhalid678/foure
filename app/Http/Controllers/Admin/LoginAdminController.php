<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Role;

class LoginAdminController extends Controller {

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request) {

        $validator = Validator::make($request->all(), [
                    'email' => 'required',
                    'password' => 'required',
        ]);
        if ($validator->fails()) {
            $return = array(
                'status' => '400',
                'message' => 'Login Failed! Email or Password Incorrect.',
                'data' => new \stdClass()
            );
            return response()->json($return);
        }
        //print_r(request(['email', 'password'])); die();
        $credentials = request(['email', 'password']);
        //$credentials['user_type'] = 'User';
        if (Auth::attempt($credentials)) { //<---
            $user = Auth::user();
            $userArray = array(
                'userId' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'parent_id' => $user->parent_id,
            );
            $userRights = getRoleRightsArray($user->role_id);
            $token = $user->createToken('flight-api')->accessToken;
            $userArray['rights'] = $userRights;
            $userArray['token'] = $token;
            $role = new Role();
            $userRoleData = $role->getRoles($user->role_id, "");
            if($userRoleData !== null){
                $userArray['role_name'] = $roleName = $userRoleData->role_name;
                $userArray['role_code'] = $roleCode = $userRoleData->role_code;
            }else{
                $userArray['role_name'] = $roleName = "";
                $userArray['role_code'] = $roleCode = "";
            }
            $return = array(
                'status' => '200',
                'message' => 'User Login Successfully.',
                'data' => $userArray
            );
            return response()->json($return);
        } else {
            $return = array(
                'status' => '400',
                'message' => 'Login Failed! Email or Password Incorrect.',
                'data' => new \stdClass()
            );
            return response()->json($return);
        }
    }

    public function userProfile() {
        $id = Auth::user()->id;
        $user = new User();
        $userData = $user->where('id', $id)->first();
        $return = array(
            'status' => '400',
            'message' => 'User Data',
            'data' => $userData
        );
        return response()->json($return);
    }

    public function index() {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id) {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        //
    }

}
