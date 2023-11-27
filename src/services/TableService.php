<?php
declare(strict_types=1);

namespace cccms\services;

use cccms\Service;
use cccms\extend\StrExtend;

class TableService extends Service
{
    /**
     * 获取表字段
     * @param string $table 表名
     * @return array
     */
    public function fields(string $table = ''): array
    {
        // 表名
        $tableName = StrExtend::humpToUnderline($table);
        // 表信息
        $sysData = InitService::instance()->getData();
        $sysDataTableInfo = $sysData[$tableName] ?? [];
        $fields = [];
        // 表信息不存在证明当前表展示所有字段
        if (!empty($sysDataTableInfo)) {
            // 当前用户所拥有的角色ID
            $roleIds = AuthService::instance()->getUserRoles(true);
            // 获取没有权限的字段名
            foreach ($sysDataTableInfo as $key => $val) {
                if (in_array($key, $roleIds)) {
                    // 这是没有权限的字段
                    array_push($fields, ...$val['fields']);
                }
            }
        }
        // 获取当前表所有字段
        $tableInfo = InitService::instance()->getTables()[$tableName] ?? [];
        $tableFields = $tableInfo['fields'] ?: [];
        // 取差集 管理员可以操作任何信息 这里严格意义上来说不能判断是否管理员 待商议
        return AuthService::instance()->isAdmin() ? $tableFields : array_diff_key($tableFields, array_flip($fields));
    }
}