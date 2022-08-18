<?php
namespace app\api\validate;

use think\Validate;

class LevelApply extends Validate
{
    protected $rule = [
        'name'  =>  'require|max:25',
        'phone' =>  'require|mobile',
        'address'  =>  'require|min:4',
        'condition'  =>  'require'
    ];

    protected $message = [
        'name.require'  =>  '申请人姓名必须',
        'name.max'  =>  '申请人姓名不能超过25个字符',
        'phone.require' =>  '申请人手机号必须',
        'phone.mobile' =>  '申请人手机号格式错误',
        'condition.require'  =>  '请输入申请条件',
        'address.require'  =>  '请输入详细地址',
        'address.min'  =>  '详细地址格式错误',
    ];
}