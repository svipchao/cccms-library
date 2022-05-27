<?php
declare (strict_types=1);

namespace cccms\model;

use think\facade\Cache;
use cccms\Model;

class SysTypes extends Model
{
    protected $append = ['type_text'];

    public static function onBeforeWrite($model)
    {
        Cache::delete('SysTypes');
    }

    public function getTypes(int $type = 0, string $key = ''): array
    {
        $data = Cache::get('SysTypes');
        if (empty($data)) {
            $data = $this->column('*', $key);
            Cache::set('SysTypes', $data);
        }
        if (!empty($type)) {
            foreach ($data as $key => $val) {
                if ($type !== $val['type']) unset($data[$key]);
            }
        }
        return $data;
    }

    public function getTypeTextAttr($value, $data): string
    {
        return ['未知', '菜单', '配置', '路由', '附件'][$data['type']] ?? '未知';
    }
}