<?php
namespace app\api\validate;

use think\Validate;

class Order extends Validate
{
    protected $rule = [
        'order_no|订单号'  =>  'require|length:10',
        'is_back_goods|是否需要退货' =>  'require|in:0,1',
        'reason|退款原因'  =>  'require|max:50',
        'remark|退款说明'  =>  'require|max:100',
    ];

    public function sceneBackgoods()
    {
        return $this->append('express_username|收货人姓名','require')
            ->append('express_phone|收货人电话','require')
            ->append('express_province|收货人省份','require')
            ->append('express_city|收货人城市','require')
            ->append('express_area|收货人区域','require')
            ->append('express_address|收货人详细地址','require')
            ->append('send_no|物流单号','require')
            ->append('send_company_title|物流公司名称','require')
            ->append('send_company_code|物流公司代码','require');
    }
}