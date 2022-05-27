<?php
declare(strict_types=1);

namespace cccms\services\auth;

use cccms\extend\ArrExtend;
use cccms\services\NodeService;
use app\admin\model\{SysUser, SysGroup, SysRole};

/**
 * 用户操作
 */
trait User
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
            $userInfo = SysUser::mk()->with(['groups.roles.nodes'])->where($condition)->_read();
        }
        if (empty($userInfo)) {
            _result(['code' => 401, 'msg' => '账号不存在'], _getEnCode());
        }
        if (!$userInfo['status']) {
            _result(['code' => 401, 'msg' => '账号已被禁用'], _getEnCode());
        }
        $userInfo['admin'] = $userInfo['id'] == 1;
        if ($userInfo['admin']) {
            $userInfo['groups'] = SysGroup::mk()->field('id,group_id,group_name,group_desc')->_list();
            $userInfo['roles'] = SysRole::mk()->field('id,role_id,role_name,role_desc')->_list();
            $userInfo['nodes'] = NodeService::instance()->getNodes();
        } else {
            foreach ($userInfo['groups'] as &$group) {
                foreach ($group['roles'] as &$role) {
                    $userInfo['nodes'] = array_merge($userInfo['nodes'] ?? [], array_column($role['nodes'], 'node'));
                    unset($role['nodes'], $role['status'], $role['create_time'], $role['update_time']);
                }
                $userInfo['roles'] = array_merge($userInfo['roles'] ?? [], $group['roles']);
                unset($group['roles'], $group['status'], $group['create_time'], $group['update_time']);
            }
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
            $roles = ArrExtend::toChildren($roles, $this->getUserInfo('roles'), true, 'id', 'role_id');
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
     * 获取用户组织
     * @param bool $isId
     * @param bool $isTree
     * @return array
     */
    public function getUserGroups(bool $isId = false, bool $isTree = false): array
    {
        $groups = $this->getAllGroups();
        if (!$this->isAdmin()) {
            $groups = ArrExtend::toChildren($groups, $this->getUserInfo('groups'), true, 'id', 'group_id');
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
     * 获取当前用户的权限
     * @return array
     */
    public function getUserNodes(): array
    {
        return $this->getUserInfo('nodes') ?: [];
    }

    /**
     * 过滤不属于当前用户的角色
     * @param mixed $roles 角色ID 数组或字符串(,连接)
     * @return array
     */
    public function filterUserRoles($roles): array
    {
        if (is_string($roles)) {
            $roles = explode(',', $roles);
        }
        // 取交集
        return is_array($roles) ? array_intersect(array_map('strtolower', $roles), $this->getUserRoles(true)) : [];
    }

    /**
     * 过滤不属于当前用户的组织
     * @param mixed $groups 组织ID 数组或字符串(,连接)
     * @return array
     */
    public function filterUserGroups($groups): array
    {
        if (is_string($groups)) {
            $groups = explode(',', $groups);
        }
        // 取交集
        return is_array($groups) ? array_intersect(array_map('strtolower', $groups), $this->getUserGroups(true)) : [];
    }

    /**
     * 判断用户是否拥有角色
     * @param int $role_id
     * @return bool
     */
    public function isUserRole(int $role_id): bool
    {
        if ($this->isAdmin()) return true;
        return in_array($role_id, $this->getUserRoles(true));
    }

    /**
     * 判断用户是否拥有组织
     * @param int $group_id
     * @return bool
     */
    public function isUserGroup(int $group_id): bool
    {
        if ($this->isAdmin()) return true;
        return in_array($group_id, $this->getUserGroups(true));
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
        return in_array($node, $this->getUserNodes());
    }
}
