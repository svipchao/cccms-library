<?php
declare(strict_types=1);

namespace cccms;

use cccms\extend\StrExtend;
use cccms\services\{AuthService, InitService};
use app\admin\model\SysUser;

/**
 * @method Query _withSearch(string|array $fields, array $data = [], string $prefix = '', $value = null) 搜索器
 * @method mixed _read(mixed $data = null, callable|null $callable = null) 查找数据
 * @method array _list(array $where = [], callable|null $callable = null) 数组
 * @method array _page($listRows = null, $simple = false, callable|null $callable = null) 分页查询
 * @method bool _delete(mixed $condition) 快捷删除逻辑器
 */
abstract class Model extends \think\Model
{
    /**
     * 创建模型实例
     * @return static
     */
    public static function mk($data = []): Model
    {
        return new static($data);
    }

    // 字段权限
    public function scopeField($query)
    {
        $data = InitService::instance()->getData();
        $tableInfo = $data[StrExtend::humpToUnderline($this->name)] ?? [];
        if (!empty($tableInfo) && !AuthService::instance()->isAdmin()) {
            $wheres = $fields = [];
            $roleIds = AuthService::instance()->getUserRoles(true);
            foreach ($tableInfo as $key => $val) {
                if (in_array($key, $roleIds)) {
                    array_push($wheres, ...$val['wheres']);
                    array_push($fields, ...$val['fields']);
                }
            }
            if (!empty($wheres)) $query->where($wheres);
            if (!empty($fields)) $query->withoutField($fields);
        }
    }

    // 数据权限
    public function scopeAuth($query, int $user_id = 0)
    {
        /**
         * 管理员 不为空 取user_id数据
         * 用户 不为空 判断是否有相同角色 有 则取 user_id 数据 否则取自己的数据
         * 管理员 为空 不加条件，取全部数据
         * 用户 为空 取自己的数据
         */
        // 判断表字段是否存在用户ID
        $tableName = $query->getTable();
        $field = $tableName === 'sys_user' ? 'id' : 'user_id';
        if (empty($user_id)) {
            if (!AuthService::instance()->isAdmin()) {
                $query->where($field, AuthService::instance()->getUserInfo('id'));
            }
        } else {
            $tableFields = $query->getFields();
            if (AuthService::instance()->isAdmin()) {
                $query->where('id', $user_id);
            } elseif (isset($tableFields['user_id']) || $tableName === 'sys_user') {
                // 传进来的用户角色
                $group_ids = SysUser::mk()->with('groups')->_read($user_id, function ($data) {
                    return $data->groups->column('id');
                });
                // 取并集 与当前登录用户存在相同角色则允许 否则查找当前登录用户数据
                if (empty(array_intersect($group_ids, AuthService::instance()->getUserGroups(true)))) {
                    $user_id = _getAccessToken('id');
                }
                $query->where($field, $user_id);
            }
        }
    }
}
