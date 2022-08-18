<?php
namespace app\api\validate;

use think\facade\Cache;
use think\Validate;

class Member extends Validate
{
    protected $rule = [
        'mobile|手机号' =>  'require|mobile',
    ];

    public function sceneBindmobile(){
        $this->append('code|短信验证码','require|length:'.config('aliyun.code_length').'|checkCode');
    }

    protected function checkCode($value,$rule,$data=[])
    {
        if($value!=Cache::pull($data['mobile'].'_bind_mobile')){
            return '短信验证码错误';
        }
        return true;
    }
}