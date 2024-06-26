<?php
declare(strict_types=1);

namespace cccms\extend;

/**
 * 随机数码管理扩展
 * Library for ThinkAdmin
 * https://gitee.com/zoujingli/ThinkLibrary
 */
class StrExtend
{
    /**
     * 下划线转驼峰(首字母大写)
     * @param string $value
     * @return string
     */
    public static function underlineToHump(string $value): string
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return str_replace(' ', '', $value);
    }

    /**
     * 小驼峰转下划线
     * @param string $value
     * @param string $delimiter
     * @return string
     */
    public static function humpToUnderline(string $value, string $delimiter = '_'): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', "$1" . $delimiter . "$2", $value));
    }

    /**
     * 字符串转数组
     * @param string $text 待转内容
     * @param string $separator 分隔字符
     * @param null|array $allow 限定规则
     * @return array
     */
    public static function str2arr(string $text, string $separator = ',', ?array $allow = null): array
    {
        $items = [];
        foreach (explode($separator, trim($text, $separator)) as $item) {
            if ($item !== '' && (!is_array($allow) || in_array($item, $allow))) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * 字符串转小写
     * @param string $value
     * @return string
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * 获取随机字符串编码
     * @param integer $size 编码长度
     * @param integer $type 编码类型(1纯数字,2纯字母,3数字字母)
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function random(int $size = 10, int $type = 1, string $prefix = ''): string
    {
        $numbs = '0123456789';
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        if ($type === 1) $chars = $numbs;
        if ($type === 3) $chars = $numbs . $chars;
        $code = $prefix . $chars[rand(1, strlen($chars) - 1)];
        while (strlen($code) < $size) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /**
     * 唯一日期编码
     * @param integer $size 编码长度
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function uniqueIdDate(int $size = 16, string $prefix = ''): string
    {
        if ($size < 14) $size = 14;
        $code = $prefix . date('Ymd') . ((int)date('H') + (int)date('i')) . date('s');
        while (strlen($code) < $size) $code .= rand(0, 9);
        return $code;
    }

    /**
     * 唯一数字编码
     * @param integer $size 编码长度
     * @param string $prefix 编码前缀
     * @return string
     */
    public static function uniqueNumber(int $size = 12, string $prefix = ''): string
    {
        $time = time() . '';
        if ($size < 10) $size = 10;
        $code = $prefix . (intval($time[0]) + intval($time[1])) . substr($time, 2) . rand(0, 9);
        while (strlen($code) < $size) $code .= rand(0, 9);
        return $code;
    }

    /**
     * 数据加密处理
     * @param mixed $data 加密数据
     * @param string $sKey 安全密钥
     * @return string
     */
    public static function encrypt(mixed $data, string $sKey): string
    {
        $iv = static::random(16, 3);
        $value = openssl_encrypt(serialize($data), 'AES-256-CBC', $sKey, 0, $iv);
        return static::enSafe64(json_encode(['iv' => $iv, 'value' => $value]));
    }

    /**
     * 数据解密处理
     * @param string $data 解密数据
     * @param string $sKey 安全密钥
     * @return mixed
     */
    public static function decrypt(string $data, string $sKey): mixed
    {
        $attr = json_decode(static::deSafe64($data), true);
        return unserialize(openssl_decrypt($attr['value'], 'AES-256-CBC', $sKey, 0, $attr['iv']));
    }

    /**
     * Base64Url 安全编码
     * @param string $text 待加密文本
     * @return string
     */
    public static function enSafe64(string $text): string
    {
        return rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
    }

    /**
     * Base64Url 安全解码
     * @param string $text 待解密文本
     * @return string
     */
    public static function deSafe64(string $text): string
    {
        return base64_decode(str_pad(strtr($text, '-_', '+/'), strlen($text) % 4, '='));
    }
}