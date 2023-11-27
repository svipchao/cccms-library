<?php

return [
    'appName' => [
        'admin' => '基础系统',
        'index' => '默认应用',
    ],
    'resultPath' => app()->getRootPath() . 'vendor/svipchao/cccms-library/src/cccms/views/result.tpl',
    'middleware' => [
        'think\middleware\SessionInit'
    ],
    'user' => [
        // 用户类型
        'types' => [
            '后台用户',
            '前台会员'
        ]
    ],
    'types' => [
        'type' => [
            '未知',
            '菜单',
            '配置',
            '路由',
            '附件',
        ]
    ],
    'storage' => [
        // 附件访问路由配置
        'routePath' => '/file/<code>'
    ]
];