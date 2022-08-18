<?php

namespace app\api\service;
use think\Db;
use think\Exception;

/**
 * 商城订单服务
 * Class OrderService
 * @package app\store
 */
class CouponService
{
    /**
     * @Notes: 获取会员可领取优惠券
     * @param int $mid 会员ID
     * @param int $goods_id 商品ID
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/12 15:29
     */
    public static function getAvailableCoupon($mid = 0,$goods_id = 0){
        if(empty($mid)){
            return [];
        }
        $user_level = Db::table('store_member')->where('id',$mid)->value('level');
        $list = self::GoodsCoupon($goods_id,$user_level);
        foreach ($list as $key => $item) {
            $count = Db::table('store_coupon_user')
                ->where('mid',$mid)
                ->where('coupon_id',$item['id'])
                ->count();
            if($count >= $item['use_limit']){
                unset($list[$key]);
            }
        }
        $list = array_values($list);
        return $list;
    }
    /**
     * @Notes: 获取可用优惠券
     * @param int $goods_id 商品ID
     * @param int $level 会员等级
     * @param int $mid
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/12 14:52
     */
    private static function GoodsCoupon($goods_id = 0,$level = 0){
        $db = Db::table('store_coupon')->where(['status' => '1','is_deleted' => '0']);
        $db->where(function ($query) use($level){
            $query->where('level_limits','=','0');
            if(!empty($level) && is_numeric($level)){
                $map1 = [
                    ['level_limits', '=', '1'],
                    ['', 'EXP', Db::raw("FIND_IN_SET(".$level.",use_level)")],
                ];
                $query->whereOr([$map1]);
            }
        });
        $db->where(function ($query) use ($goods_id){
            if(!empty($goods_id) && is_numeric($goods_id)){
                $goods = Db::table('store_goods')->where(['id' => $goods_id,'status' => '1','is_deleted' => '0'])->field('cate_id,brand_id')->find();
                if(!empty($goods)){
                    $query->where('coupon_auth_type','=','1');
                    $map1 = [
                        ['coupon_auth_type', '=', '2'],
                        ['coupon_auth_cate', '=', $goods['cate_id']],
                    ];

                    $map2 = [
                        ['coupon_auth_type', '=', '3'],
                        ['coupon_auth_cate', '=', $goods['brand_id']],
                    ];
                    $map3 = [
                        ['coupon_auth_type', '=', '4'],
                        ['coupon_auth_cate', '=', $goods_id],
                    ];
                    $query->whereOr([$map1,$map2,$map3]);
                }
            }
        });
        $db->whereColumn('coupon_stock','>','coupon_sale');
        $list = (array)$db->select();
        return $list;
    }

    /**
     * @Notes: 领取优惠券
     * @param int $mid
     * @param int $id
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/12 15:59
     */
    public static function ReceiveCoupon($mid = 0,$id = 0){
        if(empty($mid) || empty($id)){
            throw new Exception('用户编号或优惠券编号缺失！');
        }
        $user = Db::table('store_member')->where('id',$mid)->field('level')->findOrFail();
        $level = $user['level'];
        $coupon = Db::table('store_coupon')
            ->where('id',$id)
            ->whereColumn('coupon_stock','>','coupon_sale')
            ->where(function ($query) use($level){
                $query->where('level_limits','=','0');
                if(!empty($level)){
                    $map1 = [
                        ['level_limits', '=', '1'],
                        ['', 'EXP', Db::raw("FIND_IN_SET(".$level.",use_level)")],
                    ];
                    $query->whereOr([$map1]);
                }
            })->findOrFail();
        $count = Db::table('store_coupon_user')
            ->where('mid',$mid)
            ->where('coupon_id',$coupon['id'])
            ->count();
        if($count >= $coupon['use_limit']){
            throw new Exception('此优惠券每人限领'.$coupon['use_limit'].'张！');
        }
        $data = [
            'mid' => $mid,
            'coupon_id' => $id,
            'coupon_name' => $coupon['coupon_name'],
            'coupon_quota' => $coupon['coupon_quota'],
//            'coupon_type' => $coupon['coupon_type'],
//            'coupon_discount' => $coupon['coupon_discount'],
            'use_threshold' => $coupon['use_threshold'],
            'level_limits' => $coupon['level_limits'],
            'use_level' => $coupon['use_level'],
            'coupon_auth_type' => $coupon['coupon_auth_type']
        ];
        if($coupon['time_type'] == '1'){
            $data['coupon_start_time'] = $coupon['coupon_start_time'];
            $data['coupon_end_time'] = $coupon['coupon_end_time'];
        }elseif ($coupon['time_type'] == '2'){
            $data['coupon_start_time'] = time();
            $data['coupon_end_time'] = time() + $coupon['coupon_day'] * 24 * 60 * 60;
        }
        if($data['coupon_auth_type'] == '2'){
            $data['coupon_auth_cate'] = $coupon['coupon_auth_cate'];
        }elseif ($data['coupon_auth_type'] == '3'){
            $data['coupon_auth_brand'] = $coupon['coupon_auth_brand'];
        }elseif ($data['coupon_auth_type'] == '4'){
            $data['coupon_auth_goods'] = $coupon['coupon_auth_goods'];
        }
        try{
            Db::transaction(function () use ($data ,$id) {
                Db::table('store_coupon_user')->insert($data);
                Db::table('store_coupon')->where('id',$id)->setInc('coupon_sale',1);
            });
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 订单完成赠送优惠券
     * @param $activity
     * @param $order
     * @throws Exception
     */
    public static function GiftCoupons($activity,$order){
        if(empty($activity) || empty($order)){
            throw new Exception('活动或订单不存在');
        }
        $data = [
            'mid' => $order['mid'],
            'coupon_id' => 0,
            'coupon_name' => $activity['activity_name'],
            'coupon_quota' => $activity['discount'],
            'coupon_type' => 1,
//            'coupon_discount' => $coupon['coupon_discount'],
            'use_threshold' => 0,
            'level_limits' => 0,
            'use_level' => 0,
            'coupon_auth_type' => 1
        ];
        /*if($coupon['time_type'] == '1'){
            $data['coupon_start_time'] = $coupon['coupon_start_time'];
            $data['coupon_end_time'] = $coupon['coupon_end_time'];
        }elseif ($coupon['time_type'] == '2'){*/
            $data['coupon_start_time'] = time();
            $data['coupon_end_time'] = time() + 30 * 24 * 60 * 60;
//        }
        /*if($data['coupon_auth_type'] == '2'){
            $data['coupon_auth_cate'] = $coupon['coupon_auth_cate'];
        }elseif ($data['coupon_auth_type'] == '3'){
            $data['coupon_auth_brand'] = $coupon['coupon_auth_brand'];
        }elseif ($data['coupon_auth_type'] == '4'){
            $data['coupon_auth_goods'] = $coupon['coupon_auth_goods'];
        }*/
        Db::table('store_coupon_user')->insert($data);
    }

    /**
     * 指定赠送会员优惠券
     * @param $data
     * @throws Exception
     */
    public static function SendCoupons($data){
        if(empty($data) || empty($data['uid'])){
            throw new Exception('数据异常');
        }
        $uid=explode(',',$data['uid']);
        foreach ($uid as $mid){
            if(!$mid)continue;
            $data = [
                'mid' => $mid,
                'coupon_id' => 0,
                'coupon_name' => $data['coupon_name'],
                'coupon_quota' => $data['coupon_quota'],
                'coupon_type' => 1,
                'use_threshold' => $data['use_threshold'],
                'level_limits' => 0,
                'use_level' => 0,
                'coupon_auth_type' => 1
            ];
            $data['coupon_start_time'] = time();
            $data['coupon_end_time'] = time() + 30 * 24 * 60 * 60;
            $data_list[]=$data;
        }

        Db::table('store_coupon_user')->insertAll($data_list);
    }

}