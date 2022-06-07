<?php
declare(strict_types=1);

use think\{Response, Validate};
use cccms\services\InitService;
use cccms\extend\StrExtend;

if (!function_exists('_config')) {
    /**
     * 获取缓存
     * @param string $name 配置参数名（支持多级配置.号分割）
     * @param mixed $default 默认值
     * @return mixed
     */
    function _config(string $name = '', $default = null)
    {
        $configs = cache('SysConfigs');
        if (empty($configs)) return $default;
        if (empty($name)) return $configs;
        $name = explode('.', $name);
        // 按.拆分成多维数组进行判断
        foreach ($name as $val) {
            if (isset($configs[$val])) {
                $configs = $configs[$val];
            } else {
                return $default;
            }
        }
        return $configs;
    }
}

if (!function_exists('_filePath')) {
    /**
     * 输出文件路径
     * @param string $file_code 文件Code值
     * @return mixed
     */
    function _filePath(string $file_code = '')
    {
        return str_replace('<code>', $file_code, config('cccms.storage.routePath', '/file/<code>'));
    }
}

if (!function_exists('_time')) {
    /**
     * 获取 系统时间戳
     * @param string $format 时间格式
     * @return mixed
     */
    function _time(string $format = '')
    {
        return $format ? date($format, $_SERVER['REQUEST_TIME']) : $_SERVER['REQUEST_TIME'];
    }
}

if (!function_exists('_format_bytes')) {
    /**
     * 文件字节单位转换
     * @param string|integer $size
     * @return string
     */
    function _format_bytes($size): string
    {
        if (is_numeric($size)) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
            for ($i = 0; $size >= 1024 && $i < 4; $i++) $size /= 1024;
            return round($size, 2) . ' ' . $units[$i];
        } else {
            return $size;
        }
    }
}

if (!function_exists('_getAccessToken')) {
    /**
     * 获取 accessToken 值 优先级
     * 注意：GET传进来需要进行 urldecode (PHP)/encodeURIComponent(JS)加密
     * @return array|string|null
     */
    function _getAccessToken()
    {
        return request()->header('accessToken', request()->param('accessToken', Session('accessToken')));
    }
}

if (!function_exists('_getNode')) {
    // 获取当前节点
    function _getNode(): string
    {
        return StrExtend::humpToUnderline(app('http')->getName() . '/' . str_replace('.', '/', request()->controller()) . '/' . request()->action());
    }
}

if (!function_exists('_getEnCode')) {
    /**
     * 获取返回编码类型 (view,json,jsonp,xml)
     * PS:  第一个为默认编码类型
     *      view 类型请自行阅读 common.php->_result()
     *      前后端分离开发模式不需要用到 view
     * @param string $enCode 默认值
     * @return string
     */
    function _getEnCode(string $enCode = 'view'): string
    {
        return strtolower(request()->param('encode/s', $enCode));
    }
}

if (!function_exists('_validate')) {
    /**
     * @param string|array $params 需要校验的参数
     * @param string|array|null $filterParams
     *    格式：表名|必选参数|可选参数|额外参数
     *    例如：例如：sys_user|username,password|nickname,true
     *    PS：可选参数内如果包含 true 则包含表的其他字段，默认可选参数与必选参数会合并处理，不需要写两遍
     *        额外参数仅仅是方便返回自定义数据，并不参与验证等操作
     * @param array $rule 校验的规则 与ThinkPHP官方验证器写法一样
     * @param array $message 校验的提示信息 与ThinkPHP官方验证器写法一样
     * @return array
     */
    function _validate($params = '', $filterParams = null, array $rule = [], array $message = []): array
    {
        if (is_string($params) && method_exists(request(), $params)) {
            $params = request()->$params();
        }
        if (empty($params)) {
            _result(['code' => 412, 'msg' => '需要验证的数据为空'], _getEnCode());
        }
        if (!empty($filterParams)) {
            [$tableName, $requireParams, $optionalParams] = array_pad([], 3, '');
            if (is_string($filterParams)) {
                [$tableName, $requireParams, $optionalParams] = array_pad(explode('|', $filterParams), 3, "");
            } elseif (is_array($filterParams)) {
                [$tableName, $requireParams, $optionalParams] = array_pad($filterParams, 3, "");
            }

            function handleParams($params, &$extraParams)
            {
                if (empty($params)) return [];
                if (is_string($params)) $params = explode(',', $params);
                foreach ($params as $key => $value) {
                    if (is_int($key)) {
                        unset($params[$key]);
                        $params[$value] = 0;
                    } else {
                        $extraParams[$key] = $value;
                    }
                }
                return $params;
            }

            // 额外参数
            $extraParams = [];
            [$requireParams, $optionalParams] = [
                handleParams($requireParams, $extraParams),
                handleParams($optionalParams, $extraParams)
            ];
            // 获取全部表字段信息
            $tables = InitService::instance()->getTables();
            // 表信息
            $tableInfo = $tables[StrExtend::humpToUnderline($tableName)] ?? null;
            if (empty($tableInfo)) {
                _result(['code' => 412, 'msg' => '表不存在'], _getEnCode());
            }
            // 判断可选参数是否包含表字段
            if (isset($optionalParams['true']) && isset($tableInfo['fields'])) {
                unset($optionalParams['true']);
                $tableFields = array_keys($tableInfo['fields']);
                // 将字段默认值重置为空
                $optionalParams = array_merge(array_fill_keys($tableFields, null), $optionalParams);
            }
            // 判断必须存在的数据是否存在
            $requireParamsDiff = array_diff_key($requireParams, $params);
            if (!empty($requireParamsDiff)) {
                $tableFields = array_intersect_key($tableInfo['fields'], $requireParamsDiff);
                $requireParamsDiff = array_replace($requireParamsDiff, $tableFields);
                foreach ($requireParamsDiff as $key => &$val) {
                    $val = ($val ?: '未知参数') . '(' . $key . ')';
                }
                _result(['code' => 412, 'msg' => '必须存在参数：' . implode(',', $requireParamsDiff)], _getEnCode());
            }
            // 合并参数
            $mergeParams = array_merge($requireParams, $optionalParams);
            // 销毁额外数据
            $params = array_intersect_key($params, $mergeParams);
            // 获取系统生成的验证规则 必须存在的参数需在 $filterParams 中配置 这里只验证传进来的参数
            $ruleField = array_intersect_key($tableInfo['ruleAlias'], $params);
            // 取出验证规则
            $rule = array_merge(array_intersect_key($tableInfo['rules'], array_flip($ruleField)), $rule);
        }
        // 验证
        $validate = new Validate;
        if (!$validate->rule($rule)->message($message)->check($params)) {
            _result(['code' => 412, 'msg' => $validate->getError()], _getEnCode()); // 先决条件错误
        }
        // 将定义默认值的参数返回 考虑到更新时 用户会传递部分参数，如果为空会将数据重置为空
        return array_merge($extraParams ?? [], $params);
    }
}

if (!function_exists('_result')) {
    /**
     * 返回数据
     * @param array $data 参数
     * @param string $type 输出类型(view,json,jsonp,xml)
     * @param array $header 设置响应的头信息
     * @param array $options 输出参数 \think\response\ 下的options输出参数配置
     */
    function _result(array $data = [], string $type = '', array $header = [], array $options = []): Response
    {
        $type = $type ?: _getEnCode();
        $code = (int)($data['code'] ?? 200);
        if (in_array(strtolower($type), ['json', 'jsonp', 'xml'])) {
            $response = Response::create($data, $type, $code)->options($options);
        } else {
            if ($type === 'view') {
                $html = config('cccms.resultPath');
            } else {
                $html = '../app/' . strtolower(app('http')->getName()) . '/view/' . $type . '.html';
            }
            $response = Response::create($html, 'view', $code)->assign($data);
        }
        throw new \think\exception\HttpResponseException($response->header($header));
    }
}
