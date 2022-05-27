<?php

declare(strict_types=1);

namespace cccms\model;

use think\model\relation\{HasMany, BelongsToMany};
use cccms\Model;
use cccms\services\AuthService;

class SysUser extends Model
{
    protected $append = ['type_text'];

    // 关联组织
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(SysGroup::class, SysUserGroup::class, 'group_id', 'user_id');
    }

    // 关联组织(条件判断使用)
    public function userGroup(): HasMany
    {
        return $this->hasMany(SysUserGroup::class, 'user_id', 'id');
    }

    // 获取当前用户拥有的组织下的所有用户
    public function getCurrentUserGroupUser(): array
    {
        if (AuthService::instance()->isAuth('admin/group/index')) {
            $groupIds = AuthService::instance()->getUserGroups(true);
            return SysUserGroup::where('group_id', 'in', $groupIds)->column('user_id');
        } else {
            return [AuthService::instance()->getUserInfo('id')];
        }
    }

    // 写入前 新增操作和更新操作都会触发
    public static function onBeforeWrite($model)
    {
        if (!isset($model['id'])) {
            $model['token'] = md5(uniqid('cc.', true) . time());
        }
        $res = self::mk()->where('id', '<>', $model['id'])->where(function ($query) use ($model) {
            $query->whereOr([
                ['nickname', '=', $model['nickname']],
                ['username', '=', $model['username']],
            ]);
        })->findOrEmpty();
        if ($res['username'] === $model['username']) {
            _result(['code' => 403, 'msg' => '用户名已存在'], _getEnCode());
        }
    }

    // 设置密码
    public function setPassWordAttr($value, $data)
    {
        if (empty($value)) {
            unset($data['password']);
            return $this->data($data, true);
        }
        return md5($value);
    }

    // 获取密码
    public function getPassWordAttr(): string
    {
        return '';
    }

    // 获取用户类型
    public function getTypeTextAttr($value, $data): string
    {
        return config('cccms.user.types')[$data['type']];
    }
}
