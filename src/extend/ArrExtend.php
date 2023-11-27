<?php
declare(strict_types=1);

namespace cccms\extend;

class ArrExtend
{
    /**
     * 给定 一维数组 键 生成二维数组
     * @param array $array 数组
     * @param string $key 键
     * @return array
     */
    public static function createTwoArray(array $array, string $key): array
    {
        return array_map(function ($item) use ($key) {
            return [$key => $item];
        }, $array);
    }

    /**
     * 二维数组根据某个字段排序
     * @param array $array 待处理数据
     * @param string $field 字段
     * @param string|int $sort 排序 SORT_ASC 升序|SORT_DESC 降序
     * @return array
     */
    public static function toSort(array $array = [], string $field = '', string $sort = 'desc'): array
    {
        $sort = ['desc' => SORT_DESC, 'asc' => SORT_ASC][$sort] ?? SORT_DESC;
        if (empty($field)) return $array;
        $list = array_column($array, $field);
        array_multisort($list, $sort, $array);
        return $array;
    }

    /**
     * 一维数组去重
     * @param array $array 待处理数据
     * @param bool $delEmpty 是否去除空数组元素
     * @return array
     */
    public static function toOneUnique(array $array = [], bool $delEmpty = true): array
    {
        if ($delEmpty) {
            return array_filter(array_keys(array_flip($array)));
        } else {
            return array_keys(array_flip($array));
        }
    }

    /**
     * 二维数组去重
     * @param array $array 待处理数据
     * @param string $field 字段
     * @param bool $delIndexes 是否删除索引
     * @return array
     */
    public static function toTwoUnique(array $array = [], string $field = '', bool $delIndexes = true): array
    {
        if (empty($array) || empty($field)) return $array;
        $list = array_column($array, null, $field);
        return $delIndexes ? array_values($list) : $list;
    }

    /**
     * 以一维数组返回数据树
     * @param array $array
     * @param string $currentKey 当前主键
     * @param string $parentKey 父级主键
     * @param string $children 子级字段
     * @param string $mark 记号
     * @param int $level 级别
     * @param bool $isTreeArray 是否遍历树 防止多次调用toTreeArray()
     * @return array
     */
    public static function toTreeList(array $array = [], string $currentKey = 'id', string $parentKey = 'pid', string $children = 'children', string $mark = '├　', int $level = 0, bool $isTreeArray = true): array
    {
        if ($isTreeArray) {
            $array = self::toTreeArray($array, $currentKey, $parentKey, $children);
        }
        $list = [];
        foreach ($array as &$val) {
            $son = $val[$children] ?? [];
            unset($val[$children]);
            $val['mark'] = str_repeat($mark, $level);
            $list[] = $val;
            if ($son) $list = array_merge($list, self::toTreeList($son, $currentKey, $parentKey, $children, $mark, $level + 1, false));
        }
        return $list;
    }

    /**
     * 以多维数组返回数据树
     * @param array $array
     * @param string $currentKey 当前主键
     * @param string $parentKey 父级主键
     * @param string $children 子级字段
     * @return array
     */
    public static function toTreeArray(array $array = [], string $currentKey = 'id', string $parentKey = 'pid', string $children = 'children'): array
    {
        $tmp = self::toTwoUnique($array, $currentKey, false);
        $tree = [];
        foreach ($array as $value) {
            if (isset($value[$parentKey]) && isset($tmp[$value[$parentKey]])) {
                $tmp[$value[$parentKey]][$children][] = &$tmp[$value[$currentKey]];
            } else {
                $tree[] = &$tmp[$value[$currentKey]];
            }
        }
        return $tree;
    }

    /**
     * 读取指定节点的所有孩子节点
     * @param array $array
     * @param mixed $value 当前主键值
     * @param bool $withSelf 是否包含自身
     * @param string $currentKey 当前主键
     * @param string $parentKey 父级主键
     * @return array
     */
    public static function toChildren(array $array = [], $value = null, bool $withSelf = false, string $currentKey = 'id', string $parentKey = 'pid'): array
    {
        $arr = [];
        if (!is_array($value)) $value = [$value];
        foreach ($array as $val) {
            if (!isset($val[$currentKey])) continue;
            if (in_array($val[$parentKey], $value)) {
                $arr[] = $val;
                $arr = array_merge($arr, self::toChildren($array, $val[$currentKey], $withSelf, $currentKey, $parentKey));
            } elseif ($withSelf && in_array($val[$currentKey], $value)) {
                $arr[] = $val;
            }
        }
        return array_unique($arr, SORT_REGULAR);
    }

    /**
     * 读取指定节点的所有孩子节点ID
     * @param array $array
     * @param mixed $value 当前主键值
     * @param bool $withSelf 是否包含自身
     * @param string $currentKey 当前主键
     * @param string $parentKey 父级主键
     * @return array
     */
    public static function toChildrenIds(array $array = [], $value = 0, bool $withSelf = false, string $currentKey = 'id', string $parentKey = 'pid'): array
    {
        $childrenList = self::toChildren($array, $value, $withSelf, $currentKey, $parentKey);
        $childrenIds = [];
        foreach ($childrenList as $v) {
            $childrenIds[$v[$currentKey]] = $v[$currentKey];
        }
        return array_values($childrenIds);
    }
}
