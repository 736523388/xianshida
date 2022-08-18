<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/10/25
 * Time: 18:02
 */

namespace app;

use Exception;
use think\exception\Handle;

class ApiException extends Handle
{
    public function render(Exception $e)
    {
        if(request()->module()=='api'){
            return json([
                'code'=>0,
                'msg'=>$e->getMessage(),
                'data'=>[
                    'file'=>$e->getFile(),
                    'line'=>$e->getLine(),
                    'code'=>$e->getCode()
                ]
            ],200);
        }
        return parent::render($e);
    }
}