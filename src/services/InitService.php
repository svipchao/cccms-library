<?php
declare(strict_types=1);

namespace cccms\services;

use think\facade\Db;
use cccms\Service;

class InitService extends Service
{
    public function getConfigs()
    {
        $data = $this->app->cache->get('SysConfigs');
        if ($data === null) {
            $data = [];
            if (empty(Db::query('show tables like "sys_config"'))) return true;
            $configRes = Db::table('sys_config')->alias('c')
                ->field('c.name,c.value,c.configure,t.alias')
                ->join('sys_types t', 'c.type_id = t.id and t.type = 2')
                ->select()->toArray();
            $configs = [];
            // 根据类别分组
            foreach ($configRes as $val) {
                $configs[$val['alias']][] = $val;
            }
            // 组合配置文件格式
            foreach ($configs as $key => $val) {
                $items = [];
                foreach ($val as $v1) {
                    // 判断是否需要二次分割
                    if (strstr($v1['value'], '|')) {
                        $item = array_filter(explode('|', $v1['value']));
                        foreach ($item as $v2) {
                            $v2 = array_filter(explode(',', $v2));
                            $items[$v1['name']][$v2[0]] = $v2[1];
                        }
                    } elseif (strstr($v1['value'], ',')) {
                        $items[$v1['name']] = array_filter(explode(',', $v1['value']));
                    } else {
                        $items[$v1['name']] = $v1['value'];
                    }
                }
                $data[$key] = $items;
            }
            $this->app->cache->set('SysConfigs', $data);
        }
        return $data;
    }

    // 表信息
    public function getTables()
    {
        $data = $this->app->cache->get('Tables');
        if ($data === null) {
            $data = [];
            $tables = Db::query('SHOW TABLE STATUS');
            foreach ($tables as $table) {
                $fields = Db::getFields($table['Name']);
                foreach ($fields as $key => $val) {
                    $rule = [];
                    if ($val['notnull']) $rule[] = 'require';
                    // 移除用户自定义注释内容
                    $val['comment'] = preg_replace("/(?=【).*?(?<=】)/", '', $val['comment']);
                    // 没有字段备注的时候使用字段名
                    $val['comment'] = $val['comment'] ?: $val['name'];
                    // 判断类型 没有括号的时候 返回当前类型
                    if (strstr($val['type'], 'int')) $rule[] = 'number';
                    // 设置长度
                    preg_match("/(?<=\().*?(?=\))/", $val['type'], $length);
                    if (!empty($length)) $rule[] = 'length:1,' . (int)$length[0];
                    // 读取扩展验证
                    preg_match("/(?<=\().*?(?=\))/", $val['comment'], $comment);
                    if (!empty($comment)) {
                        // 判断生成的验证是否和用户自定义规则冲突
                        if (strstr($comment[0], 'length')) unset($rule['length']);
                        $comment = explode('|', $comment[0]);
                        // 字段有默认值 所以验证规则可为空
                        if (in_array('noRequire', $comment)) {
                            unset($rule[array_search('require', $rule)]);
                            unset($comment[array_search('noRequire', $comment)]);
                        }
                        $rule = array_merge($comment, $rule);
                    }
                    // 给字段设置中文名
                    $val['comment_new'] = strstr($val['comment'], '(', true) ?: $val['comment'];
                    // 设置映射字段
                    $data[$table['Name']]['fields'][$key] = $val['comment_new'];
                    $data[$table['Name']]['ruleAlias'][$key] = $key . '|' . $val['comment_new'];
                    // 规则为空则不设置规则
                    if (empty($rule)) continue;
                    $data[$table['Name']]['rules'][$key . '|' . $val['comment_new']] = implode('|', $rule);
                }
                $data[$table['Name']]['table'] = $table['Name'];
                $data[$table['Name']]['table_name'] = $table['Comment'];
            }
            $this->app->cache->set('Tables', $data);
        }
        return $data;
    }

    // 生成字段条件 && 数据条件
    public function getData()
    {
        $data = $this->app->cache->get('SysData');
        if ($data === null) {
            $data = [];
            $res = Db::table('sys_data')->select()->toArray();
            foreach ($res as $val) {
                if (empty($val['field']) || empty($val['table'])) continue;
                // 添加角色字段权限信息
                $data[$val['table']][$val['role_id']]['fields'][$val['field']] = $val['field'];
                // 添加角色字段条件信息
                if (!empty($val['where']) && !empty($val['value'])) {
                    // 通过索引添加唯一条件
                    $data[$val['table']][$val['role_id']]['wheres'][$val['field'] . $val['where'] . $val['value']] = [
                        $val['field'],
                        $val['where'],
                        $val['value']
                    ];
                }
            }
            // 处理索引
            foreach ($data as &$val) {
                foreach ($val as &$v) {
                    $v['fields'] = array_values($v['fields'] ?? []);
                    $v['wheres'] = array_values($v['wheres'] ?? []);
                }
            }
            $this->app->cache->set('SysData', $data);
        }
        return $data;
    }
}
