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

use service\DataService;
use service\ToolsService;
use service\WechatService;
use think\Db;
use think\facade\Env;
use WeChat\Pay;

/**
 * 微信支付服务
 * Class OrderService
 * @package app\store
 */
class WeChatPayService
{
     protected static $goodsOrderNotifyUrl = 'https://xianshida.test.cqclxsc.com/api/base.wxnotify/goods_order';
     protected static $goodsLevelNotifyUrl = 'https://xianshida.test.cqclxsc.com/api/base.wxnotify/level_order';
    /**
     * @Notes: 定义一个函数获取客户端IP地址定义一个函数获取客户端IP地址
     * @return array|false|string
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/29 15:47
     */
    public static function getIP(){
        global $ip;
        if (getenv("HTTP_CLIENT_IP"))
            $ip = getenv("HTTP_CLIENT_IP");
        else if(getenv("HTTP_X_FORWARDED_FOR"))
            $ip = getenv("HTTP_X_FORWARDED_FOR");
        else if(getenv("REMOTE_ADDR"))
            $ip = getenv("REMOTE_ADDR");
        else $ip = "Unknow IP";
        return $ip;
    }

    /**
     * @Notes: 创建JsApi及H5支付参数
     * @param $options
     * @return array
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/29 15:46
     */
    public static function createGoodsOrder($options){
        if(empty($options['spbill_create_ip'])){
            $options['spbill_create_ip'] = self::getIP();
        }
        if(empty($options['trade_type'])){
            $options['trade_type'] = 'JSAPI';
        }
        if(empty($options['notify_url'])){
            $options['notify_url'] = self::$goodsOrderNotifyUrl;
        }
        $result = WechatService::WeChatPay()->createOrder($options);
        if($result['return_code'] !== 'SUCCESS' || $result['result_code'] !== 'SUCCESS'){
            return ['code' => 0, 'msg' => '生成支付参数失败！'];
        }
        $params = WechatService::WeChatPay()->createParamsForJsApi($result['prepay_id']);
        return ['code' => 1, 'data' => $params, 'msg' => '生成支付参数成功！','result' => $result];
    }

    /**
     * @Notes:
     * @param $options
     * @return array
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/5 14:34
     */
    public static function WePayRefund($options)
    {
        return WechatService::WePayRefund()->create($options);
    }

    /**
     * @Notes: 创建购买级别订单
     * @param $options
     * @return array
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/5 14:57
     */
    public static function createLevelOrder($options)
    {
        if(empty($options['spbill_create_ip'])){
            $options['spbill_create_ip'] = self::getIP();
        }
        if(empty($options['trade_type'])){
            $options['trade_type'] = 'JSAPI';
        }
        if(empty($options['notify_url'])){
            $options['notify_url'] = self::$goodsLevelNotifyUrl;
        }
        $result = WechatService::WeChatPay()->createOrder($options);
        if($result['return_code'] !== 'SUCCESS'){
            return ['code' => 0, 'msg' => '生成支付参数失败！'];
        }
        $params = WechatService::WeChatPay()->createParamsForJsApi($result['prepay_id']);
        return ['code' => 1, 'data' => $params, 'msg' => '生成支付参数成功！','result' => $result];
    }
}