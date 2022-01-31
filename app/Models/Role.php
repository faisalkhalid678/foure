<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Role_right;
use DB;

class Role extends Model {

    public $timestamps = false;

    use HasFactory;

    public function getRoles($id, $role_code) {
        $where['status'] = getConstant('STATUS_ACTIVE');
        if ($role_code !== "") {
            $where['role_code'] = $role_code;
        }
        if ($id !== "" && $id !== null) {
            unset($where['role_code']);
            $where['id'] = $id;
        }

        $roleWithRights = array();
        if ($id !== "" || $role_code !== "") {
            $rolesData = $this->where($where)->first();
            if ($rolesData && $rolesData['id']) {
                $roleRights = $this->getRoleRightsById($rolesData['id']);
                $rolesData['rights'] = $roleRights;
            }
            $roleWithRights = $rolesData;
        } else {
            $rolesData = $this->where($where)->get()->toArray();
            $rolesArray = array();
            if (!empty($rolesData)) {
                foreach ($rolesData as $key => $role) {
                    if ($role['id']) {
                        $roleRights = $this->getRoleRightsById($role['id']);
                        $role['rights'] = $roleRights;
                    }
                    $rolesArray[$key] = $role;
                }
            }
            $roleWithRights = $rolesArray;
        }
        return $roleWithRights;
    }

    public function getRoleRightsById($roleId) {
        $roleRights = DB::table('role_rights')
                        ->select('rights.id as right_id', 'rights.right_name', 'rights.right_code')
                        ->join('rights', 'role_rights.right_id', '=', 'rights.id')
                        ->where(['role_rights.role_id' => $roleId])
                        ->get()->toArray();

        return $roleRights;
    }
    
    public function addRoles($input){
        $isExist = $this->getRoles($input->role_id, $input->role_code);
        if ($isExist === null && $input->role_id !== "" && $input->role_id !== null && $input->role_id === 0) {
            $this->role_name = $input->role_name;
            $this->role_code = $input->role_code;
            $this->date_created = date(getConstant('DATETIME_DB_FORMAT'));
            $this->save();
            $roleId = $this->id;
            $roleRight = new Role_right();
            $roleRight->addRoleRights($roleId, $input->rights);
            $message = "Roles Added Successfully!";
        } else {
            $update['role_name'] = $input->role_name;
            $update['role_code'] = $input->role_code;
            $this->where('id',$isExist->id)->update($update);
            $roleRight = new Role_right();
            $roleRight->addRoleRights($isExist->id, $input->rights);
            $message = "Roles Updated Successfully!";
        }
        
        return array(
            'status' => TRUE,
            'message' => $message
        );
    }

}
