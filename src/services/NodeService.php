<?php

declare(strict_types=1);

namespace cccms\services;

use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use cccms\Service;
use cccms\extend\StrExtend;

class NodeService extends Service
{
    /**
     * 获取文件数组
     * @param string $path 扫描目录
     * @param array $ignoresFile 额外数据
     * @return array
     */
    public function scanDirArray(string $path, array $ignoresFile = ['Error', 'view', 'model']): array
    {
        $data = [];
        foreach (glob($path . '*') as $item) {
            $fileName = pathinfo($item)['filename'];
            if (in_array($fileName, $ignoresFile)) continue;
            if (is_dir($item)) {
                $data[$fileName] = $this->scanDirArray($item . DIRECTORY_SEPARATOR, $ignoresFile);
            } elseif (is_file($item) && pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                $data[$fileName] = strtr($item, '\\', '/');
            }
        }
        return $data;
    }

    /**
     * 待扫描文件数组
     * @return array
     */
    public function getToScanFileArray(): array
    {
        $ds = DIRECTORY_SEPARATOR;
        $files = array_merge_recursive(
            $this->scanDirArray($this->app->getRootPath() . 'vendor' . $ds . 'poetry' . $ds . 'cccms-app' . $ds . 'src' . $ds),
            $this->scanDirArray($this->app->getBasePath())
        );
        // 这里需要处理一次结果
        foreach ($files as &$val) {
            if (isset($val['controller'])) $val = $val['controller'];
        }
        return $files;
    }

    /**
     * 所有框架父级节点 应用节点、类节点
     * @return array
     */
    public function getFrameNodes(): array
    {
        $data = $this->app->cache->get('SysFrameNodes') ?? [];
        if (empty($data)) {
            $data = $this->getNodesInfo();
            foreach ($data as &$val) {
                if (!isset($val['frame'])) {
                    unset($val);
                    continue;
                }
                unset($val['parentTitle'], $val['encode'], $val['methods'], $val['appName'], $val['auth'], $val['login'], $val['sort']);
            }
            $this->app->cache->set('SysFrameNodes', $data);
        }
        return $data;
    }

    /**
     * 合并框架节点
     * @param array $nodes
     * @param array $frameNodes
     * @return array
     */
    public function setFrameNodes(array $nodes, array $frameNodes): array
    {
        $parentNode = array_intersect_key($frameNodes, array_column($nodes, 'parentNode', 'parentNode'));
        foreach ($parentNode as $val) {
            if ($val['parentNode'] !== '#') {
                $parentNode = array_merge($parentNode, $this->setFrameNodes($parentNode, $frameNodes));
            }
        }
        return $parentNode;
    }

    /**
     * 获取节点信息
     * @param string $node 权限节点(键)
     * @return array
     */
    public function getNode(string $node = ''): array
    {
        return $this->getNodesInfo()[$node] ?? [];
    }

    /**
     * 获取所有节点
     * @return array
     */
    public function getNodes(): array
    {
        return array_keys($this->getNodesInfo());
    }

    /**
     * 获取所有需要授权的节点
     * @return array
     */
    public function getAuthNodes(): array
    {
        $nodes = $this->getNodesInfo();
        foreach ($nodes as $key => $node) {
            if (isset($node['auth']) && $node['auth'] === false) {
                unset($nodes[$key]);
            }
        }
        return $nodes;
    }

    /**
     * 获取所有控制器方法
     * @param array $toScanFileArray 待扫描文件数组
     * @param array $parentInfo 父级数组信息
     * @param bool $isCache
     * @return array
     */
    public function getNodesInfo(array $toScanFileArray = [], array $parentInfo = [], bool $isCache = false): array
    {
        $data = $this->app->cache->get('SysNodes') ?: [];
        if ($isCache || empty($data)) {
            $toScanFileArray = $toScanFileArray ?: $this->getToScanFileArray();
            $appNames = config('cccms.appName');
            $data = [];
            // 排除内置方法，禁止访问内置方法
            $ignores = get_class_methods('\cccms\Base');
            foreach ($toScanFileArray as $key => $val) {
                if (is_array($val)) {
                    $title = $appNames[$key] ?? $key;
                    $currentNode = $prefix = isset($parentInfo['node']) ? $parentInfo['node'] . '/' . $key : $key;
                    $data[$prefix] = [
                        'title' => $title,
                        'sort' => 1,
                        'currentNode' => $currentNode,
                        'parentNode' => $parentInfo['currentNode'] ?? '#',
                        'parentTitle' => $parentInfo['title'] ?? '#',
                    ];
                    if (!empty($val)) {
                        $data = array_merge($data, $this->getNodesInfo($val, $data[$prefix], true));
                    }
                } else {
                    if (preg_match("/(\w+)\/(\w+)\/controller\/(.*)\.php/i", $val, $matches)) {
                        [, $namespace, $appName, $className] = $matches;
                        $namespace = 'app';
                        if (!class_exists($namespace . '\\' . $appName . '\\controller\\' . strtr($className, '/', '\\'))) continue;
                        try {
                            $reflect = new ReflectionClass($namespace . '\\' . $appName . '\\controller\\' . strtr($className, '/', '\\'));
                            // 判断是否继承基础类库 没有继承 跳出循环 || 如果没有注释 跳出循环
                            if (($reflect->getParentClass()->name ?? '') !== 'cccms\Base' || $reflect->getDocComment() === false) continue;
                            // 前缀 类的命名空间
                            $prefix = StrExtend::humpToUnderline(strtr($appName . '/' . $className, ['\\' => '/', '.' => '/']));
                            // 赋值类节点 方便处理Tree
                            $data[$prefix] = array_merge($this->parseComment($reflect->getDocComment(), $className), [
                                'currentNode' => $prefix,
                                'parentNode' => $parentInfo['currentNode'] ?? '#',
                                'parentTitle' => $parentInfo['title'] ?? '#',
                            ]);
                            unset($data[$prefix]['auth'], $data[$prefix]['login'], $data[$prefix]['encode'], $data[$prefix]['methods']);
                            $reflectionMethod = $reflect->getMethods(ReflectionMethod::IS_PUBLIC);
                            foreach ($reflectionMethod as $method) {
                                // 忽略的方法 || 没有注释 跳出循环
                                if (in_array($metName = StrExtend::humpToUnderline($method->getName()), $ignores) || $method->getDocComment() === false) continue;
                                // 赋值类节点 方便处理Tree
                                $data[$prefix . '/' . $metName] = array_merge($this->parseComment($method->getDocComment(), $metName), [
                                    'currentNode' => $prefix . '/' . $metName,
                                    'parentNode' => $prefix,
                                    'parentTitle' => $data[$prefix]['title'],
                                ]);
                            }
                        } catch (ReflectionException $e) {
                        }
                    }
                }
            }
            $data = array_change_key_case($data, CASE_LOWER);
            $this->app->cache->set('SysNodes', $data);
        }
        return $data;
    }

    /**
     * 解析硬节点属性
     * @param string $comment 备注内容
     * @param string $default 默认标题
     * @return array
     */
    private function parseComment(string $comment, string $default = ''): array
    {
        $text = strtolower(strtr($comment, "\n", ' '));
        $title = preg_replace('/^\/\*\s*\*\s*\*\s*(.*?)\s*\*.*?$/', '$1', $text);
        foreach (['@auth', '@login', '@methods'] as $find) {
            if (stripos($title, $find) === 0) $title = $default;
        }
        preg_match('/@encode.(\S+)/i', $text, $enCode);
        preg_match('/@sort.(\S+)/i', $text, $sort);
        preg_match('/@methods.(\S+)/i', $text, $methods);
        // 请求返回编码 view|json|jsonp|xml
        // 请求类型详细解释请看 https://www.kancloud.cn/manual/thinkphp6_0/1037520
        return [
            'title' => $title ?: $default,
            'sort' => $sort[1] ?? 0,
            'auth' => (bool)intval(preg_match('/@auth\s*true/i', $text)),
            'login' => (bool)intval(preg_match('/@login\s*true/i', $text)),
            'encode' => isset($enCode[1]) ? explode('|', $enCode[1]) : [],
            'methods' => isset($methods[1]) ? explode('|', strtoupper($methods[1])) : [],
        ];
    }
}
