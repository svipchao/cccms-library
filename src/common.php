<?php
declare(strict_types=1);

use think\{Response, Validate};
use cccms\extend\{StrExtend, JwtExtend};

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
     * @param string $key 键
     * @return mixed
     */
    function _getAccessToken(string $key = '')
    {
        $accessToken = request()->header('accessToken', '') ?: request()->param('accessToken', '');
        $jwt = JwtExtend::verifyToken($accessToken ?: Session('accessToken'));
        if (!isset($jwt['exp']) || $jwt['exp'] < time()) {
            return [];
        }
        return $key ? $jwt[$key] ?? [] : $jwt;
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
     * @param string $tableAndFields
     *    格式：表名|必选参数|可选参数
     *    例如：例如：sys_user|username,password|nickname,true
     *    PS：可选参数内如果包含 true 则包含表的其他字段，默认可选参数与必选参数会合并处理，不需要写两遍
     * @param array $rule 校验的规则 与ThinkPHP官方验证器写法一样
     * @param array $message 校验的提示信息 与ThinkPHP官方验证器写法一样
     * @return array
     */
    function _validate($params = '', string $tableAndFields = '', array $rule = [], array $message = []): array
    {
        $methods = ['param', 'get', 'post', 'put', 'delete', 'session', 'cookie', 'request', 'server', 'env', 'route', 'middleware', 'file', 'all'];
        if (is_string($params) && in_array($params, $methods)) {
            $params = request()->$params();
        }
        if (empty($params)) {
            _result(['code' => 412, 'msg' => '需要验证的数据为空'], _getEnCode());
        }
        if (!empty($tableAndFields)) {
            // 分割字符串
            $tableAndFields = explode('|', $tableAndFields);
            // 表名 , 必选参数 , 可选参数
            [$tableName, $requireParams, $optionalParams] = [
                $tableAndFields[0] ?? '',
                array_filter(explode(',', $tableAndFields[1] ?? '')),
                array_filter(explode(',', ($tableAndFields[1] ?? '') . ',' . ($tableAndFields[2] ?? '')))
            ];
            // 获取全部表字段信息
            $tablesInfo = cache('Tables');
            // 表信息
            $tableInfo = $tablesInfo[StrExtend::humpToUnderline($tableName)] ?? null;
            if (empty($tableInfo)) {
                _result(['code' => 412, 'msg' => '表不存在'], _getEnCode());
            }
            // 判断是否包含表字段
            if (in_array('true', $optionalParams) && isset($tableInfo['fields'])) {
                array_push($optionalParams, ...array_keys($tableInfo['fields']));
                // 去重
                $optionalParams = array_keys(array_flip($optionalParams));
            }
            // 必选参数和可选参数都为空就没必要往下执行了
            if (empty($requireParams) && empty($optionalParams)) {
                _result(['code' => 412, 'msg' => '需要验证的字段无效'], _getEnCode());
            }
            // 判断必须存在的数据是否存在
            $requireParamsDiff = array_diff_key(array_flip($requireParams), $params);
            if (!empty($requireParamsDiff)) {
                $fieldInfo = [];
                foreach ($tableInfo['fields'] as $fields) {
                    $fields = explode('|', $fields);
                    $fieldInfo[$fields[0]] = $fields[1] ?? '未知参数';
                }
                // 获取表字段信息
                _result(['code' => 412, 'msg' => '必须存在参数：' . join(',', array_intersect_key($fieldInfo, $requireParamsDiff))], _getEnCode());
            }
            // 销毁额外数据
            $params = array_intersect_key($params, array_flip($optionalParams));
            // 获取系统生成的验证规则
            // 取出验证规则键 不能获取交集 表字段必须录入的数据会被绕过
            // $ruleField = array_intersect_key($tableInfo['fields'], $params);
            // 取出验证规则
            // $rule = array_merge(array_intersect_key($tableInfo['rules'], array_flip($ruleField)), $rule);
            $rule = array_merge(array_intersect_key($tableInfo['rules'], array_flip($tableInfo['fields'])), $rule);
        }
        // 验证
        $validate = new Validate;
        if (!$validate->rule($rule)->message($message)->check($params)) {
            _result(['code' => 412, 'msg' => $validate->getError()], _getEnCode()); // 先决条件错误
        }
        return $params;
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