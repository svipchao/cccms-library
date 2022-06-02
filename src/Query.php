<?php
declare(strict_types=1);

namespace cccms;

use cccms\extend\StrExtend;
use think\db\exception\{DbException, DataNotFoundException, ModelNotFoundException};

class Query extends \think\db\Query
{
    /**
     * 查找数据
     * @param mixed $data
     * @param callable|null $callable 回调
     * @return mixed
     * @throws DbException
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     */
    public function _read($data = null, ?callable $callable = null)
    {
        if (is_string($data) || is_numeric($data)) {
            $data = $this->allowEmpty()->find($data);
        } elseif (is_array($data)) {
            $data = $this->where($data)->allowEmpty()->find();
        } else {
            return [];
        }
        if ($data->isEmpty()) return [];
        if (is_callable($callable)) {
            $data = call_user_func($callable, $data);
        } else {
            $data = $data->toArray();
        }
        return $data;
    }

    /**
     * 数组
     * @param mixed $where
     * @param callable|null $callable 回调
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function _list($where = null, ?callable $callable = null): array
    {
        $data = $this->where($where)->select()->toArray();
        if (is_callable($callable)) {
            $data = array_map($callable, $data);
        }
        return $data;
    }

    /**
     * 分页查询
     *    PS:withCache 与分页查询冲突 请不要一起使用
     * @param null $listRows 每页数量 数组表示配置参数
     * @param int|bool $simple 是否简洁模式或者总记录数
     * @param callable|null $callable 回调
     * @return array
     * @throws DbException
     */
    public function _page($listRows = null, $simple = false, ?callable $callable = null): array
    {
        $data = $this->paginate([
            'list_rows' => $listRows['limit'] ?? 15,
            'page' => $listRows['page'] ?? 1,
        ], $simple)->toArray();
        if (is_callable($callable)) {
            $data['data'] = array_map($callable, $data['data']);
        }
        return $data;
    }

    /**
     * 快捷删除逻辑器
     * @param mixed $condition
     * @return bool
     * @throws DbException
     */
    public function _delete($condition): bool
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
        return (bool)(empty($data) ? $query->delete() : $query->update($data));
    }
}