<?php
declare (strict_types=1);

namespace cccms\model;

use think\facade\Cache;
use think\model\relation\HasOne;
use cccms\Model;

class SysMenu extends Model
{
    public static function onBeforeWrite($model)
    {
        Cache::delete('SysMenus');
    }

    public function getMenus(): array
    {
        $data = Cache::get('SysMenus');
        if (empty($data)) {
            $data = $this->column('*', 'id');
            Cache::set('SysMenus', $data);
        }
        return $data;
    }

    public function type(): HasOne
    {
        return $this->hasOne(SysTypes::class, 'id', 'type_id')->bind(['type_name' => 'name']);
    }
}