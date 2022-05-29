<?php
declare(strict_types=1);

namespace cccms;

use think\Paginator;
use think\db\exception\DbException;
use cccms\extend\StrExtend;

class Query extends \think\db\Query
{
    /**
     * 查找数据
     * @param mixed $data
     * @param callable|null $callable 回调
     * @return mixed
     */
    public function _read($data = null, ?callable $callable = null)
    {
        try {
            $data = $this->model->find($data);
            if (is_callable($callable)) {
                call_user_func($callable, $data);
            } else {
                $data = $data->toArray();
            }
            return $data;
        } catch (DbException $e) {
            return [];
        }
    }

    /**
     * 数组
     * @param mixed $where
     * @param callable|null $callable 回调
     * @return array
     */
    public function _list($where = null, ?callable $callable = null): array
    {
        try {
            return $this->where($where)->select()->each(function ($item) use ($callable) {
                if (is_callable($callable)) {
                    call_user_func($callable, $item);
                }
            })->toArray();
        } catch (DbException $e) {
            return [];
        }
    }

    /**
     * 分页查询
     *    PS:withCache 与分页查询冲突 请不要一起使用
     * @param null $listRows 每页数量 数组表示配置参数
     * @param int|bool $simple 是否简洁模式或者总记录数
     * @param callable|null $callable 回调
     * @return array|Paginator
     */
    public function _page($listRows = null, $simple = false, ?callable $callable = null)
    {
        try {
            $data = $this->paginate([
                'list_rows' => $listRows['limit'] ?? 15,
                'page' => $listRows['page'] ?? 1,
            ], $simple);
            if (is_callable($callable)) {
                call_user_func($callable, $data);
            } else {
                $data = $data->toArray();
            }
            return $data;
        } catch (DbException $e) {
            return [];
        }
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