<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2020/3/23
 * Time: 10:52
 */

namespace app\api\validate;


use think\Validate;

class Wholesaler extends Validate
{
    protected $rule=[
        'img|营业执照'=>'require|url',
        'title|单位名称'=>'require',
        'name|法人姓名'=>'require',
        'address|联系地址'=>'require',
        'validtime|有效期'=>'require',
        'id_num|证件编号'=>'require',
        'credit_code|社会信用代码'=>'require'
    ];
}