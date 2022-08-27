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

use app\store\service\GoodsService;
use service\DataService;
use service\ToolsService;
use think\Db;
use think\Exception;

/**
 * 商城订单服务
 * Class OrderService
 * @package app\store
 */
class OrderService
{
    /**
     * @Notes:
     * @param int $mid 用户ID
     * @param boolean $is_insider 是否是会员
     * @param int $uesr_level 用户级别 非会员为
     * @param string $params 商品参数规格 (商品ID@商品规格@购买数量;商品ID@商品规格@购买数量)
     * @param bool $use_integral 是否使用积分
     * @param int $coupon_id 使用的优惠券ID
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/30 17:27
     */
    public static function confirmOrder($mid, $user_level, $params, $coupon_id = 0)
    {
        /*当前用户是否会员身份*/
        $is_insider = $user_level ? true : false;
        /*当前用户计价字段*/
        $price_field = $is_insider ? 'selling_price' : 'market_price';
//        $price_field = 'selling_price';
        /* 优惠券列表 商品列表*/
        list($couponlist, $goodslist) = [[], []];

        //商品分类信息
        $cateField = 'id,pid,cate_title,cate_desc';
        $cateWhere = ['status' => '1', 'is_deleted' => '0'];
        $cateList = Db::name('StoreGoodsCate')->where($cateWhere)->order('sort asc,id desc')->column($cateField);
        /* 应付金额 商品金额 优惠金额 会员见面金额 运费金额 商品重量 是否需要实名认证*/
        $confirmData = ['real_price' => 0, 'goods_price' => 0, 'discount_amount' => 0, 'member_discount_amount' => 0, 'freight_price' => 0, 'goods_weight' => 0, 'is_auth' => 0];

        foreach (explode(';', trim($params, ',;@')) as $param) {
            list($goods_id, $goods_spec, $number) = explode('@', "{$param}@@");
            $item = ['mid' => $mid, 'goods_id' => $goods_id, 'goods_spec' => $goods_spec, 'goods_number' => $number];
            $goodsResult = self::buildConfirmData($item, $goodslist, $cateList, $confirmData, $price_field);
            if (empty($goodsResult['code'])) {
                return $goodsResult;
            }
        }
        // 生成优惠券信息
        $couponResult = self::buildCouponData($mid, $user_level, $confirmData['goods_price'], $goodslist, $couponlist);
        if (empty($couponResult['code'])) {
            return $couponResult;
        }
        //使用优惠券信息
        $use_coupon = [];
        if (!empty($coupon_id) && is_numeric($coupon_id)) {
            foreach ($couponlist as $coup) {
                if ($coup['id'] == $coupon_id) {
                    $use_coupon = $coup;
                    $confirmData['discount_amount'] += $coup['coupon_quota'];
                    $use_coupon['discount_amount'] = $coup['coupon_quota'];
                }
            }
        }
        $confirmData['real_price'] -= $confirmData['discount_amount'];
        //处理满减
        $manjian_activity=self::manjian($user_level,$confirmData['real_price']);
        if($manjian_activity){
            $confirmData['manjian']=$manjian_activity;
            $confirmData['real_price'] -= $confirmData['manjian']['discount'];
        }else{
            $confirmData['manjian']=[];
        }
        $confirmData['real_price'] = $confirmData['real_price'] < 0 ? 0 : $confirmData['real_price'];

        //计算邮费
        $confirmData['freight_price']=0;
        $insider_exempt_postage = sysconf('insider_exempt_postage') ? sysconf('insider_exempt_postage') : '0';
        if (!$is_insider || !$insider_exempt_postage) {
            if (($confirmData['real_price'] < sysconf('exemption_postage_price')) && $confirmData['goods_weight'] > 0) {
                $confirmData['freight_price'] += sysconf('basic_freight');
                if ($confirmData['goods_weight'] > sysconf('basic_weight')) {
                    $confirmData['freight_price'] += ($confirmData['goods_weight'] - sysconf('basic_weight')) * sysconf('extra_freight');
                }
            }
            $confirmData['real_price'] += $confirmData['freight_price'];
        }
        /*$confirmData['freight_price']=sysconf('order_post_fee');
        $confirmData['real_price'] += $confirmData['freight_price'];*/

        // 获取积分信息
        $confirmData['goods_price'] = sprintf("%.2f", $confirmData['goods_price']);
        $confirmData['real_price'] = sprintf("%.2f", $confirmData['real_price']);
        $confirmData['discount_amount'] = sprintf("%.2f", $confirmData['discount_amount']);
        $confirmData['member_discount_amount'] = sprintf("%.2f", $confirmData['member_discount_amount']);
        $confirmData['freight_price'] = sprintf("%.2f", $confirmData['freight_price']);

        $data = [
            'mid' => $mid,
            'goods_price' => $confirmData['goods_price'],
            'real_price' => $confirmData['real_price'],
            'manjian' => $confirmData['manjian'],
            'discount_amount' => $confirmData['discount_amount'],
            'member_discount_amount' => $confirmData['member_discount_amount'],
            'freight_price' => $confirmData['freight_price'],
            'goodslist' => $goodslist,
            'couponlist' => $couponlist,
            'use_coupon' => $use_coupon,
            'user_integral' => []
        ];
        return ['code' => 1, 'msg' => 'success', 'data' => $data];
    }

    /**
     * 满减活动
     * @param $user_level
     * @param $real_price
     * @return array|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function manjian($user_level,$real_price){
        //查询满减活动
        $db=Db::name('store_full_reduction')
            ->where('status',1)
            ->where('activity_start_time','<=',date('Y-m-d H:i:s'))
            ->where('activity_end_time','>=',date('Y-m-d H:i:s'))
            ->where('reach','<=',$real_price);
        if($user_level>0){
            //批发商
            $db->where('identity','<>',1);
        }else{
            //会员
            $db->where('identity','<>',2);
        }
        $activity=$db->order('reach','desc')->findOrEmpty();
        return $activity;

    }

    public static function manzeng($order){
        $member=Db::table('store_member')->where('id',$order['mid'])->field('id,level')->find();
        //查询满减活动
        $db=Db::name('store_full_gift')
            ->where('status',1)
            ->where('activity_start_time','<=',date('Y-m-d H:i:s'))
            ->where('activity_end_time','>=',date('Y-m-d H:i:s'))
            ->where('reach','<=',$order['real_price']);
        if($member['level']>0){
            //批发商
            $db->where('identity','<>',1);
        }else{
            //会员
            $db->where('identity','<>',2);
        }
        $activity=$db->order('reach','desc')->findOrEmpty();
        if($activity){
            file_put_contents('manzeng.log',json_encode($activity).PHP_EOL,FILE_APPEND);
            //赠送他优惠券
            CouponService::GiftCoupons($activity,$order);
        }
    }

    /**
     * @Notes: 获取可用优惠券信息
     * @param int $mid 用户ID
     * @param int $user_level 用户级别
     * @param float $total_price 商品总价
     * @param array $goodslist 商品列表
     * @param array $couponlist 可用优惠券列表
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/30 15:46
     */
    public static function buildCouponData($mid, $user_level, $total_price, $goodslist, &$couponlist)
    {
        //查询可用的优惠券
        $couponlist = Db::table('store_coupon_user')
            ->where('mid', $mid)
            ->where('use_threshold', '<=', $total_price)
            ->where('coupon_start_time', '<', time())
            ->where('coupon_end_time', '>', time())
            ->where('status', '1')
            ->select();
        foreach ($couponlist as $key => $value) {
            $couponlist[$key]['coupon_start_time'] = date('Y-m-d H:i:s', $value['coupon_start_time']);
            $couponlist[$key]['coupon_end_time'] = date('Y-m-d H:i:s', $value['coupon_end_time']);
            if ($value['level_limits'] === 1) {
                if (!in_array($user_level, explode(',', $value['use_level']))) {
                    unset($couponlist[$key]);
                    continue;
                }
            }
            if ($value['coupon_auth_type'] === 2) {
                $cate_price = 0;
                foreach ($goodslist as $v) {
                    if (in_array($value['coupon_auth_cate'], $v['cate'])) {
                        $cate_price += $v[$v['price_field']]*$v['number'];
                    }
                }
                if ($cate_price <= $value['use_threshold']) {
                    unset($couponlist[$key]);
                    continue;
                } else {
                    $couponlist[$key]['preferential_principal'] = $cate_price;
                }
            } elseif ($value['coupon_auth_type'] == 3) {
                $brand_price = 0;
                foreach ($goodslist as $v) {
                    if ($value['coupon_auth_brand'] == $v['brand_id']) {
                        $brand_price += $v[$v['price_field']]*$v['number'];
                    }
                }
                if ($brand_price <= $value['use_threshold']) {
                    unset($couponlist[$key]);
                    continue;
                } else {
                    $couponlist[$key]['preferential_principal'] = $brand_price;
                }
            } elseif ($value['coupon_auth_type'] == 4) {
                $goods_price = 0;
                foreach ($goodslist as $v) {
                    if ($value['coupon_auth_goods'] == $v['goods_id']) {
                        $goods_price += $v[$v['price_field']]*$v['number'];
                    }
                }
                if ($goods_price <= $value['use_threshold']) {
                    unset($couponlist[$key]);
                    continue;
                } else {
                    $couponlist[$key]['preferential_principal'] = $goods_price;
                }
            }
        }
        $couponlist = array_values($couponlist);
        return ['code' => 1, 'msg' => '优惠券添加成功！'];
    }

    /**
     * @Notes: 添加商品
     * @param $item
     * @param $goodslist
     * @param $cateList
     * @param $goods_price
     * @param $real_price
     * @param $member_discount_amount
     * @param $goods_weight
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/30 17:14
     */
    public static function buildConfirmData($item, &$goodslist, $cateList, &$confirmData, $price_field)
    {
        list($mid, $goods_id, $goods_spec, $number) = [
            $item['mid'], $item['goods_id'], $item['goods_spec'], $item['goods_number'],
        ];
        // 商品主体信息
        $goodsField = 'goods_title,goods_logo,goods_image,cate_id,brand_id,weight,type_id,is_quantity,quantity_number,exemption_from_postage';
        $goodsWhere = ['id' => $goods_id, 'status' => '1', 'is_deleted' => '0'];
        if (!($goods = Db::name('StoreGoods')->field($goodsField)->where($goodsWhere)->find())) {
            return ['code' => 0, 'msg' => "无效的商品信息！", 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
        }
        //$goods['goods_logo'] = sysconf('applet_url') . $goods['goods_logo'];
        $goodsTypeField = 'id,type_title,image type_image,is_auth';
        $goodsTypeWhere = ['id' => $goods['type_id'], 'status' => '1', 'is_deleted' => '0'];
        $goodsType = Db::table('store_goods_type')->where($goodsTypeWhere)->field($goodsTypeField)->find();
        $goods['is_auth'] = isset($goodsType['is_auth']) ? $goodsType['is_auth'] : 0;
        $goods['goods_type_title'] = isset($goodsType['type_title']) ? $goodsType['type_title'] : '';
        $goods['goods_type_image'] = isset($goodsType['type_image']) ? $goodsType['type_image'] : '';
        //商品分类处理
        $goods['cate'] = [];
        if (isset($cateList[$goods['cate_id']])) {
            $tcate = $cateList[$goods['cate_id']];
            $goods['cate'][] = $tcate['id'];
            while (isset($tcate['pid']) && $tcate['pid'] > 0 && isset($cateList[$tcate['pid']])) {
                $tcate = $cateList[$tcate['pid']];
                $goods['cate'][] = $tcate['id'];
            }
            $goods['cate'] = array_reverse($goods['cate']);
        }

        // 商品规格信息
        $specField = 'goods_id,goods_spec,market_price,selling_price,goods_stock,goods_sale';
        $specWhere = ['status' => '1', 'is_deleted' => '0', 'goods_id' => $goods_id, 'goods_spec' => $goods_spec];
        if (!($goodsSpec = Db::name('StoreGoodsList')->field($specField)->where($specWhere)->find())) {
            return ['code' => 0, 'msg' => '无效的商品规格信息！', 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
        }
        if ($goodsSpec['goods_spec'] === 'default:default') {
            $goodsSpec['goods_spec_alias'] = '默认规格';
        } else {
            $goodsSpec['goods_spec_alias'] = str_replace([':', ','], [': ', ', '], $goodsSpec['goods_spec']);
        }
        $member_discount_amount = floatval(($goodsSpec['market_price'] - $goodsSpec[$price_field]) * $number);
        $goods_stock = $goodsSpec['goods_stock'] - $goodsSpec['goods_sale'];

        $goodsSpec['desc'] = '';
        //如果商品正在进行秒杀活动
        if ($spike = Db::table('store_goods_spike')->where(['goods_id' => $goods_id, 'status' => '1'])->where('activity_start_time', '<', date('Y-m-d H:i:s'))->where('activity_end_time', '>', date('Y-m-d H:i:s'))->where('stock', '>', 0)->find()) {
            $goods_stock = $goods_stock > $spike['stock'] ? $spike['stock'] : $goods_stock;
            $goodsSpec[$price_field] = $spike['activity_price'];
            $goodsSpec['desc'] = '限时抢购 ' . date('m月d日H:i:s', strtotime($spike['activity_end_time'])) . '结束';
            $member_discount_amount = 0;
        }
        // 商品库存检查
        if ($goods_stock < $number) {
            return ['code' => 0, 'msg' => $goods['goods_title'] . ' ' . $goodsSpec['goods_spec_alias'] . '库存不足(剩余：' . $goods_stock . ')，请调整数量或更换其它商品！', 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
        }
        $goodsSpec['price_field'] = $price_field;
        $goodslist[] = array_merge($goods, $goodsSpec, ['number' => (int)$number]);
        $confirmData['goods_price'] += floatval($goodsSpec[$price_field]) * $number;
        $confirmData['real_price'] += floatval($goodsSpec[$price_field]) * $number;
        if (!$goods['exemption_from_postage']) {
            $confirmData['goods_weight'] += floatval($goods['weight'] * $number);
        }

        $confirmData['member_discount_amount'] += $member_discount_amount;
        return ['code' => 1, 'msg' => '商品添加成功！'];

    }

    /**
     * @Notes: 创建订单
     * @param int $mid 用户ID
     * @param int $user_level 用户级别
     * @param string $params 商品参数规格 (商品ID@商品规格@购买数量;商品ID@商品规格@购买数量)
     * @param int $coupon_id 使用的优惠券ID 单独使用 不返还
     * @param int $addressId 收货地址ID
     * @param int $authentication_id 实名认证ID
     * @param string $orderDesc 订单描述
     * @param int $orderType 订单类型1普通 2秒杀 3团购 4 砍价
     * @param string $from 订单来源
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/3 16:40
     */
    public static function create($mid, $user_level, $params, $coupon_id, $addressId, $authentication_id, $orderDesc = '', $orderType = 1, $from = 'wechat')
    {
        /*是否享受会员价*/
        $is_insider = $user_level ? true : false;
        /* 计价字段 */
        $price_field = $is_insider ? 'selling_price' : 'market_price';
//        $price_field = 'selling_price' ;
        //商品分类信息
        $cateField = 'id,pid,cate_title,cate_desc';
        $cateWhere = ['status' => '1', 'is_deleted' => '0'];
        $cateList = Db::name('StoreGoodsCate')->where($cateWhere)->order('sort asc,id desc')->column($cateField);

        // 订单数据生成
        list($order_no, $orderList) = [DataService::createSequence(10, 'ORDER'), []];
        $order = ['mid' => $mid, 'order_no' => $order_no, 'real_price' => 0, 'goods_price' => 0, 'discount_amount' => 0, 'member_discount_amount' => 0, 'freight_price' => 0, 'goods_weight' => 0, 'is_auth' => 0, 'desc' => $orderDesc, 'type' => $orderType, 'from' => $from];
        foreach (explode(';', trim($params, ',;@')) as $param) {
            list($goods_id, $goods_spec, $number) = explode('@', "{$param}@@");
            $item = ['mid' => $mid, 'order_no' => $order_no, 'goods_id' => $goods_id, 'goods_spec' => $goods_spec, 'goods_number' => $number];
            $goodsResult = self::buildOrderData($item, $order, $orderList, $price_field, $cateList);
            if (empty($goodsResult['code'])) {
                return $goodsResult;
            }
        }

        // 生成优惠券信息
        $couponResult = self::buildCouponData($mid, $user_level, $order['goods_price'], $orderList, $couponlist);
        if (empty($couponResult['code'])) {
            return $couponResult;
        }
        //使用优惠券信息
        $use_coupon = [];
        if (!empty($coupon_id) && is_numeric($coupon_id)) {
            foreach ($couponlist as $coup) {
                if ($coup['id'] == $coupon_id) {
                    $use_coupon = $coup;
                    $order['discount_amount'] += $coup['coupon_quota'];
                    $order['coupon_id'] = $coupon_id;
                    $use_coupon['discount_amount'] = $coup['coupon_quota'];
                }
            }
        }
        $order['real_price'] -= $order['discount_amount'];
        $manjian_activity=self::manjian($user_level,$order['real_price']);
        if($manjian_activity){
            $order['real_price'] -= $manjian_activity['discount'];
            $order['manjian_money'] = $manjian_activity['discount'];
        }
        $order['real_price'] = $order['real_price'] < 0 ? 0 : $order['real_price'];

        //计算邮费
        $insider_exempt_postage = sysconf('insider_exempt_postage') ? sysconf('insider_exempt_postage') : '0';
        if (!$is_insider || !$insider_exempt_postage) {
            if (($order['real_price'] < sysconf('exemption_postage_price')) && $order['goods_weight'] > 0) {
                $order['freight_price'] += sysconf('basic_freight');
                if ($order['goods_weight'] > sysconf('basic_weight')) {
                    $order['freight_price'] += ($order['goods_weight'] - sysconf('basic_weight')) * sysconf('extra_freight');
                }
            }
            $order['real_price'] += $order['freight_price'];
        }
//        $order['freight_price']=sysconf('order_post_fee');
//        $order['real_price'] += $order['freight_price'];
        foreach ($orderList as $key => $item) {
            unset($orderList[$key]['cate']);
            unset($orderList[$key]['cate_id']);
            unset($orderList[$key]['brand_id']);
            unset($orderList[$key]['weight']);
            unset($orderList[$key]['type_id']);
            unset($orderList[$key]['is_quantity']);
            unset($orderList[$key]['quantity_number']);
            unset($orderList[$key]['exemption_from_postage']);
        }
        // 生成快递信息
        $expressResult = self::buildExpressData($order, $addressId, $authentication_id);
        if (empty($expressResult['code'])) {
            return $expressResult;
        }
        $order['is_pay'] = 0;
        if ($order['real_price'] == 0) {
            $order['is_pay'] = 1;
            $order['pay_at'] = date('Y-m-d H:i:s');
            $order['pay_type'] = 'integral';
            $order['status'] = '2';
        }
        unset($order['goods_weight']);
        unset($order['is_auth']);
        try {
            // 写入订单信息
            Db::transaction(function () use ($order, $orderList, $expressResult, $mid, $use_coupon) {
                Db::name('StoreOrder')->insert($order); // 主订单信息
                Db::name('StoreOrderGoods')->insertAll($orderList); // 订单关联的商品信息
                //删除购物车
                Db::name('storeGoodsCart')->where('mid',$mid)->where('goods_id','in',array_column($orderList,'goods_id'))->delete();
                Db::name('storeOrderExpress')->insert($expressResult['data']); // 快递信息
                if (!empty($use_coupon)) {
                    Db::name('StoreCouponUser')->where('id', $use_coupon['id'])->setField('status', '0');
                }
            });
            // 同步商品库存列表
            foreach (array_unique(array_column($orderList, 'goods_id')) as $stock_goods_id) {
                GoodsService::syncGoodsStock($stock_goods_id);
            }
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => '商城订单创建失败，请稍候再试！' . $e->getLine() . $e->getFile() . $e->getMessage()];
        }
        return ['code' => 1, 'msg' => '商城订单创建成功！', 'order_no' => $order_no, 'is_pay' => $order['is_pay']];
    }

    /**
     * 生成订单快递数据
     * @param array $order 订单主表记录
     * @param int $address_id 会员地址ID
     * @param int $express_id 快递信息ID
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function buildExpressData(&$order, $address_id, $authentication_id)
    {
        // 收货地址处理
        $addressWhere = ['mid' => $order['mid'], 'id' => $address_id, 'status' => '1', 'is_deleted' => '0'];
        $addressField = 'store_name express_store_name,username express_username,phone express_phone,province express_province,city express_city,area express_area,address express_address';
        if (!($address = Db::name('StoreMemberAddress')->field($addressField)->where($addressWhere)->find())) {
            return ['code' => 0, 'msg' => '收货地址数据异常！'];
        }
        // 实名认证处理
        if ($order['is_auth'] == '1') {
            if (empty($authentication_id)) {
                return ['code' => 0, 'msg' => '请选择实名认证！'];
            }
            $authenticationWhere = ['mid' => $order['mid'], 'id' => $authentication_id, 'status' => '1', 'is_deleted' => '0'];
            $authenticationField = 'username,id_card express_idcard,image_front express_idcard_front,image_other express_idcard_other';
            if (!($authentication = Db::name('StoreMemberAuthentication')->field($authenticationField)->where($authenticationWhere)->find())) {
                return ['code' => 0, 'msg' => '收货地址数据异常！'];
            }
            if ($address['express_username'] != $authentication['username']) {
                return ['code' => 0, 'msg' => '收货人姓名和实名认证姓名不一致！'];
            }
            $address['express_idcard'] = $authentication['express_idcard'];
            $address['express_idcard_front'] = $authentication['express_idcard_front'];
            $address['express_idcard_other'] = $authentication['express_idcard_other'];
        }
        $extend = ['mid' => $order['mid'], 'order_no' => $order['order_no'], 'type' => 0];
        return ['code' => 1, 'data' => array_merge($address, $extend), 'msg' => '生成快递信息成功！'];
    }

    /**
     * 订单数据生成
     * @param array $item 订单单项参数
     * (mid,type,order_no,goods_id,goods_spec,goods_number)
     * @param array $order 订单主表
     * @param array $orderList 订单详细表
     * @param string $price_field 实际计算单价字段
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private static function buildOrderData($item, &$order, &$orderList, $price_field = 'selling_price', $cateList)
    {
        list($mid, $order_no, $goods_id, $goods_spec, $number) = [
            $item['mid'], $item['order_no'], $item['goods_id'], $item['goods_spec'], $item['goods_number'],
        ];
        // 商品主体信息
        $goodsField = 'goods_title,goods_logo,goods_image,cate_id,brand_id,weight,type_id,depot_id,is_quantity,quantity_number,exemption_from_postage,rebate_template_id';
        $goodsWhere = ['id' => $goods_id, 'status' => '1', 'is_deleted' => '0'];
        if (!($goods = Db::name('StoreGoods')->field($goodsField)->where($goodsWhere)->find())) {
            return ['code' => 0, 'msg' => "无效的商品信息！", 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
        }
        $goodsTypeField = 'id,is_auth';
        $goodsTypeWhere = ['id' => $goods['type_id'], 'status' => '1', 'is_deleted' => '0'];
        $goodsType = Db::table('store_goods_type')->where($goodsTypeWhere)->field($goodsTypeField)->find();
        if (!empty($goodsType)) {
            $order['is_auth'] = $goodsType['is_auth'];
        }
        $goodsDepotField = 'id,depot_title';
        $goodsDepotWhere = ['id' => $goods['depot_id'], 'status' => '1', 'is_deleted' => '0'];
        $goodsDepot = Db::table('store_goods_depot')->where($goodsDepotWhere)->field($goodsDepotField)->find();
        if (!empty($goodsDepot)) {
            $goods['depot_id'] = $goodsDepot['id'];
            $goods['depot_title'] = $goodsDepot['depot_title'];
        }
        //商品分类处理
        $goods['cate'] = [];
        if (isset($cateList[$goods['cate_id']])) {
            $tcate = $cateList[$goods['cate_id']];
            $goods['cate'][] = $tcate['id'];
            while (isset($tcate['pid']) && $tcate['pid'] > 0 && isset($cateList[$tcate['pid']])) {
                $tcate = $cateList[$tcate['pid']];
                $goods['cate'][] = $tcate['id'];
            }
            $goods['cate'] = array_reverse($goods['cate']);
        }
        // 商品规格信息
        $specField = 'goods_id,goods_spec,market_price,selling_price,cost_price,goods_stock,goods_sale';
        $specWhere = ['status' => '1', 'is_deleted' => '0', 'goods_id' => $goods_id, 'goods_spec' => $goods_spec];
        if (!($goodsSpec = Db::name('StoreGoodsList')->field($specField)->where($specWhere)->find())) {
            return ['code' => 0, 'msg' => '无效的商品规格信息！', 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
        }
        $member_discount_amount = floatval($goodsSpec['market_price'] - $goodsSpec[$price_field]) * $number;
        /*计算商品规格的剩余库存*/
        $goods_stock = $goodsSpec['goods_stock'] - $goodsSpec['goods_sale'];
        //如果商品正在进行秒杀活动
        $spike = Db::table('store_goods_spike')
            ->where(['goods_id' => $goods_id, 'status' => '1'])
            ->where('activity_start_time', '<', date('Y-m-d H:i:s'))
            ->where('activity_end_time', '>', date('Y-m-d H:i:s'))
            ->where('stock', '>', 0)
            ->find();
        if ($spike) {

            /*当前用户已购数量*/
            $buy_count = Db::table('store_goods_spike_user')->alias('a')
                ->join('store_order b', 'a.order_no = b.order_no')
                ->where('b.status', 'in', [1, 2, 3, 4, 5])
                ->where(['a.mid' => $mid, 'a.spike_id' => $spike['id']])
                ->sum('a.buy_count');
            if ($spike['activity_quantity'] < $number + $buy_count) {
                return ['code' => 0, 'msg' => '每人限购' . $spike['activity_quantity'] . '件！', 'data' => "{$goods_id}, {$goods_spec}, {$number}"];

            }

            $goods_stock = $spike['stock'] = $goods_stock > $spike['stock'] ? $spike['stock'] : $goods_stock;
            $goodsSpec[$price_field] = $spike['activity_price'];
            try {
                Db::transaction(function () use ($spike, $number, $mid, $order_no) {
                    $seckill = Db::table('store_goods_spike')
                        ->where('id', $spike['id'])
                        ->where('activity_start_time', '<', date('Y-m-d H:i:s'))
                        ->where('activity_end_time', '>', date('Y-m-d H:i:s'))
                        ->where('stock', '>', 0)
                        ->lock(true)
                        ->find();
                    if (empty($seckill)) {
                        throw new Exception('无效的商品！');
                    }
                    if ($seckill['stock'] <= $number || $spike['stock'] <= $number) {
                        throw new Exception('商品剩余' . $spike['stock'] . '件！');
                    }
                    Db::table('store_goods_spike')->where('id', $spike['id'])->setDec('stock', $number);
                    Db::table('store_goods_spike_user')->insert([
                        'mid' => $mid,
                        'spike_id' => $spike['id'],
                        'buy_count' => $number,
                        'order_no' => $order_no
                    ]);
                });
            } catch (\Exception $e) {
                return ['code' => 0, 'msg' => '网络繁忙，请稍后再试！', 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
            }
            $member_discount_amount = 0;
        } else {
            if ($goods['is_quantity']) {
                $goods_order_sum = Db::table('store_order_goods')->alias('a')
                    ->join('store_order b', 'a.order_no = b.order_no')
                    ->where('b.mid', $mid)
                    ->where('b.status', 'in', [1, 2, 3, 4, 5])
                    ->where(['a.goods_id' => $goods_id])
                    ->sum('number');
                if ($goods_order_sum + $number > $goods['quantity_number']) {
                    return ['code' => 0, 'msg' => "超过限制购买数量！", 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
                }
            }
        }
        // 商品库存检查
        if ($goods_stock < $number) {
            return ['code' => 0, 'msg' => '商品库存不足，请更换其它商品！', 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
        }


        // 订单价格处理
        $goodsSpec['price_field'] = $price_field;
        unset($goodsSpec['goods_stock']);
        unset($goodsSpec['goods_sale']);
        $orderList[] = array_merge($goods, $goodsSpec, ['mid' => $mid, 'number' => $number, 'order_no' => $order_no]);
        $order['goods_price'] += floatval($goodsSpec[$price_field]) * $number;
        $order['real_price'] += floatval($goodsSpec[$price_field]) * $number;
        $order['member_discount_amount'] += $member_discount_amount;
        if (!$goods['exemption_from_postage']) {
            $order['goods_weight'] += floatval($goods['weight']) * $number;
        }
        return ['code' => 1, 'msg' => '商品添加到订单成功！'];
    }

    /**
     * 订单主表数据处理
     * @param array $list
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function buildOrderList(&$list)
    {
        $orderNos = array_unique(array_column($list, 'order_no'));
        $goodsList = Db::name('StoreOrderGoods')->whereIn('order_no', $orderNos)->select();
        $expressList = Db::name('StoreOrderExpress')->whereIn('order_no', $orderNos)->select();
        foreach ($list as $key => $vo) {
            list($list[$key]['goods'], $list[$key]['express']) = [[], []];
            foreach ($expressList as $express) {
                ($vo['order_no'] === $express['order_no']) && $list[$key]['express'] = $express;
            }
            foreach ($goodsList as $goods) {
                $goods['goods_logo'] = sysconf('applet_url') . $goods['goods_logo'];
                if ($goods['goods_spec'] === 'default:default') {
                    $goods['goods_spec_alias'] = '默认规格';
                } else {
                    $goods['goods_spec_alias'] = str_replace([':', ','], ['：', '，'], $goods['goods_spec']);
                }
                ($vo['order_no'] === $goods['order_no']) && $list[$key]['goods'][] = $goods;
            }
        }
        return $list;
    }

}