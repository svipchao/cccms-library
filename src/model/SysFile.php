<?php
declare(strict_types=1);

namespace cccms\model;

use cccms\Model;
use cccms\extend\FormatExtend;
use think\model\relation\HasOne;

class SysFile extends Model
{
    protected $hidden = ['type', 'user'];

    protected $append = ['file_share', 'file_url'];

    public function cate(): HasOne
    {
        return $this->hasOne(SysFileCate::class, 'id', 'cate_id')->bind([
            'cate_name'
        ]);
    }

    public function user(): hasOne
    {
        return $this->hasOne(SysUser::class, 'id', 'user_id')->bind([
            'nickname',
            'username'
        ]);
    }

    public function searchCateIdAttr($query, $value): void
    {
        $query->where('cate_id', '=', $value);
    }

    public function getFileSizeAttr($value): string
    {
        return FormatExtend::formatBytes($value);
    }

    public function getFileShareAttr($value, $data): string
    {
        return request()->domain() . '/file/' . ($data['file_code'] ?? '404');
    }

    public function getFileUrlAttr($value, $data): string
    {
        return request()->domain() . '/uploads/' . ($data['file_path'] ?? '404');
    }
}
