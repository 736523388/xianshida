<?php
namespace app\api\controller\user;
use app\api\controller\BasicUserApi;
use app\api\service\CouponService;
use think\Db;
use think\exception\HttpResponseException;

class Coupon extends BasicUserApi
{
    public $table = 'StoreCoupon';

    /**
     * @Notes: 可领取优惠券列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/15 10:41
     */
    public function index(){
        $goods_id = $this->request->param('goods_id',0);
        // 排除当前用户已领取且当前时间正有效的优惠券列表
        $mid = UID;
        $res = Db::query("select * from store_coupon where status = 1 and is_deleted = 0 and (coupon_stock = 0 or coupon_stock - coupon_sale > 0)
and NOT EXISTS(select id from store_coupon_user where mid = {$mid} and coupon_id = store_coupon.id and coupon_end_time > unix_timestamp(now()))");
        foreach ($res as &$item) {
            $item['coupon_quota'] = floatval($item['coupon_quota']);
            $item['coupon_discount'] = floatval($item['coupon_discount']);
            $item['use_threshold'] = floatval($item['use_threshold']);
            // 有效期处理
            if($item['time_type'] == 2){
                $item['coupon_start_time'] = strtotime(date('Y-m-d 00:00:00'));
                $item['coupon_end_time'] = strtotime(date('Y-m-d 23:59:59', strtotime("+ {$item['coupon_day']} day")));
            }
        }
        $this->success('success', $res);
//        dump($res);exit();
//        $coupon_list = CouponService::getAvailableCoupon(UID,$goods_id);
//        foreach ($coupon_list as &$item) {
//            $item['coupon_quota'] = floatval($item['coupon_quota']);
//            $item['coupon_discount'] = floatval($item['coupon_discount']);
//            $item['use_threshold'] = floatval($item['use_threshold']);
//        }
//        $this->success('success',$coupon_list);
    }

    /**
     * @Notes: 我的优惠券
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/15 10:47
     */
    public function mycoupon(){
        $status = $this->request->param('status',0);
        $db = Db::table('store_coupon_user')->where('mid',UID);
        if($status != 0){
            if($status == 1){
                $db->where('status','1')
                    ->where('coupon_end_time','>',time());
            }elseif ($status == 2){
                $db->where('status','0');
            }elseif ($status == 3){
                $db->where('status','1')
                    ->where('coupon_end_time','<',time());
            }
        }
        $db->where(function ($query){
            $query->where('level_limits','=','0');
            if(!empty(USER_LEVEL)){
                $map1 = [
                    ['level_limits', '=', '1'],
                    ['', 'EXP', Db::raw("FIND_IN_SET(".USER_LEVEL.",use_level)")],
                ];
                $query->whereOr([$map1]);
            }
        });
        $list = (array)$db
            ->order('create_at desc')
            ->field('*,FROM_UNIXTIME(coupon_start_time,\'%Y-%m-%d\') start_time_format,FROM_UNIXTIME(coupon_end_time,\'%Y-%m-%d\') end_time_format')
            ->select();
        foreach ($list as &$item) {
            $item['coupon_quota'] = floatval($item['coupon_quota']);
            $item['coupon_discount'] = floatval($item['coupon_discount']);
            $item['use_threshold'] = floatval($item['use_threshold']);
        }
        $this->success('success',$list);
    }

    /**
     * @Notes: 领取优惠券
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/15 14:48
     */
    public function receive(){
        $id = $this->request->param('id');
        $mid = UID;
        $res = Db::query("select * from store_coupon where id = {$id} and status = 1 and is_deleted = 0 and (coupon_stock = 0 or coupon_stock - coupon_sale > 0)
and NOT EXISTS(select id from store_coupon_user where mid = {$mid} and coupon_id = {$id} and coupon_end_time > unix_timestamp(now()))");
        if(empty($res)){
            $this->error("领取优惠券失败");
        }
        $coupon = $res[0];
        $integral = $coupon['integral'];
        if($integral > 0){
            //如果积分不够
            $member_integral = Db::name('StoreMember')->where(['id' => $mid])->value('integral');
            if($member_integral < $integral){
                $this->error("您的积分不够，兑换失败");
            }
        }
        $data = [
            'mid' => $mid,
            'coupon_id' => $id,
            'coupon_name' => $coupon['coupon_name'],
            'coupon_quota' => $coupon['coupon_quota'],
            'use_threshold' => $coupon['use_threshold'],
            'coupon_auth_type' => $coupon['coupon_auth_type']
        ];
        if($coupon['time_type'] == 1){
            $data['coupon_start_time'] = $coupon['coupon_start_time'];
            $data['coupon_end_time'] = $coupon['coupon_end_time'];
        }elseif ($coupon['time_type'] == 2){
            $data['coupon_start_time'] = strtotime(date('Y-m-d 00:00:00'));
            $data['coupon_end_time'] = strtotime(date('Y-m-d 23:59:59', strtotime("+ {$coupon['coupon_day']} day")));
        }
        if($data['coupon_auth_type'] == 2){
            $data['coupon_auth_cate'] = $coupon['coupon_auth_cate'];
        }elseif ($data['coupon_auth_type'] == 3){
            $data['coupon_auth_brand'] = $coupon['coupon_auth_brand'];
        }elseif ($data['coupon_auth_type'] == 4){
            $data['coupon_auth_goods'] = $coupon['coupon_auth_goods'];
        }
        try{
            Db::transaction(function () use ($data, $id, $mid, $integral) {
                Db::table('store_coupon_user')->insert($data);
                Db::table('store_coupon')->where('id',$id)->setInc('coupon_sale',1);
                if($integral > 0){
                    Db::name('StoreMember')->where(['id' => $mid])->setDec('integral', $integral);
                    Db::table('store_member_integral_log')->insert([
                        'mid' => $mid,
                        'integral' => -$integral,
                        'desc' => "兑换优惠券【{$data['coupon_name']}】花费{$integral}积分"
                    ]);
                }
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('领取优惠券失败,' . $e->getMessage());
        }
        $this->success('领取优惠券成功');
        dump($res);exit();
        try{
            CouponService::ReceiveCoupon(UID,$id);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('领取优惠券失败,' . $e->getMessage());
        }
        $this->success('领取优惠券成功');
    }
}