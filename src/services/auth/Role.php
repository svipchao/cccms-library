<?php

declare(strict_types=1);

namespace cccms\services\auth;

use cccms\extend\ArrExtend;
use cccms\services\NodeService;
use app\admin\model\SysRole;

/**
 * 角色操作
 */
trait Role
{
    /**
     * 获取全部角色
     */
    public function getAllRoles(): array
    {
        return SysRole::mk()->field('id,role_id,role_name,role_desc')->cache(600)->_list();
    }

    /**
     * 获取角色子集角色(包含自身)
     * @param array $roleIds 角色ID集合
     * @param bool $isId 是否返回ID
     * @return array
     */
    public function getRoleChildren(array $roleIds = [], bool $isId = false): array
    {
        $roles = ArrExtend::toChildren($this->getAllRoles(), $roleIds, true, 'id', 'role_id');
        return $isId ? array_column($roles, 'id') : $roles;
    }

    /**
     * 获取角色权限节点
     * @param int $role_id 角色ID
     * @return array
     */
    public function getRoleNodes(int $role_id = 0): array
    {
        $allNodes = NodeService::instance()->getNodes();
        if (empty($role_id)) return $allNodes;
        $role = SysRole::mk()->with('nodes')->where('status', 1)->findOrEmpty($role_id)->toArray();
        return array_column($role['nodes'] ?? [], 'node');
    }

    /**
     * 过滤不属于角色的权限节点
     * @param int $role_id 角色ID
     * @param mixed $nodes 权限节点
     * @return array
     */
    public function filterRoleNodes(int $role_id, $nodes): array
    {
        if (is_string($nodes)) {
            $nodes = ArrExtend::toOneUnique(explode(',', $nodes));
        } elseif (!is_array($nodes)) {
            return [];
        }
        // 与父级角色权限取交集
        return array_intersect(array_map('strtolower', $nodes), $this->getRoleNodes($role_id));
    }
}
