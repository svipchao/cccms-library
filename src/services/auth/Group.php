<?php
declare (strict_types=1);

namespace cccms\services\auth;

use cccms\model\SysGroup;
use cccms\extend\ArrExtend;

/**
 * 组织操作
 */
trait Group
{
    /**
     * 获取全部组织
     */
    public function getAllGroups(bool $isId = false): array
    {
        $groups = SysGroup::mk()->getGroups();
        if ($isId) return array_keys($groups);
        return $groups;
    }

    /**
     * 获取组织子集组织(包含自身)
     * @param array $groupIds 组织ID集合
     * @param bool $isId 是否返回ID
     * @return array
     */
    public function getGroupChildren(array $groupIds = [], bool $isId = false): array
    {
        $groups = ArrExtend::toChildren($this->getAllGroups(), $groupIds, true, 'id', 'group_id');
        return $isId ? array_column($groups, 'id') : $groups;
    }

    /**
     * 获取组织拥有的角色
     * @param int $group_id 组织ID
     * @param bool $isId 是否返回ID
     * @return array
     */
    public function getGroupRoles(int $group_id = 0, bool $isId = false): array
    {
        $allRoles = $this->getAllRoles();
        if (empty($group_id)) return $allRoles;
        $group = SysGroup::mk()->with('roles')->where('status', 1)->findOrEmpty($group_id)->toArray();
        if ($isId) return array_column($group['roles'], 'id');
        return $group['roles'];
    }

    /**
     * 过滤不属于组织的角色
     * @param int $group_id
     * @param $roleIds
     * @return array
     */
    public function filterGroupRoles(int $group_id, $roleIds): array
    {
        if (is_string($roleIds)) {
            $roleIds = ArrExtend::toOneUnique(explode(',', $roleIds));
        } elseif (!is_array($roleIds)) {
            return [];
        }
        // 与父级角色权限取交集
        return array_intersect($roleIds, $this->getGroupRoles($group_id, true));
    }
}