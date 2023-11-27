<?php
declare(strict_types=1);

namespace cccms;

use think\db\{Query, Mongo, BaseQuery};
use think\{App, Model, Request, Container};
use cccms\extend\VirtualModelExtend;

/**
 * 自定义服务基类
 * Class Service
 */
abstract class Service
{
    /**
     * 应用实例
     * @var App
     */
    protected App $app;

    /**
     * @var Request
     */
    protected Request $request;

    /**
     * Service constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->initialize();
    }

    /**
     * 初始化服务
     */
    protected function initialize()
    {
    }

    /**
     * 静态实例对象
     * @param array $var 实例参数
     * @param boolean $new 创建新实例
     * @return static
     */
    public static function instance(array $var = [], bool $new = false): Service
    {
        return Container::getInstance()->make(static::class, $var, $new);
    }

    /**
     * 获取数据库查询对象
     * @param Model|BaseQuery|string $query
     * @return Query|Mongo|BaseQuery
     */
    public static function buildQuery($query)
    {
        if (is_string($query)) {
            return static::buildModel($query)->db();
        }
        if ($query instanceof Model) return $query->db();
        if ($query instanceof BaseQuery && !$query->getModel()) {
            $name = $query->getConfig('name') ?: '';
            if (is_string($name) && strlen($name) > 0) {
                $name = config("database.connections." . $name) ? $name : '';
            }
            $query->model(static::buildModel($query->getName(), [], $name));
        }
        return $query;
    }

    /**
     * 动态创建模型对象
     * @param mixed $name 模型名称
     * @param array $data 初始数据
     * @param mixed $conn 指定连接
     * @return Model
     */
    public static function buildModel(string $name, array $data = [], string $conn = ''): Model
    {
        if (strpos($name, '\\') !== false) {
            if (class_exists($name)) {
                $model = new $name($data);
                if ($model instanceof Model) return $model;
            }
            $name = basename(str_replace('\\', '/', $name));
        }
        return VirtualModelExtend::mk($name, $data, $conn);
    }
}