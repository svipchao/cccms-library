<?php
declare (strict_types=1);

namespace cccms\model;

use think\model\relation\HasOne;
use cccms\Model;

class SysLog extends Model
{
    protected $hidden = ['user'];

    public function user(): HasOne
    {
        return $this->hasOne(SysUser::class, 'id', 'user_id')->bind(['nickname', 'username']);
    }
}