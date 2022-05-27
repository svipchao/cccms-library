<?php
declare(strict_types=1);

namespace cccms\services;

use cccms\Service;
use cccms\extend\ArrExtend;

/**
 * 无限极权限服务类
 */
class UnlimitService extends Service
{
    /**
     * 创建数据
     * 给一个数组 判断主键是否存在数组中
     * PS 这样不能添加父级ID为0的数据 待解决
     * @param array $data 添加的数据
     * @param array $array 筛选的数据
     * @param string $currentKey 当前主键
     * @param string $parentKey 父级主键
     * @return bool
     */
    public function isCreate(array $data, array $array, string $currentKey, string $parentKey): bool
    {
        return in_array($data[$parentKey], array_column($array, $currentKey));
    }

    /**
     * 删除数据
     *     判断数据是否存在数组中 判断是否有子集
     *     是否能删除 true 是|false 否
     * @param array|string $data 删除的数据
     * @param array $array 筛选的数据
     * @param string $currentKey 当前主键
     * @param string $parentKey 父级主键
     * @return bool
     */
    public function isDelete($data, array $array, string $currentKey, string $parentKey): bool
    {
        // 判断是否数组
        $data = $data[$currentKey] ?? $data;
        // 判断数据是否存在数组
        if (in_array($data, array_column($array, $currentKey))) {
            // 判断是否存在子集 存在子集 不能删除 返回false
            return !ArrExtend::toChildrenIds($array, $data, false, $currentKey, $parentKey);
        } else {
            // 数据不存在 不能删除
            return false;
        }
    }

    /**
     * 修改数据
     * 判断数据是否存在数组中 判断是否为子集
     *     是否能更新 true 是|false 否
     * @param array $data 修改的数据
     * @param array $array 筛选的数据
     * @param string $currentKey 当前主键
     * @param string $parentKey 父级主键
     * @return bool
     */
    public function isUpdate(array $data, array $array, string $currentKey, string $parentKey): bool
    {
        // 判断数据是否存在数组
        if (in_array($data[$currentKey], array_column($array, $currentKey))) {
            // 判断是否存在子集
            $ids = ArrExtend::toChildrenIds($array, $data[$currentKey], false, $currentKey, $parentKey);
            // 判断修改的父级主键是否为子集数据 存在子集 不能更新 返回false
            return !in_array($data[$parentKey], $ids);
        } else {
            return false;
        }
    }

    /**
     * 查找数据
     * 判断查找的数据ID是否存在数组中
     * @param array|string $data 查找的数据
     * @param array $array 筛选的数据
     * @param string $currentKey 当前主键
     * @return bool
     */
    public function isSelect($data, array $array, string $currentKey): bool
    {
        // 判断是否数组
        $data = $data[$currentKey] ?? $data;
        return in_array($data, array_column($array, $currentKey));
    }
}
