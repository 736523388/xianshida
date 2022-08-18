<?php
namespace app\api\validate;

use think\Validate;

class Authentication extends Validate
{
    protected $rule = [
        'username'  =>  'require|max:25',
        'id_card' =>  ['require','/(^\d(15)$)|((^\d{18}$))|(^\d{17}(\d|X|x)$)/'],
        /*'image_front'  =>  'require',
        'image_other'  =>  'require'*/
    ];

    protected $message = [
        'username.require'  =>  '姓名必须',
        'username.max'  =>  '姓名不能超过25个字符',
        'id_card.require' =>  '身份证号码必须',
        'id_card' =>  '身份证号码格式错误',
        /*'image_front.require'  =>  '身份证正面照片必须',
        'image_other.require'  =>  '身份证反面照片必须'*/
    ];
}