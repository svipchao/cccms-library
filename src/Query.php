<?php
declare(strict_types=1);

namespace cccms;

use cccms\extend\StrExtend;
use think\db\exception\DbException;

class Query extends \think\db\Query
{
    public function _withSearch($fields, array $data = [], string $prefix = '', $value = null): Query
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }
        foreach ($fields as $key => $field) {
            if (array_key_exists($field, $data) && $data[$field] === $value) {
                unset($fields[$key], $data[$field]);
            }
        }
        return parent::withSearch($fields, $data, $prefix);
    }

    /**
     * 查找数据
     * @param mixed $data
     * @param callable|null $callable 回调
     * @return mixed
     */
    public function _read($data = null, ?callable $callable = null)
    {
//        try {
            if (is_string($data) || is_numeric($data)) {
                $data = $this->allowEmpty()->find($data);
            } elseif (is_array($data)) {
                $data = $this->where($data)->allowEmpty()->find();
            } else {
                $data = $this->allowEmpty()->find();
            }
            if (is_callable($callable)) {
                $data = call_user_func($callable, $data);
            } else {
                $data = $data->toArray();
            }
            return $data;
//        } catch (DbException $e) {
//            _result(['code' => 403, 'msg' => '查询失败'], _getEnCode());
//        }
    }

    /**
     * 数组
     * @param mixed $where
     * @param callable|null $callable 回调
     * @return array
     */
    public function _list($where = null, ?callable $callable = null): array
    {
//        try {
            $data = $this->where($where)->select();
            if (is_callable($callable)) {
                return call_user_func($callable, $data);
            } else {
                return $data->toArray();
            }
//        } catch (DbException $e) {
//            _result(['code' => 403, 'msg' => '查询失败'], _getEnCode());
//        }
    }

    /**
     * 分页查询
     *    PS:withCache 与分页查询冲突 请不要一起使用
     * @param null $listRows 每页数量 数组表示配置参数
     * @param int|bool $simple 是否简洁模式或者总记录数
     * @param callable|null $callable 回调
     * @return array
     */
    public function _page($listRows = null, $simple = false, ?callable $callable = null): array
    {
//        try {
            $data = $this->paginate([
                'list_rows' => $listRows['limit'] ?? 15,
                'page' => $listRows['page'] ?? 1,
            ], $simple);
            if (is_callable($callable)) {
                return call_user_func($callable, $data);
            } else {
                return $data->toArray();
            }
//        } catch (DbException $e) {
//            _result(['code' => 403, 'msg' => '查询分页失败'], _getEnCode());
//        }
    }

    /**
     * 快捷删除逻辑器
     * @param mixed $condition
     * @param callable|null $callable 回调
     * @return bool
     */
    public function _delete($condition, ?callable $callable = null): bool
    {
        // 查询限制处理
        if (is_array($condition)) {
            $query = $this->where($condition);
        } else {
            $query = $this->whereIn('id', StrExtend::str2arr((string)$condition));
        }
        // 阻止危险操作
        if (!$query->getOptions('where')) {
            _result(['code' => 200, 'msg' => '数据删除失败, 请稍候再试！'], _getEnCode());
        }
        $query = $query->findOrEmpty();
        if ($query->isEmpty()) return false;
        // 组装执行数据
        $data = [];
        if (method_exists($query, 'getTableFields')) {
            $fields = $query->getTableFields();
            // 软删除
            if (in_array('delete_time', $fields)) $data['delete_time'] = time();
        }
        if (is_callable($callable)) {
            $query = call_user_func($callable, $query);
        }
        try {
            return (bool)(empty($data) ? $query->delete() : $query->update($data));
        } catch (DbException $e) {
            _result(['code' => 200, 'msg' => '数据删除失败, 请稍候再试！'], _getEnCode());
        }
    }
}