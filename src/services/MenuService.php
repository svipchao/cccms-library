<?php
declare (strict_types=1);

namespace cccms\services;

use cccms\Service;
use cccms\extend\ArrExtend;
use app\admin\model\{SysTypes, SysMenu};

class MenuService extends Service
{
    /**
     * 根据权限节点生成菜单目录树(所有菜单类别)
     * @param array $nodes 权限节点(键)
     * @return array
     */
    public function getTypesMenus(array $nodes = []): array
    {
        [$types, $menus] = [SysTypes::mk()->getTypes(1, 'id'), SysMenu::mk()->getMenus()];
        foreach ($menus as $menu) {
            if ($menu['status'] === 0) continue;
            if (isset($types[$menu['type_id']])) {
                $types[$menu['type_id']]['menus'][$menu['id']] = $menu;
            }
        }
        foreach ($types as $tKey => &$tVal) {
            $tVal['menus'] = $tVal['menus'] ?? [];
            foreach ($tVal['menus'] as $mKey => &$mVal) {
                if (isset($tVal['menus'][$mVal['menu_id']]) && $mVal['menu_id'] !== 0) $tVal['menus'][$mVal['menu_id']]['url'] = '#';
                if (!empty($mVal['node']) && $mVal['node'] !== '#' && !in_array($mVal['node'], $nodes)) unset($tVal['menus'][$mKey]);
                if ($mVal['url'] !== '/' && !filter_var($mVal['url'], FILTER_VALIDATE_URL)) {
                    // 隐藏后台入口文件
                    $mVal['url'] = ltrim($mVal['url'], '/');
                }
            }
            $pMenuId = array_column($tVal['menus'], 'menu_id');
            foreach ($tVal['menus'] as $k => $v) {
                // 判断是否父级菜单
                if ($v['url'] === '#' && !in_array($v['id'], $pMenuId)) {
                    unset($tVal['menus'][$k]);
                }
            }
            if (empty($tVal['menus'])) {
                unset($types[$tKey]);
            } else {
                $tVal['menus'] = ArrExtend::toTreeArray($tVal['menus'], 'id', 'menu_id');
            }
        }
        return ArrExtend::toSort($types, 'sort');
    }

    /**
     * 获取菜单
     * @param null $type_id 菜单类别
     * @param bool $isTree 是否返回树型结构
     * @return array
     */
    public function getTypeMenus($type_id = null, bool $isTree = false): array
    {
        $menus = SysMenu::mk()->getMenus();
        foreach ($menus as $mKey => &$mVal) {
            if ($type_id && $mVal['type_id'] != $type_id) unset($menus[$mKey]);
        }
        if ($isTree) {
            return ArrExtend::toTreeArray($menus, 'id', 'menu_id');
        } else {
            return ArrExtend::toTreeList($menus, 'id', 'menu_id');
        }
    }
}