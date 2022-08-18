<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/12/12
 * Time: 15:55
 */

namespace app\api\validate;


use think\Validate;

class Feedback extends Validate
{
    protected $rule = [
        'content|反馈内容'  =>  'require|min:10',
        'phone|手机号' =>  'require|mobile',
    ];
}