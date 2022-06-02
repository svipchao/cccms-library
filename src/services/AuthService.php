<?php
declare(strict_types=1);

namespace cccms\services;

use cccms\Service;
use cccms\extend\{ArrExtend, StrExtend};
use app\admin\model\{SysUser, SysRole, SysGroup};

class AuthService extends Service
{
    protected array $userInfo = [];

    public function initialize(): void
    {
        $this->userInfo = $this->setUserInfo();
    }

    /**
     * 获取用户信息
     * @param array $condition
     * @return array
     */
    public function setUserInfo(array $condition = []): array
    {
        if (empty($condition) && empty(_getAccessToken('id'))) return [];
        $condition = $condition ?: ['id' => _getAccessToken('id')];
        if (isset($condition['id']) && $condition['id'] == 1) {
            $userInfo = SysUser::mk()->_read(1);
        } else {
            $userInfo = SysUser::mk()->with(['loginGroups.loginRoles.loginNodes'])->_read($condition);
        }
        if (empty($userInfo)) {
            _result(['code' => 401, 'msg' => '账号不存在'], _getEnCode());
        }
        if (!$userInfo['status']) {
            _result(['code' => 401, 'msg' => '账号已被禁用'], _getEnCode());
        }
        $userInfo = array_merge(['groups' => [], 'roles' => [], 'nodes' => []], $userInfo);
        if ($userInfo['id'] == 1) {
            $userInfo['groups'] = SysGroup::mk()->field('id,group_id,group_name,group_desc')->_list();
            $userInfo['roles'] = SysRole::mk()->field('id,role_id,role_name,role_desc')->_list();
            $userInfo['nodes'] = NodeService::instance()->getNodes();
        } else {
            foreach ($userInfo['loginGroups'] as &$group) {
                foreach ($group['loginRoles'] as &$role) {
                    $userInfo['nodes'] = array_merge($userInfo['nodes'] ?? [], array_column($role['loginNodes'], 'node'));
                    unset($role['loginNodes'], $role['status'], $role['create_time'], $role['update_time']);
                }
                $userInfo['roles'] = array_merge($userInfo['roles'] ?? [], $group['loginRoles']);
                unset($group['loginRoles'], $group['status'], $group['create_time'], $group['update_time']);
            }
            $userInfo['groups'] = $userInfo['loginGroups'];
            unset($userInfo['loginGroups']);
        }
        return $userInfo;
    }

    /**
     * 获取用户信息
     * @param string $key
     * @return mixed
     */
    public function getUserInfo(string $key = '')
    {
        return $this->userInfo[$key] ?? $this->userInfo;
    }

    /**
     * 获取用户角色
     * @param bool $isId
     * @param bool $isTree
     * @return array
     */
    public function getUserRoles(bool $isId = false, bool $isTree = false): array
    {
        $roles = $this->getAllRoles();
        if (!$this->isAdmin()) {
            $role_ids = array_column($this->getUserInfo('roles'), 'id');
            $roles = ArrExtend::toChildren($roles, $role_ids, true, 'id', 'role_id');
        }
        if ($isId) {
            return array_column($roles, 'id');
        }
        if ($isTree) {
            return ArrExtend::toTreeArray($roles, 'id', 'role_id');
        } else {
            return ArrExtend::toTreeList($roles, 'id', 'role_id');
        }
    }

    /**
     * 获取角色子集角色
     * @param int $role_id 角色ID
     * @param bool $withSelf 是否包含自身
     * @param bool $isId 是否返回ID
     * @return array
     */
    public function getRoleChildren(int $role_id = 0, bool $withSelf = true, bool $isId = true): array
    {
        $roles = ArrExtend::toChildren($this->getAllRoles(), $role_id, $withSelf, 'id', 'role_id');
        return $isId ? array_column($roles, 'id') : $roles;
    }

    /**
     * 获取用户组织
     * @param bool $isId
     * @param bool $isTree
     * @return array
     */
    public function getUserGroups(bool $isId = false, bool $isTree = false): array
    {
        $groups = $this->getAllGroups();
        if (!$this->isAdmin()) {
            $group_ids = array_column($this->getUserInfo('groups'), 'id');
            $groups = ArrExtend::toChildren($groups, $group_ids, true, 'id', 'group_id');
        }
        if ($isId) {
            return array_column($groups, 'id');
        }
        if ($isTree) {
            return ArrExtend::toTreeArray($groups, 'id', 'group_id');
        } else {
            return ArrExtend::toTreeList($groups, 'id', 'group_id');
        }
    }

    /**
     * 获取组织子集组织
     * @param int $group_id 组织ID
     * @param bool $withSelf 是否包含自身
     * @param bool $isId 是否返回ID
     * @return array
     */
    public function getGroupChildren(int $group_id = 0, bool $withSelf = true, bool $isId = true): array
    {
        $groups = ArrExtend::toChildren($this->getAllGroups(), $group_id, $withSelf, 'id', 'group_id');
        return $isId ? array_column($groups, 'id') : $groups;
    }

    /**
     * 获取用户权限节点
     *
     */
    public function getUserNodes()
    {
        return $this->getUserInfo('nodes');
    }

    /**
     * 判断是否是管理员
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->userInfo['id'] == 1;
    }

    /**
     * 判断是否有权限
     * @param string $node 权限节点
     * @return bool
     */
    public function isAuth(string $node = ''): bool
    {
        return in_array($node, $this->getUserInfo('nodes') ?: []);
    }

    /**
     * 获取全部组织
     */
    public function getAllGroups(): array
    {
        return SysGroup::mk()->field('id,group_id,group_name,group_desc')->cache(600)->_list();
    }

    /**
     * 获取全部角色
     */
    public function getAllRoles(): array
    {
        return SysRole::mk()->field('id,role_id,role_name,role_desc')->cache(600)->_list();
    }

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