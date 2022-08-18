<?php

// +----------------------------------------------------------------------
// | Think.Admin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/Think.Admin
// +----------------------------------------------------------------------

namespace app\api\service;
use think\Db;

/**
 * 商城订单服务
 * Class OrderService
 * @package app\store
 */
class IntegralService
{
    /**
     * @Notes: 积分记录
     * @param int $mid
     * @param int $integral
     * @param string $desc
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/6 14:52
     */
    public static function RecordLog($mid = 0,$integral = 0,$desc = ''){
        Db::table('store_member_integral_log')->insert(['mid' => $mid,'integral' => $integral,'desc' => $desc]);
    }
}