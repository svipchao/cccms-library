<?php
declare (strict_types=1);

namespace cccms\model;

use think\facade\Cache;
use think\model\relation\HasMany;
use cccms\Model;

class SysRole extends Model
{
    protected $hidden = ['pivot'];

    public static function onBeforeWrite($model)
    {
        Cache::delete('SysRoles');
    }

    public function getRoles(): array
    {
        $data = Cache::get('SysRoles');
        if (empty($data)) {
            $data = $this->column('*', 'id');
            Cache::set('SysRoles', $data);
        }
        return $data;
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(SysRoleNode::class, 'role_id', 'id');
    }
}