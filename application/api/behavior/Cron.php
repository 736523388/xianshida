<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/11/23
 * Time: 14:50
 */

namespace app\api\behavior;


use app\api\service\MemberService;
use app\store\service\GoodsService;
use service\LogService;
use service\WechatService;
use think\Db;
use think\Exception;

class Cron
{
    public function run(){

        set_time_limit(0);
        //订单自动完成
        $this->finish_order();
        //自动取消订单
        $this->cancel_order();
        //订单自动收货
        $this->receive_order();
        //定时失败砍价
//        $this->deal_order_bargain();
        //自动成团
        //$this->set_group_order();
    }

    /**
     * @Notes:
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/2/13 11:16
     */
    private function set_group_order(){
        //设置过期的拼团订单失败
        $ee_pre = Db::table('store_goods_group_pre')
            ->where('end_time','<',time())
            ->where('success_time','0')
            ->select();
        foreach ($ee_pre as $item) {
            //发送拼团失败通知
            $goods_title = Db::table('store_goods')->where('id',$item['goods_id'])->value('goods_title');
            $price = Db::table('store_goods_list')->where('goods_id',$item['goods_id'])->where('goods_spec',$item['goods_spec'])->value('market_price');
            $activity_price = Db::table('store_goods_group')->where('id',$item['activity_id'])->value('activity_price');
            $openid = Db::table('store_member')->where('id',$item['mid'])->value('openid');
            $template_id = 'Z5jky8G9nrTN_kKn4wmXpD7BZ2C4dzIpTHLGQbR2iDE';
            $form_id = Db::table('store_order')->where('order_no',$item['order_no'])->value('prepay_id');
            $data = [
                'touser' => $openid,
                'template_id' => $template_id,
                'page' => '/pages/my/my_order/my_order',
                'form_id' => $form_id,
                'data' => [
                    'keyword1' =>[ 'value' => $goods_title ],
                    'keyword2' =>[ 'value' => $price."元" ],
                    'keyword3' =>[ 'value' => $activity_price."元" ],
                    'keyword4' =>[ 'value' => $item['order_no'] ],
                    'keyword5' =>[ 'value' => "参团人数未满！" ]
                ],
            ];
            WechatService::WeMiniTemplate()->send($data);
        }
        Db::table('store_goods_group_pre')
            ->where('end_time','<',time())
            ->setField([
                'success_time' => -1
            ]);

        //查询所有有效期之内的团购订单
        $group_parent_order = Db::table('store_goods_group_pre')
            ->alias('a')
            ->join('store_order b','a.order_no = b.order_no')
            ->join('store_goods_group c','c.id = a.activity_id')
            ->where('a.parent_id','0')
            ->where('a.end_time','>',time())
            ->where('a.success_time','0')
            ->where('b.is_pay','1')
            ->field('a.*,c.complete_num')
            ->select();
        if(empty($group_parent_order)) return false;
        foreach ($group_parent_order as $item) {
            $League_member_count = Db::table('store_goods_group_pre')
                ->alias('a')
                ->join('store_order b','a.order_no = b.order_no')
                ->join('store_goods_group c','c.id = a.activity_id')
                ->where('a.parent_id',$item['id'])
                ->where('b.is_pay','1')
                ->count();
            if($League_member_count + 1 == $item['complete_num']){
                //设置拼团成功
                $dd_pre = Db::table('store_goods_group_pre')
                    ->where('id',$item['id'])
                    ->whereOr('parent_id',$item['id'])
                    ->select();
                foreach ($dd_pre as $items) {
                    //发送拼团成功通知
                    $goods_title = Db::table('store_goods')->where('id',$items['goods_id'])->value('goods_title');
                    $price = Db::table('store_goods_list')->where('goods_id',$items['goods_id'])->where('goods_spec',$item['goods_spec'])->value('market_price');
                    $activity_price = Db::table('store_goods_group')->where('id',$items['activity_id'])->value('activity_price');
                    $openid = Db::table('store_member')->where('id',$items['mid'])->value('openid');
                    $template_id = 'kwHGX3l2EXRYvbYL_j6kBy0V62kd93FoYaB2e5Gsvck';
                    $form_id = Db::table('store_order')->where('order_no',$items['order_no'])->value('prepay_id');
                    $data = [
                        'touser' => $openid,
                        'template_id' => $template_id,
                        'page' => '/pages/my/my_order/my_order',
                        'form_id' => $form_id,
                        'data' => [
                            'keyword1' =>[ 'value' => $items['order_no'] ],
                            'keyword2' =>[ 'value' => $goods_title ],
                            'keyword3' =>[ 'value' => $price."元" ],
                            'keyword4' =>[ 'value' => $activity_price."元" ],
                            'keyword5' =>[ 'value' => "恭喜您已成功拼团！" ]
                        ],
                    ];
                    WechatService::WeMiniTemplate()->send($data);
                }
                Db::table('store_goods_group_pre')->where('id',$item['id'])->whereOr('parent_id',$item['id'])->setField([
                    'success_time' => time()
                ]);
            }
        }
    }

    private function deal_order_bargain(){
        //结束未失败的订单置为失败
        Db::name('store_order_bargain')
            ->where('success_time',0)
            ->where('end_time','<',time())
            ->update(['success_time'=>-1]);
    }

    /**
     * 订单自动收货
     * @author jungshen
     */
    private function receive_order(){
        $order_receive_day=config('mall.order_receive_day');
        if($order_receive_day<=0)return false;
        try{
            $res=Db::name('store_order')
                ->alias('o')
                ->join('store_order_express oe','oe.order_no=o.order_no')
                ->where('o.status',ORDER_STATUS_SHIPPED)
                ->where('oe.type',0)
                ->where('oe.send_at','<',date('Y-m-d H:i:s',time()-$order_receive_day*24*60*60))
                ->update(['o.status'=>ORDER_STATUS_RECEIVED]);
        }catch (Exception $e){
            LogService::write('订单收货','订单收货失败：'.$e->getMessage());
        }
    }

    /**
     * 自动取消订单
     * @author jungshen
     */
    private function cancel_order(){
        $cancel_order_second=config('mall.cancel_order_second');
        if($cancel_order_second<=0)return false;
        //查询需要被去取消的订单
        $order_list=Db::name('store_order')
            ->where('status',1)
            ->where('is_pay',0)
            ->where('create_at','<',date('Y-m-d H:i:s',time()-$cancel_order_second))
            ->column('id,use_integral,order_no,mid,coupon_id');
        $order_nos=implode(',',array_column($order_list,'order_no'));
        if($order_nos){
            //将这些订单全部取消
            Db::startTrans();
            try{
                //取消订单
                Db::name('store_order')
                    ->where('order_no','in',$order_nos)
                    ->setField('status',0);
                //订单商品状态取消
                Db::name('store_order_goods')
                    ->where('order_no','in',$order_nos)
                    ->where('status',1)
                    ->setField('status',0);
                //退回积分
//                foreach ($order_list as $k=>$v){
//                    if($v['coupon_id']>0){
//                        Db::name('StoreCouponUser')->where('id', $v['coupon_id'])->setField('status', 1);
//                    }
//                    if($v['use_integral']>0){
//                        MemberService::log_account_change($v['mid'],$v['use_integral'],'订单['.$v['order_no'].']支付超时，积分自动退还');
//                    }
//                }
                //同步库存
                $order_goods=Db::name('store_order_goods')
                    ->where('order_no','in',$order_nos)
                    ->column('goods_id');
                $order_goods=array_unique($order_goods);
                foreach ($order_goods as $v){
                    //同步库存
                    GoodsService::syncGoodsStock($v);
                }

            }catch (Exception $e){
                Db::rollback();
                LogService::write('订单取消','订单取消失败：'.$e->getMessage());
            }
            Db::commit();
        }
    }

    /**
     * 自动完成订单
     * @return bool
     * @throws Exception
     */
    private function finish_order(){
        //查询所有需要完成的订单
        $config=config('mall.order_finish');
        if(!$config['is_open'])return false;
        $order_list = Db::table('store_order')
            ->alias('a')
            ->join('store_member b','a.mid = b.id')
            ->where('a.status',4)
            ->where('a.create_at','<',date('Y-m-d H:i:s',time()-$config['wait_day']*24*60*60))
            ->column('a.id');
        $ids=implode(',',$order_list);
        if(!$ids)return false;
        Db::name('store_order')->where('id','in',$ids)->setField('status',5);
//        finish_order($ids);
        /**try{
            Db::transaction(function () use($ids){
                finish_order($ids);
                Db::name('store_order')->where('id','in',$ids)->setField('status',5);
            });
        }catch (\Exception $e){
            Db::rollback();
            LogService::write('订单完成','订单完成失败：'.$e->getMessage());
        }**/
    }
}