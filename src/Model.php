<?php
declare(strict_types=1);

namespace cccms;

use cccms\extend\StrExtend;
use cccms\services\{AuthService, InitService};
use app\admin\model\SysUserGroup;

/**
 * @method mixed _read(mixed $data = null, callable|null $callable = null) 查找数据
 * @method array _list(array $where = [], callable|null $callable = null) 数组
 * @method array _page($listRows = null, $simple = false, callable|null $callable = null) 分页查询
 * @method bool _delete(mixed $condition) 快捷删除逻辑器
 */
abstract class Model extends \think\Model
{
    // 定义全局的查询范围
    protected $globalScope = ['field'];

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
        $tableInfo = $data[StrExtend::humpToUnderline($this->name)] ?? null;
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
        // 判断表字段是否存在用户ID
        $tableFields = $query->getFields();
        $tableName = $query->getTable();
        $field = $tableName === 'sys_user' ? 'id' : 'user_id';
        if (!AuthService::instance()->isAdmin() && (isset($tableFields['user_id']) || $tableName === 'sys_user')) {
            // 传进来的用户角色
            $groupIds = SysUserGroup::mk()->where('user_id', $user_id)->column('group_id');
            // 取并集 与当前登录用户存在相同角色则允许 否则查找当前登录用户数据
            if (empty(array_intersect($groupIds, AuthService::instance()->getUserGroups(true)))) {
                $user_id = _getAccessToken('id');
            }
            $query->where($field, $user_id);
        }
    }
}