<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Auth;
use App\Models\Region;

class User extends Authenticatable {

    use HasApiTokens,
        HasFactory,
        Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getUsers($where) {
        
        if (!empty($where)) {
            $usersData = $this
                    ->select('users.*','roles.role_name','roles.role_code')
                    ->where($where)
                    ->where('users.status', '!=', getConstant('STATUS_DELETED'))
                    ->rightJoin('roles','users.role_id','=','roles.id')
                    ->first();
        } else {
            $usersData = $this
                    ->select('users.*','roles.role_name','roles.role_code')
                    ->where('users.status', '!=', getConstant('STATUS_DELETED'))
                    ->Join('roles','users.role_id','=','roles.id')
                    ->get()->toArray();
        }
        return $usersData;
    }

    public function addUser($input) {
        $role = new Role();
        $userRoleName = "";
        $roleData = $role->where('id',Auth::user()->role_id)->first();
        if($roleData){
          $userRoleName = $roleData->role_code;
        }
        $where['email'] = $input->email;
        $isEmailExist = $this->getUsers($where);
        if ($isEmailExist !== null && $input->user_id === "0") {
            return array(
                'status' => TRUE,
                'message' => 'Email Already Exist'
            );
        }
        $where1['users.id'] = $input->user_id;
        $isExist = $this->getUsers($where1);
        if ($input->user_id === "0" && $isExist === null) {
            $this->parent_id = $userRoleName == "vendor"?Auth::user()->id:0;
            $this->first_name = $input->first_name;
            $this->last_name = $input->last_name;
            $this->email = $input->email;
            $this->mobile = $input->mobile_no;
            $this->password = bcrypt($input->email);
            $this->role_id = $input->role_id;
            $this->created_at = date(getConstant('DATETIME_DB_FORMAT'));
            $this->save();
            $message = "User Added Successfully!";
        } else {
            $update['first_name'] = $input->first_name;
            $update['last_name'] = $input->last_name;
            $update['mobile'] = $input->mobile_no;
            $update['role_id'] = $input->role_id;
            $update['updated_at'] = date(getConstant('DATETIME_DB_FORMAT'));
            $this->where('id', $isExist->id)->update($update);
            $message = "User Updated Successfully!";
        }

        return array(
            'status' => TRUE,
            'message' => $message
        );
    }

}
