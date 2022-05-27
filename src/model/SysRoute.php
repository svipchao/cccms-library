<?php
declare (strict_types=1);

namespace cccms\model;

use think\facade\Cache;
use think\model\relation\HasOne;
use cccms\Model;

class SysRoute extends Model
{
    public static function onBeforeWrite($model)
    {
        Cache::delete('SysRoutes');
    }

    public function getRoutes(): array
    {
        $data = Cache::get('SysRoutes');
        if (empty($data)) {
            $data = $this->column('*', 'id');
            Cache::set('SysRoutes', $data);
        }
        return $data;
    }

    public function type(): HasOne
    {
        return $this->hasOne(SysTypes::class, 'id', 'type_id')->bind(['type_name' => 'name']);
    }
}