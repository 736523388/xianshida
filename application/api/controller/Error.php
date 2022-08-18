<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/7/26
 * Time: 11:18
 */
namespace app\api\controller;


use controller\BasicApi;

class Error extends BasicApi
{
    public function _empty()
    {
        $this->error('API未注册');
    }
}