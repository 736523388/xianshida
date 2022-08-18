<?php
/**
 * Created by PhpStorm.
 * User: forska
 * Date: 2018/11/8
 * Time: 11:37
 */


namespace app\api\controller\store;

use app\api\controller\BasicUserApi;

class Index extends BasicUserApi
{
    public function index(){
        return create_order_sn();
    }
}