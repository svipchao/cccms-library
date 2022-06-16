<?php
declare(strict_types=1);

namespace cccms;

use cccms\extend\StrExtend;
use cccms\services\{AuthService, InitService};
use app\admin\model\SysUser;
use app\admin\model\SysAuth;
use think\model\relation\HasMany;

/**
 * @method Query _withSearch(string|array $fields, array $data = [], string $prefix = '', $value = null) 搜索器
 * @method mixed _read(mixed $data = null, callable|null $callable = null) 查找数据
 * @method array _list(array $where = [], callable|null $callable = null) 数组
 * @method array _page($listRows = null, $simple = false, callable|null $callable = null) 分页查询
 * @method bool _delete(mixed $condition) 快捷删除逻辑器
 */
abstract class Model extends \think\Model
{
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
        if (AuthService::instance()->isLogin()) {
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
    }

    // 关联权限记录表
    public function relationAuth(): HasMany
    {
        return $this->hasMany(SysAuth::class, 'user_id', 'user_id');
    }

    // 数据权限
    public function scopeAuth($query)
    {
        if (AuthService::instance()->isLogin()) {
            // 判断表字段是否存在用户ID
            $tables = InitService::instance()->getTables();
            $tableName = $query->getTable();
            $tableInfo = $tables[$tableName] ?? [];
            // 表不存在则跳过
            if (empty($tableInfo)) return;
            $field = $tableInfo['table'] === 'sys_user' ? 'id' : 'user_id';
            // 字段不存在则跳过
            if (!isset($tableInfo['fields'][$field])) return;
            // 获取关联
            $query->when(!AuthService::instance()->isAdmin(), function ($query) use ($field) {
                $query->hasWhere('relationAuth', [
                    ['group_id', 'in', AuthService::instance()->getUserGroups(true, false, true)]
                ])->whereOr($field, AuthService::instance()->getUserInfo('id'));
            });
        }
    }
}
