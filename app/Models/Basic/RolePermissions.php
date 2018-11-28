<?php

namespace App\Models\Basic;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RolePermissions extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'sl_role_permissions';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = false;

    /*
     *  主键ID
     */
    protected $primaryKey = 'id';

    public static function getIns() {
        static $ins;

        if (empty($ins) || !($ins instanceof self)) {
            $ins = new self();
        }
        return $ins;
    }

    public function saveRolePermission($permid_data,$roleid,$admin_id){

        if (!$roleid) {
            return FALSE;
        }

        //先删除表中的角色对应的记录
        $del_result = $this::where('roleid',$roleid)->delete();
        $time = date('Y-m-d H:i:s',time());

        if (!empty($permid_data)) {
            foreach ($permid_data as $permid) {
                $data[] = [
                    'p_id'=>$permid,
                    'roleid'=>$roleid,
                    'created_by'=>$admin_id,
                    'updated_by'=>$admin_id,
                    'created_time'=>$time,
                    'updated_time'=>$time,
                ];
            }

            //写入现在的记录
            $insert_result = DB::table('sl_role_permissions')->insert($data);

            if ($del_result === FALSE || $insert_result === FALSE) {
                return FALSE;
            }

            return TRUE;
        }

    }


}
