<?php
declare(strict_types=1);

namespace cccms;

use think\Service;
use cccms\exception\Http;
use cccms\services\NodeService;
use cccms\multiple\{Url, MultiApp};

class Library extends Service
{
    // 初始化服务
    public function boot()
    {
        // 多应用
        $this->app->event->listen('HttpRun', function () {
            $this->app->middleware->add(MultiApp::class);
        });
        // 绑定URL类
        $this->app->bind(['think\route\Url' => Url::class]);
        // 设置扩展配置文件
        $libraryConfigPath = NodeService::instance()->scanDirArray($this->app->getRootPath() . 'vendor/svipchao/cccms-library/src/cccms/config/', []);
        foreach ($libraryConfigPath as $libraryConfig) {
            $this->app->config->load($libraryConfig, pathinfo($libraryConfig, PATHINFO_FILENAME));
        }
        // 设置用户配置文件
        $userConfigPath = NodeService::instance()->scanDirArray($this->app->getRootPath() . 'cccms/config/', []);
        foreach ($userConfigPath as $userConfig) {
            $this->app->config->load($userConfig, pathinfo($userConfig, PATHINFO_FILENAME));
        }
        // 设置数据库指定查询对象
        $database = $this->app->config->get('database', []);
        $database['connections'][$database['default']]['query'] = '\\cccms\\Query';
        $database['connections'][$database['default']]['fields_cache'] = true;
        $this->app->config->set($database, 'database');
        // 设置全局中间件
        $this->app->middleware->import($this->app->config->get('cccms.middleware', []));
        // 设置过滤规则
        $this->app->request->filter(['trim', 'strip_tags', 'htmlspecialchars']);
    }
}
