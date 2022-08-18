<?php
namespace app\api\validate;

use think\Validate;

class Address extends Validate
{
    protected $rule = [
        'username'  =>  'require|max:25',
        'phone' =>  'require|mobile',
        'province'  =>  'require',
        'city'  =>  'require',
        'area'  =>  'require',
        'address'  =>  'require|min:4'
    ];

    protected $message = [
        'username.require'  =>  '收货人姓名必须',
        'username.max'  =>  '收货人姓名不能超过25个字符',
        'phone.require' =>  '收货人手机号必须',
        'phone.mobile' =>  '收货人手机号格式错误',
        'province.require'  =>  '请输入省',
        'city.require'  =>  '请输入市',
        'area.require'  =>  '请输入区',
        'address.require'  =>  '请输入详细地址',
        'address.min'  =>  '详细地址格式错误',
    ];
}