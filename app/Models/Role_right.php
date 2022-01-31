<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class Role_right extends Model {

    public $timestamps = false;

    use HasFactory;

    public function addRoleRights($roleId, $rights) {
        if ($roleId !== "" & $roleId !== null && $roleId > 0 && !empty($rights)) {
            $this->where('role_id', $roleId)->delete();
            foreach ($rights as $right) {
                DB::table('role_rights')->insert([
                    'role_id' => $roleId,
                    'right_id' => $right
                ]);
            }
        }
        return True;
    }

}
