<?php
namespace app\store\validate;

use think\Validate;

class Deliver extends Validate
{
    protected $rule = [
        'order_no|订单号'  =>  'require',
    ];
}