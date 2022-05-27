<?php
declare (strict_types=1);

namespace cccms\services\auth;

use cccms\extend\StrExtend;
use cccms\services\InitService;

/**
 * 数据权限操作
 */
trait Data
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
            $roleIds = $this->getUserRoles(true);
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
        $res = $this->isAdmin() ? $tableFields : array_diff_key($tableFields, array_flip($fields));
        // 字段为键 字段名为值
        foreach ($res as $key => &$val) {
            $val = explode('|', $val)[1] ?? $key;
        }
        return $res;
    }

    /**
     * 验证权限
     * @param array $params 参数
     * @return bool
     */
    public function validateAuth(array $params = []): bool
    {
        // 判断角色权限
        if (!in_array($params['role_id'], $this->getUserRoles(true))) {
            _result(['code' => 403, 'msg' => '权限不足'], _getEnCode());
        }
        // 判断字段权限
        // PS:这里只判断到字段权限，如果数据权限需要限制请自行开发
        $fields = $this->fields($params['table']);
        if (!isset($fields[$params['field']])) {
            _result(['code' => 403, 'msg' => '字段权限不存在'], _getEnCode());
        }
        return true;
    }
}