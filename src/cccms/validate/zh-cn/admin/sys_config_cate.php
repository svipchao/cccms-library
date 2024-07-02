<?php
declare (strict_types=1);

return [
    'cate_name|配置名称' => 'require|length:1,32',
    'cate_name.require' => '%s 不能为空',
    'cate_name.length' => '%s 长度至少 1 个字符或不超过 32 个字符',

    'cate_desc|配置描述' => 'length:0,255',
    'cate_desc.length' => '%s 长度不能超过 255 个字符',

    'sort|排序' => 'integer',
    'sort.integer' => '请录入正确的 %s',

    'create_time|创建时间' => 'date',
    'create_time.date' => '请录入正确的 %s',

    'update_time|创建时间' => 'date',
    'update_time.date' => '请录入正确的 %s',
];
