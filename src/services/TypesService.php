<?php
declare (strict_types=1);

namespace cccms\services;

use cccms\Service;
use cccms\model\SysTypes;

class TypesService extends Service
{
    /**
     * 判断类别是否正确
     * @param int|string $id 类别ID
     * @param int|string $type 分类标识 1:菜单｜2:配置
     */
    public function isType(int $id, int $type)
    {
        if (empty(SysTypes::mk()->where('type', $type)->_read($id))) {
            _result(['code' => 403, 'msg' => '请选择正确类别'], _getEnCode());
        }
    }

    public function getTypesAndWheres(int $type = 0, int $type_id = 0): array
    {
        $types = SysTypes::mk()->where('type', $type)->field('id,name,type')->_list();
        if ($type_id) {
            $wheres = ['type_id' => $type_id];
        } elseif (isset($types[0], $types[0]['id'])) {
            $wheres = ['type_id' => $types[0]['id']];
        } else {
            $wheres = [];
        }
        return [$types, $wheres];
    }
}