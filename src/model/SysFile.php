<?php
declare (strict_types=1);

namespace cccms\model;

use think\model\relation\HasOne;
use cccms\Model;

class SysFile extends Model
{
    protected $hidden = ['type', 'user'];

    protected $append = ['file_link'];

    public function type(): HasOne
    {
        return $this->hasOne(SysTypes::class, 'id', 'type_id')->bind([
            'type_name' => 'name',
            'type_alias' => 'alias'
        ]);
    }

    public function user(): HasOne
    {
        return $this->hasOne(SysUser::class, 'id', 'user_id')->bind(['nickname', 'username']);
    }

    public function getFileSizeAttr($value): string
    {
        return _format_bytes($value);
    }

    public function getFileLinkAttr($value, $data): string
    {
        return request()->domain() . '/file/' . ($data['file_code'] ?? '404');
    }
}