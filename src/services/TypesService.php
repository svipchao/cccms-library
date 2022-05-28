<?php
declare(strict_types=1);

namespace cccms\services;

use cccms\Service;
use app\admin\model\SysTypes;

class TypesService extends Service
{
    public function getTypes(int $type = 0, string $column = ''): array
    {
        if ($type) {
            $types = SysTypes::mk()->where('type', $type)->field('id,type,name,alias')->cache(600)->_list();
        } else {
            $types = SysTypes::mk()->field('id,type,name,alias')->cache(600)->_list();
        }
        if ($column) {
            $types = array_column($types, null, $column);
        }
        return $types;
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