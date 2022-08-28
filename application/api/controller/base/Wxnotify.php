<?php
namespace app\api\controller\base;
use app\api\service\MemberService;
use app\api\service\OrderService;
use controller\BasicApi;
use service\WechatService;
use think\Db;
use think\Exception;
use WeChat\Pay;

class Wxnotify extends BasicApi
{
    public $wechat;

    /**
     * Wxnotify constructor.
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function __construct()
    {
        parent::__construct();
        $this->wechat = new Pay([
            'appid'       => sysconf('wechat_appid'),
            'mch_id'      => sysconf('wechat_mch_id'),
            'mch_key'        => sysconf('wechat_partnerkey')
        ]);
    }

    /**
     * @Notes: 等级订单支付回调
     * @throws Exception
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/6 10:50
     */
    public function level_order(){
        $result = $this->wechat->getNotify();
        if($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS'){
            try{
                Db::transaction(function () use($result){
                    /*设置支付状态*/
                    Db::table('store_member_level_buy')->where('order_sn',$result['out_trade_no'])->where('pay_status','0')->setField('pay_status','1');
                    MemberService::level_log_and_back_pre($result['out_trade_no']);
                    $mid = Db::table('store_member_level_buy')->where('order_sn',$result['out_trade_no'])->value('mid');
                    //发送模板消息
                    /*$openid = Db::table('store_member')->where('id',$mid)->value('openid');
                    $template_id = 'fRU24pJ1hah8aaubEeUE5-n5alUyu3fa3Vfxf7vXFeY';
                    $form_id = Db::table('store_member_level_buy')->where('order_sn',$result['out_trade_no'])->value('prepay_id');
                    $data = [
                        'touser' => $openid,//接收者（用户）的 openid
                        'template_id' => $template_id,//所需下发的模板消息的id
                        'page' => '/pages/my/my/my',//点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
                        'form_id' => $form_id,//表单提交场景下，为 submit 事件带上的 formId；支付场景下，为本次支付的 prepay_id
                        'data' => [
                            'keyword1' =>[ 'value' => $result['out_trade_no'] ],
                            'keyword2' =>[ 'value' => ($result['total_fee'] / 100)."元" ],
                            'keyword3' =>[ 'value' => "微信支付" ],
                            'keyword4' =>[ 'value' => "请等待管理员审核...." ]
                        ],
                    ];
                    WechatService::WeMiniTemplate()->send($data);*/
                });
            }catch (\Exception $e){
                die($e->getMessage());
            }
            $data = [
                'return_code' => 'SUCCESS',
                'return_msg' => 'OK'
            ];
            $this->wechat->toXml($data);
            die(0);
        }
    }

    /**
     * @Notes: 商品订单支付回调
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/5 15:29
     */
    public function goods_order(){
        $result = $this->wechat->getNotify();
//        file_put_contents('WxPay.txt',json_encode($result).PHP_EOL,FILE_APPEND);
        if($result['return_code'] === 'SUCCESS' && $result['result_code'] === 'SUCCESS'){
            $out_trade_no = $result['out_trade_no'];//订单号
            $transaction_id = $result['transaction_id'];//交易号
            $real_price = bcdiv($result['total_fee'], 100, 2);//真实支付金额

            if($order = Db::table('store_order')->where('order_no', $out_trade_no)->where('is_pay','0')->find()){

                $mid = $order['mid'];
                $number = intval(Db::name('StoreOrderGoods')->where(['order_no' => $out_trade_no])->sum('number'));
                $integral_base_point = intval(sysconf('integral_base_point'));
                $integral = $number * $integral_base_point;
                try {
                    Db::transaction(function () use($mid, $out_trade_no, $transaction_id, $real_price, $number, $integral){
                        Db::table('store_order')->where('order_no', $out_trade_no)->update([
                            'is_pay' => '1',
                            'pay_type' => 'wechat',
                            'pay_no' => $transaction_id,
                            'pay_price' => $real_price,
                            'pay_at' => date('Y-m-d H:i:s'),
                            'status' => 2
                        ]);
                        // 统计消费额和积分统计
                        Db::name('StoreMember')->where('id', $mid)->update([
                            'self_expenditure' => Db::raw('self_expenditure+' . $real_price),
                            'self_expenditure_total' => Db::raw('self_expenditure_total+' . $real_price),
                            'integral' => Db::raw('integral+' . $integral),
                            'integral_total' => Db::raw('integral_total+' . $integral),
                        ]);
                        Db::table('store_member_integral_log')->insert([
                            'mid' => $mid,
                            'integral' => $integral,
                            'desc' => "购买{$number}件商品获赠{$integral}积分"
                        ]);
//                        Db::name('StoreMember')->where('id', $order['mid'])->setInc('save_amount',$order['member_discount_amount']);
//                        OrderService::manzeng($order);
//                        Db::table('store_member')->where('id',$order['mid'])->setInc('save_amount',$order['member_discount_amount']);
                        //发送模板消息
//                        $openid = Db::table('store_member')->where('id',$order['mid'])->value('openid');
//                        $template_id = 'fRU24pJ1hah8aaubEeUE5-n5alUyu3fa3Vfxf7vXFeY';
//                        $form_id = Db::table('store_order')->where('order_no',$result['out_trade_no'])->value('prepay_id');
//                        $data = [
//                            'touser' => $openid,//接收者（用户）的 openid
//                            'template_id' => $template_id,//所需下发的模板消息的id
//                            'page' => '/pages/my/my_order/my_order',//点击模板卡片后的跳转页面，仅限本小程序内的页面。支持带参数,（示例index?foo=bar）。该字段不填则模板无跳转。
//                            'form_id' => $form_id,//表单提交场景下，为 submit 事件带上的 formId；支付场景下，为本次支付的 prepay_id
//                            'data' => [
//                                'keyword1' =>[ 'value' => $result['out_trade_no'] ],
//                                'keyword2' =>[ 'value' => ($result['total_fee'] / 100)."元" ],
//                                'keyword3' =>[ 'value' => "微信支付" ],
//                                'keyword4' =>[ 'value' => "进入小程序查看详情...." ]
//                            ],
//                        ];
//                        WechatService::WeMiniTemplate()->send($data);
                    });

                } catch (\Exception $e){
                    file_put_contents('WxPayException.txt',$e->getMessage().PHP_EOL,FILE_APPEND);
                }
            }
            $data = [
                'return_code' => 'SUCCESS',
                'return_msg' => 'OK'
            ];
            $this->wechat->toXml($data);
        }
    }
}