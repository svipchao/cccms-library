<?php
declare(strict_types=1);

namespace cccms\exception;

use Throwable;
use think\Response;
use think\exception\Handle;
use think\db\exception\PDOException;

class Http extends Handle
{
    public function render($request, Throwable $e): Response
    {
        // 请求异常
        if ($e instanceof PDOException) {
            _result(['code' => $e->getCode(), 'msg' => $e->getMessage()], _getEnCode());
        }
        // 其他错误交给系统处理
        return parent::render($request, $e);
    }
}
