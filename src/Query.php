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
     * @param bool $isArray
     * @return mixed
     */
    public function _read($data = null, bool $isArray = true)
    {
        if ($isArray) {
            return $this->findOrEmpty($data)->toArray();
        } else {
            return $this->findOrEmpty($data);
        }
    }

    /**
     * 数组
     * @param array $where
     * @return array
     */
    public function _list(array $where = []): array
    {
        try {
            return $this->where($where)->select()->toArray();
        } catch (DbException $e) {
            return [];
        }
    }

    /**
     * 分页查询
     *    PS:withCache 与分页查询冲突 请不要一起使用
     * @param null $listRows 每页数量 数组表示配置参数
     * @param int|bool $simple 是否简洁模式或者总记录数
     * @param bool $isArray
     * @return array|Paginator
     */
    public function _page($listRows = null, $simple = false, bool $isArray = true)
    {
        try {
            $res = $this->paginate([
                'list_rows' => $listRows['limit'] ?? 15,
                'page' => $listRows['page'] ?? 1,
            ], $simple);
            return $isArray ? $res->toArray() : $res;
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