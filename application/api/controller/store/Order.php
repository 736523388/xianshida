<?php
/**
 * Created by PhpStorm.
 * User: forska
 * Date: 2018/11/8
 * Time: 11:37
 */

namespace app\api\controller\store;

use app\api\controller\BasicUserApi;
use app\api\service\IntegralService;
use app\api\service\OrderService;
use app\api\service\WeChatPayService;
use app\store\service\GoodsService;
use service\DataService;
use service\HttpService;
use service\KdniaoService;
use think\Db;
use think\Exception;
use think\exception\HttpResponseException;

class Order extends BasicUserApi
{
    /**
     * @Notes: 确认订单
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/30 17:33
     */
    public function confirm(){
        $data = (string)$this->request->param('data');
        $coupon_id = (int)$this->request->param('coupon_id',0);
        $result = OrderService::confirmOrder(UID,USER_LEVEL, $data, $coupon_id);
        return json($result);
    }

    /**
     * @Notes: 创建订单
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/3 16:42
     */
    public function create(){
        /*产品参数*/
        $data = (string)$this->request->param('data');
        /*优惠券ID*/
        $coupon_id = (int)$this->request->param('coupon_id',0);
        /*收货地址ID*/
        $address_id = (int)$this->request->param('address_id',0);
        /*实名认证ID*/
        $authentication_id = (int)$this->request->param('authentication_id',0);
        /*订单描述*/
        $orderDesc = (string)$this->request->param('order_desc','');
        $result = OrderService::create(UID, USER_LEVEL ,$data,$coupon_id,$address_id,$authentication_id,$orderDesc);
        return json($result);
    }

    /**
     * 团购订单确认
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function confirm_group_order(){
        $data = $this->request->param('data','');
        list($goods_id, $goods_spec, $number) = explode('@', "{$data}@@");
        /*检测购买商品*/
        $goodsField = 'goods_title,goods_logo,goods_image,cate_id,brand_id,weight,type_id,depot_id';
        $goodsWhere = ['id' => $goods_id, 'status' => '1', 'is_deleted' => '0'];
        if (!($goods = Db::name('StoreGoods')->field($goodsField)->where($goodsWhere)->find())) {
            $this->error('无效的商品信息');
        }
        /*商品类型信息*/
        $goodsTypeField = 'id,is_auth';
        $goodsTypeWhere = ['id' => $goods['type_id'],'status' => '1','is_deleted' => '0'];
        $goodsType = Db::table('store_goods_type')->where($goodsTypeWhere)->field($goodsTypeField)->find();
        $goods['is_auth'] = isset($goodsType['is_auth']) ? $goodsType['is_auth'] : 0;
        /*商品规格信息*/
        $specField = 'goods_id,goods_spec,market_price,selling_price,cost_price,goods_stock,goods_sale';
        $specWhere = ['status' => '1', 'is_deleted' => '0', 'goods_id' => $goods_id, 'goods_spec' => $goods_spec];
        if (!($goodsSpec = Db::name('StoreGoodsList')->field($specField)->where($specWhere)->find())) {
            $this->error('无效的商品规格信息！');
        }
        $goods_group = Db::table('store_goods_group')
            ->where('activity_start_time','<',date('Y-m-d H:i:s'))
            ->where('activity_end_time','>',date('Y-m-d H:i:s'))
            ->where('stock','>',0)
            ->where('goods_id',$goods_id)
            ->where('status',1)
            ->find();
        empty($goods_group) && $this->error('无效的团购信息！');
        $goods = array_merge($goods,$goodsSpec);
        $goods['goods_logo'] = sysconf('applet_url') . $goods['goods_logo'];
        if ($goods['goods_spec'] === 'default:default') {
            $goods['goods_spec_alias'] = '默认规格';
        } else {
            $goods['goods_spec_alias'] = str_replace([':', ','], [': ', ', '], $goods['goods_spec']);
        }
        $goods['goods_number'] = $number;
        $this->success('success',[
            'activity_info' => $goods_group,
            'goods' => $goods,
            'total_price' => sprintf("%.2f",$goods_group['activity_price'] * $number)
        ]);
    }
    /**
     * @Notes: 创建团购订单
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/16 11:37
     */
    public function create_group_order(){
        $data = $this->request->param('data','');
        list($goods_id, $goods_spec, $number) = explode('@', "{$data}@@");
        if($now_group = Db::table('store_goods_group_pre')
            ->where('mid',UID)
            ->where('goods_id',$goods_id)
            ->where('success_time','0')
            ->find()){
            return json(['code' => -2,'msg' => '正在团购中!','id' => $now_group['id']]);
        }
        /*检测购买商品*/
        $goodsField = 'goods_title,goods_logo,goods_image,cate_id,brand_id,weight,type_id,depot_id,rebate_template_id';
        $goodsWhere = ['id' => $goods_id, 'status' => '1', 'is_deleted' => '0'];
        if (!($goods = Db::name('StoreGoods')->field($goodsField)->where($goodsWhere)->find())) {
            $this->error('无效的商品信息');
        }
        /*商品类型信息*/
        $goodsTypeField = 'id,is_auth';
        $goodsTypeWhere = ['id' => $goods['type_id'],'status' => '1','is_deleted' => '0'];
        $goodsType = Db::table('store_goods_type')->where($goodsTypeWhere)->field($goodsTypeField)->find();
        $goods['is_auth'] = isset($goodsType['is_auth']) ? $goodsType['is_auth'] : 0;
        /*商品仓库信息*/
        $goodsDepotField = 'id,depot_title';
        $goodsDepotWhere = ['id' => $goods['depot_id'],'status' => '1','is_deleted' => '0'];
        $goodsDepot = Db::table('store_goods_depot')->where($goodsDepotWhere)->field($goodsDepotField)->find();
        $goods['depot_title'] = isset($goodsDepot['depot_title']) ? $goodsDepot['depot_title'] : '';
        /*商品规格信息*/
        $specField = 'goods_id,goods_spec,market_price,selling_price,cost_price,goods_stock,goods_sale';
        $specWhere = ['status' => '1', 'is_deleted' => '0', 'goods_id' => $goods_id, 'goods_spec' => $goods_spec];
        if (!($goodsSpec = Db::name('StoreGoodsList')->field($specField)->where($specWhere)->find())) {
            $this->error('无效的商品规格信息！');
        }
        /*检测拼团*/
        $goods_group = Db::table('store_goods_group')
            ->where('activity_start_time','<',date('Y-m-d H:i:s'))
            ->where('activity_end_time','>',date('Y-m-d H:i:s'))
            ->where('stock','>',0)
            ->where('goods_id',$goods_id)
            ->find();
        empty($goods_group) && $this->error('无效的团购信息！');
        /*限购处理*/
        if($number > $goods_group['activity_quantity']){
            $this->error('每单限购'.$goods_group['activity_quantity'].'件！');
        }
        $parent_id = $this->request->param('parent_id',0);
        if(!empty($parent_id)){
            $parent_group = Db::table('store_goods_group_pre')
                ->alias('a')
                ->where('id',$parent_id)
                ->where('mid','<>',UID)
                ->where('goods_id',$goods_id)
                ->where('success_time','0')
                ->find();
            empty($parent_group) && $this->error('拼团信息出错！');
            $group_total_count = Db::table('store_goods_group_pre')
                ->alias('a')
                ->join('store_order b','b.order_no = a.order_no')
                ->where('parent_id',$parent_id)
                ->where('success_time','0')
                ->count();
            if($group_total_count + 1 > $goods_group['complete_num']){
                $this->error('参团人数已满！');
            }
        }
        $goods_stock = $goodsSpec['goods_stock'] - $goodsSpec['goods_sale'];
        $goods_stock = $goods_stock > $goods_group['stock'] ? $goods_group['stock'] : $goods_stock;
        if($goods_stock < $number){
            $this->error('商品库存不足！');
        }
        $order_no = DataService::createSequence(10,'ORDER');
        $goodsGroupPre = [
            'parent_id' => $parent_id,
            'mid' => UID,
            'order_no' => $order_no,
            'goods_id' => $goods_id,
            'activity_id' => $goods_group['id'],
            'end_time' => sysconf('group_hour') * 60 *60 + time(),
            'create_at' => time(),
            'goods_number' => $number,
            'goods_spec' => $goods_spec,
        ];
        $order = [
            'type' => 2,
            'mid' => UID,
            'order_no' => $order_no,
            'goods_price' => $goods_group['activity_price'] * $number,
            'real_price' => $goods_group['activity_price'] * $number,
            'is_auth' => $goods['is_auth']
        ];
        $orderGoodsList = [
            'mid' => UID,
            'order_no' => $order_no,
            'goods_id' => $goods_id,
            'goods_title' => $goods['goods_title'],
            'goods_spec' => $goods_spec,
            'goods_logo' => $goods['goods_logo'],
            'goods_image' => $goods['goods_image'],
            'depot_id' => $goods['depot_id'],
            'depot_title' => $goods['depot_title'],
            'market_price' => $goods_group['activity_price'],
            'selling_price' => $goodsSpec['selling_price'],
            'cost_price' => $goodsSpec['cost_price'],
            'price_field' => 'market_price',
            'rebate_template_id' => $goods['rebate_template_id'],
            'number' => $number
        ];
        /*收货地址ID*/
        $address_id = (int)$this->request->param('address_id',0);
        /*实名认证ID*/
        $authentication_id = (int)$this->request->param('authentication_id',0);
        /*订单描述*/
        $orderDesc = (string)$this->request->param('order_desc','');
        $order['desc'] = $orderDesc;
        // 生成快递信息
        $expressResult = OrderService::buildExpressData($order, $address_id, $authentication_id);
        empty($expressResult['code']) && $this->error($expressResult['msg']);
        unset($order['is_auth']);
        try{
            Db::transaction(function() use($goodsGroupPre,$order,$orderGoodsList,$goods_group,$goods_id,$number,$expressResult){
                /*减少团购库存*/
                Db::table('store_goods_group')->where('id',$goods_group['id'])->setDec('stock',$number);
                /*添加团购PRE*/
                Db::table('store_goods_group_pre')->insert($goodsGroupPre);
                /*插入订单*/
                Db::table('store_order')->insert($order);
                /*插入订单产品*/
                Db::table('store_order_goods')->insert($orderGoodsList);
                Db::name('storeOrderExpress')->insert($expressResult['data']); // 快递信息
                GoodsService::syncGoodsStock($goods_id);
            });
        }catch (\Exception $e){
            $this->error('团购订单创建失败，请稍候再试！' . $e->getLine() . $e->getFile() . $e->getMessage());
        }
        $this->success('团购订单创建成功！', ['order_no' => $order_no]);

    }

    /**
     * @Notes: 拼团详情
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/31 16:00
     */
    public function group_detail(){
        $id = $this->request->param('id');
        $info = Db::table('store_goods_group_pre')
            ->alias('a')
            ->join('store_order order','order.order_no = a.order_no')
            ->join('store_goods goods','goods.id = a.goods_id')
            ->join('store_goods_list goods_spec','goods_spec.goods_id = a.goods_id and goods_spec.goods_spec = a.goods_spec')
            ->join('store_goods_group group','group.id = a.activity_id')
            ->where('a.id',$id)
            ->where('a.success_time',0)
            ->field('a.*,order.is_pay,goods.goods_title,goods.goods_logo,goods.service_txt,goods.goods_desc,group.complete_num,group.activity_price,goods_spec.market_price')
            ->find();
        empty($info) && $this->error('拼团信息错误！');
        $info['goods_logo'] = sysconf('applet_url') . $info['goods_logo'];
        $info['service_txt'] = array_filter(explode('|',$info['service_txt']));
        $info['after_time'] = $info['end_time'] - time();
        $parent_id = $info['parent_id'] ? $info['parent_id'] : $info['id'];
        $grouping = self::getGroupIng($parent_id);
        $League_num = count($grouping);
        $info['residue'] = $info['complete_num'] - $League_num;
        $info['grouping'] = $grouping;
        $is_ke = true;
        foreach ($grouping as $item) {
            if($item['mid'] == UID){
                $is_ke = false;
            }
        }
        $info['is_ke'] = $is_ke && $info['residue'];
        $this->success('success',$info);
    }

    /**
     * @Notes: 团购团队列表
     * @param $parent_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/31 17:52
     */
    public static function getGroupIng($parent_id){
        $parent = Db::table('store_goods_group_pre')
            ->alias('a')
            ->join('store_member m','m.id = a.mid')
            ->where('a.success_time','>=',0)
            ->where('a.id',$parent_id)
            ->field('a.parent_id,m.id mid,m.headimg,m.nickname')
            ->find();
        $child = (array)Db::table('store_goods_group_pre')
            ->alias('a')
            ->join('store_member m','m.id = a.mid')
            ->where('a.success_time','>=',0)
            ->where('a.parent_id',$parent_id)
            ->field('a.parent_id,m.id mid,m.headimg,m.nickname')
            ->select();
        array_unshift($child,$parent);
        return $child;
    }
    /**
     * @Notes: 砍价订单确认
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/26 17:20
     */
    public function confirm_bargain_order(){
        $id = $this->request->param('id');
        $bargin_info = Db::table('store_order_bargain')
            ->where('id',$id)
            ->where('mid',UID)
            ->where('order_no','')
            ->where('success_time','>',0)
            ->find();
        empty($bargin_info) && $this->error('砍价信息错误！');
        /*检测购买商品*/
        $goodsField = 'goods_title,goods_logo,goods_image,cate_id,brand_id,weight,type_id,depot_id';
        $goodsWhere = ['id' => $bargin_info['goods_id'], 'status' => '1', 'is_deleted' => '0'];
        if (!($goods = Db::name('StoreGoods')->field($goodsField)->where($goodsWhere)->find())) {
            $this->error('无效的商品信息！');
        }
        /*商品类型信息*/
        $goodsTypeField = 'id,is_auth';
        $goodsTypeWhere = ['id' => $goods['type_id'],'status' => '1','is_deleted' => '0'];
        $goodsType = Db::table('store_goods_type')->where($goodsTypeWhere)->field($goodsTypeField)->find();
        $goods['is_auth'] = isset($goodsType['is_auth']) ? $goodsType['is_auth'] : 0;

        /*商品规格信息*/
        $specField = 'goods_id,goods_spec,market_price,selling_price,cost_price,goods_stock,goods_sale';
        $specWhere = ['status' => '1', 'is_deleted' => '0', 'goods_id' => $bargin_info['goods_id'], 'goods_spec' => $bargin_info['goods_spec']];
        if (!($goodsSpec = Db::name('StoreGoodsList')->field($specField)->where($specWhere)->find())) {
            $this->error('无效的商品规格信息！');
        }
        $goods = array_merge($goods,$goodsSpec);
        $goods['goods_logo'] = sysconf('applet_url') . $goods['goods_logo'];
        $this->success('success',[
            'bargain_info' => $bargin_info,
            'goods' => $goods
        ]);
    }
    /**
     * @Notes: 创建砍价订单
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/17 15:19
     */
    public function create_bargain_order(){
        $bargain_id = $this->request->param('bargain_id');
        $bargain_order = Db::table('store_order_bargain')
            ->where('id',$bargain_id)
            ->where('mid',UID)
            ->where('order_no','')
            ->where('success_time','>',0)
            ->find();
        empty($bargain_order) && $this->error('砍价信息错误！');
        /*检测购买商品*/
        $goodsField = 'goods_title,goods_logo,goods_image,cate_id,brand_id,weight,type_id,depot_id,rebate_tempalte_id';
        $goodsWhere = ['id' => $bargain_order['goods_id'], 'status' => '1', 'is_deleted' => '0'];
        if (!($goods = Db::table('store_goods')->field($goodsField)->where($goodsWhere)->find())) {
            $this->error('无效的商品信息');
        }
        /*商品类型信息*/
        $goodsTypeField = 'id,is_auth';
        $goodsTypeWhere = ['id' => $goods['type_id'],'status' => '1','is_deleted' => '0'];
        $goodsType = Db::table('store_goods_type')->where($goodsTypeWhere)->field($goodsTypeField)->find();
        $goods['is_auth'] = isset($goodsType['is_auth']) ? $goodsType['is_auth'] : 0;
        /*商品仓库信息*/
        $goodsDepotField = 'id,depot_title';
        $goodsDepotWhere = ['id' => $goods['depot_id'],'status' => '1','is_deleted' => '0'];
        $goodsDepot = Db::table('store_goods_depot')->where($goodsDepotWhere)->field($goodsDepotField)->find();
        $goods['depot_title'] = isset($goodsDepot['depot_title']) ? $goodsDepot['depot_title'] : '';
        /*商品规格信息*/
        $specField = 'goods_id,goods_spec,market_price,selling_price,cost_price,goods_stock,goods_sale';
        $specWhere = ['status' => '1', 'is_deleted' => '0', 'goods_id' => $bargain_order['goods_id'], 'goods_spec' => $bargain_order['goods_spec']];
        if (!($goodsSpec = Db::name('StoreGoodsList')->field($specField)->where($specWhere)->find())) {
            $this->error('无效的商品规格信息！');
        }
        $goods = array_merge($goods,$goodsSpec);
        $order_no = DataService::createSequence(10,'ORDER');
        $order = [
            'type' => 3,
            'mid' => UID,
            'order_no' => $order_no,
            'goods_price' => $bargain_order['now_price'],
            'real_price' => $bargain_order['now_price'],
            'is_auth' => $goods['is_auth'],
            'is_pay' => 0
        ];
        if($bargain_order['now_price'] == 0){
            $order['is_pay'] = 1;
            $order['pay_type'] = 'wechat';
            $order['pay_price'] = 0;
            $order['pay_at'] = date('Y-m-d H:i:s');
            $order['status'] = 2;
        }
        $orderGoodsList = [
            'mid' => UID,
            'order_no' => $order_no,
            'goods_id' => $bargain_order['goods_id'],
            'goods_title' => $goods['goods_title'],
            'goods_spec' => $bargain_order['goods_spec'],
            'goods_logo' => $goods['goods_logo'],
            'goods_image' => $goods['goods_image'],
            'depot_id' => $goods['depot_id'],
            'depot_title' => $goods['depot_title'],
            'market_price' => $bargain_order['now_price'],
            'selling_price' => $goodsSpec['selling_price'],
            'cost_price' => $goodsSpec['cost_price'],
            'price_field' => 'market_price',
            'rebate_template_id' => $goods['rebate_template_id'],
            'number' => 1
        ];
        /*收货地址ID*/
        $address_id = (int)$this->request->param('address_id',0);
        /*实名认证ID*/
        $authentication_id = (int)$this->request->param('authentication_id',0);
        /*订单描述*/
        $orderDesc = (string)$this->request->param('order_desc','');
        $order['desc'] = $orderDesc;
        // 生成快递信息
        $expressResult = OrderService::buildExpressData($order, $address_id, $authentication_id);
        empty($expressResult['code']) && $this->error($expressResult['msg']);
        unset($order['is_auth']);
        //$this->error($order,$orderGoodsList,$expressResult);
        try{
            Db::transaction(function() use($bargain_id,$order,$orderGoodsList,$expressResult){
                /*插入订单*/
                Db::table('store_order')->insert($order);
                /*插入订单产品*/
                Db::table('store_order_goods')->insert($orderGoodsList);
                Db::name('storeOrderExpress')->insert($expressResult['data']); // 快递信息
                Db::table('store_order_bargain')->where('id',$bargain_id)->setField(['order_no' => $order['order_no']]);
                GoodsService::syncGoodsStock($orderGoodsList['goods_id']);
            });
        }catch (\Exception $e){
            $this->error('砍价订单创建失败，请稍候再试！' . $e->getLine() . $e->getFile() . $e->getMessage());
        }
        $this->success('砍价订单创建成功！', ['order_no' => $order_no,'is_pay' => $order['is_pay']]);
    }
    /**
     * @Notes: 取消订单
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/3 16:53
     */
    public function cancel(){
        $order_id = $this->request->param('order_id');
        $orderWhere = ['id' => $order_id,'mid' => UID,'status' => '1','is_pay' => '0'];
        if(!($order = Db::name('StoreOrder')->where($orderWhere)->find())){
            $this->error('非法操作');
        }
        try{
            Db::transaction(function() use($order,$orderWhere){
                Db::name('StoreOrder')->where($orderWhere)->setField('status','0');
                if($order['coupon_id']>0){
                    Db::name('StoreCouponUser')->where('id', $order['coupon_id'])->setField('status', 1);
                }
                if($order['use_integral'] > 0){
                    Db::table('store_member')->where('id',$order['mid'])->setInc('integral',$order['use_integral']);
                    IntegralService::RecordLog($order['mid'],$order['use_integral'],'取消订单');
                }
            });

        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('取消订单失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('取消订单成功');

    }

    /**
     * @Notes: 获取支付参数
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/29 16:21
     */
    public function createGoodsParams(){
        $order_no = $this->request->param('order_no');
        $order = Db::table('store_order')->where('order_no',$order_no)->where(['is_pay' => '0','is_deleted' => '0','status' => '1','mid' => UID])->find();
        empty($order) && $this->error('请求错误！');
        $options = [
            'body' => '购买商品',
            'out_trade_no' => $order['order_no'],
            'total_fee' => $order['real_price'] * 100,
            'openid' => Db::table('store_member')->where('id',UID)->value('openid')
        ];
        $result = WeChatPayService::createGoodsOrder($options);
        if($result['code'] === 1){
            Db::table('store_order')->where('order_no',$order_no)->setField(['prepay_id' => $result['result']['prepay_id']]);
            unset($result['result']);
        }
        return json($result);
    }

    /**
     * @Notes: 我的订单列表
     * @param int status 1 2 3 4 (待付款，待发货，待收货，待评价)
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/3 19:45
     */
    public function index(){
        $status = $this->request->param('status','0');
        $db = Db::table('store_order')->where(['is_deleted' => '0','mid' => UID]);
        if(!empty($status) && is_numeric($status)){
            if(in_array($status,[1,2,3])) $db->where('status',$status);
            if($status == 4) $db->where('status','in',[4,5])->where('is_comment','0');
        }else{
            $db->where('status','in',[1,2,3,4,5]);
        }
        $list = $db->order('create_at desc')->select();
        $db->setField(['is_see' => '1']);
        OrderService::buildOrderList($list);
        foreach ($list as &$item) {
            if($item['type'] == 2){
                $order_group = Db::table('store_goods_group_pre')->where('order_no',$item['order_no'])->find();
                $item['success_time'] = isset($order_group['success_time']) ? $order_group['success_time'] : -1;
            }
        }
        $this->success('success',$list);
    }
    public function order_count(){
        $noPayCount = Db::table('store_order')->where(['is_deleted' => '0','mid' => UID])->where('status',1)->count();
        $noShippingCount = Db::table('store_order')->where(['is_deleted' => '0','mid' => UID])->where('status',2)->count();
        $noReceivingCount = Db::table('store_order')->where(['is_deleted' => '0','mid' => UID])->where('status',3)->count();
        $noCommentCount = Db::table('store_order')->where(['is_deleted' => '0','mid' => UID])->where('status','in',[4,5])->where('is_comment','0')->where('is_see','0')->count();
        $this->success('success',['noPayCount' => $noPayCount,'noShippingCount' => $noShippingCount,'noReceivingCount' => $noReceivingCount,'noCommentCount' => $noCommentCount]);
    }
    /**
     * @Notes: 订单详情
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/3 19:57
     */
    public function detail(){
        $order_id = $this->request->param('order_id');
        $db = Db::name('StoreOrder')->where(['id' => $order_id,'mid' => UID,'is_deleted' => '0'])->where('status','in',[1,2,3,4,5]);
        $order = $db->select();
        OrderService::buildOrderList($order);
        $this->success('success',$order[0]);
    }

    /**
     * @Notes: 确认收货
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/6 15:52
     */
    public function receiving(){
        $order_id = $this->request->param('order_id');
        $re = Db::table('store_order')->where(['id' => $order_id,'mid' => UID,'is_deleted' => '0','status' => '3'])->setField('status','4');
        if($re){
            $this->success('收货成功');
        }else{
            $this->error('error');
        }
    }

    /**
     * 订单评价
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function evalute(){
        $goods_score=input('goods_score');//goods_score[goods_id] 每个产品必须选择

        if($goods_score){
            $goods_score=json_decode($goods_score,true);
        }
        $evalute=input('evalute');//evalute[goods_id]
        if($evalute){
            $evalute=json_decode($evalute,true);
        }
        $goods_pic=input('goods_pic');//产品图片 goods_pic[goods_id][]
        if($goods_pic){
            $goods_pic=json_decode($goods_pic,true);
        }

        //商家
        $logistics_score=input('logistics_score',5);
        $service_score=input('service_score',5);
        $oid=input('order_id');//订单ID


        //查询该订单
//        $oiwhr['id']=array('eq',$oid);
//        $oiwhr['evaluate_status']=array('eq',0);
//        $oiwhr['shipping_status']=array('eq',2);
        $order_info=Db::name('store_order')
            ->where('id',$oid)
            ->where('is_comment',0)
            ->where('status','in',[4,5])
            ->find();
        if(!$order_info){
            $this->error('您无权评价该订单');
        }

        foreach ($goods_score as $k=>$v){
            //$v['goods_id']
            /*dump($evalute[$k]);
            dump($goods_pic[$k]);*/
            $ge_data['goods_id']=$k;
            $ge_data['goods_score']=$v;
            $ge_data['service_score']=$service_score;
            $ge_data['express_score']=$logistics_score;
            $ge_data['describe']=isset($evalute[$k])?$evalute[$k]:'';
            $ge_data['image']=isset($goods_pic[$k])?implode('|',$goods_pic[$k]):'';
            $ge_data['mid']=UID;
            $ge_data['status']=0;
            $ge_data['is_deleted']=0;
            $ge_data['create_at']=date('Y-m-d H:i:s');
            $dataList[]=$ge_data;
        }
        //批量添加产品评论
        $ge_res=Db::name('store_goods_comment')->insertAll($dataList);
        if($ge_res>0){
            //将该订单置为已评价
            Db::name('store_order')
                ->where('id',$oid)
                ->where('is_comment',0)
                ->where('status','in',[4,5])
                ->setField('is_comment',1);
            $this->success('评价成功');
        }else{
            $this->error('评价失败，请联系管理员');
        }

    }

    /**
     * 申请退款|退货
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function back_order(){
        $post=$this->request->only(
            ['order_no','is_back_goods','remark','reason','images',
                'express_username','express_phone','express_province','express_city','express_area','express_address',
                'send_no','send_company_title','send_company_code',
                'send_username','send_phone','send_province','send_city','send_area','send_address'
            ],'post');
        //查询该订单
        $order=Db::name('store_order')->where('order_no',$post['order_no'])->where('mid',UID)->where('status','in',[2,3,4])->find();
        if(empty($order)){
            $this->error('该订单无法发起退款');
        }
        //验证数据合法性
        $validate=new \app\api\validate\Order();
        if(isset($post['is_back_goods'])&&$post['is_back_goods']==1){
            $validate->scene('backgoods');
        }
        if(false===$validate->check($post)){
            $this->error($validate->getError());
        }
        Db::transaction(function () use ($post){
           //插入退单记录
            $ob_data['mid']=UID;
            $ob_data['order_no']=$post['order_no'];
            $ob_data['is_back_goods']=$post['is_back_goods'];
            $ob_data['status']=0;
            $ob_data['create_at']=date('Y-m-d H:i:s');
            $ob_data['remark']=$post['remark'];
            $ob_data['reason']=$post['reason'];
            $ob_data['images']=$post['images'];
            Db::name('store_order_back')->insert($ob_data);
            //将订单状态改为已退货
            Db::name('store_order')->where('order_no',$post['order_no'])->setField('status',6);
           //插入快递记录
            if($post['is_back_goods']==1){
                $oe_data['mid']=UID;
                $oe_data['type']=1;
                $oe_data['order_no']=$post['order_no'];
                $oe_data['express_username']=$post['express_username'];
                $oe_data['express_phone']=$post['express_phone'];
                $oe_data['express_province']=$post['express_province'];
                $oe_data['express_city']=$post['express_city'];
                $oe_data['express_area']=$post['express_area'];
                $oe_data['express_address']=$post['express_address'];
                $oe_data['send_no']=$post['send_no'];
                $oe_data['send_company_title']=$post['send_company_title'];
                $oe_data['send_company_code']=$post['send_company_code'];
                isset($post['send_username'])&&$oe_data['send_username']=$post['send_username'];
                isset($post['send_phone'])&&$oe_data['send_phone']=$post['send_phone'];
                isset($post['send_province'])&&$oe_data['send_province']=$post['send_province'];
                isset($post['send_city'])&&$oe_data['send_city']=$post['send_city'];
                isset($post['send_area'])&&$oe_data['send_area']=$post['send_area'];
                isset($post['send_address'])&&$oe_data['send_address']=$post['send_address'];
                $oe_data['send_at']=date('Y-m-d H:i:s');
                $oe_data['desc']=$post['remark'];
                $oe_data['status']=1;
                $oe_data['is_deleted']=0;
                $oe_data['create_at']=$oe_data['send_at'];
                Db::name('store_order_express')->insert($oe_data);
            }
        });
        $this->success('申请成功');
    }

    /**
     * 获取退货相关信息
     * @author jungshen
     */
    public function back_order_info(){
        $info['back_order_info']=config('mall.back_order_info');
        $info['back_order_reason']=config('mall.back_order_reason');
        $this->success('success',$info);
    }

    /**
     * 查询订单物流信息
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function logistics(){
        $order_no=$this->request->get('order_no');
        $type=$this->request->get('type',0);
        //查询快递记录
        $orderExpress=Db::name('store_order_express')
            ->where('order_no',$order_no)
            ->where('type',$type)
            ->field('order_no,send_no,send_company_title,send_company_code,way,store_id')
            ->find();
        if(!$orderExpress){
            $this->error('快递单查询失败');
        }
        if($orderExpress['way']==1){
            $kdniaoService=KdniaoService::getInstance();

            //$param['OrderCode']=$orderExpress['order_no'];//O
            $param['ShipperCode']=$orderExpress['send_company_code'];
            $param['LogisticCode']=$orderExpress['send_no'];

            $res=$kdniaoService->getOrderTracesByJson($param);
            array_multisort(array_column($res['Traces'],'AcceptTime'),SORT_DESC,$res['Traces']);
            $res['Shipper']=$orderExpress['send_company_title'];
        }else{
            $res['Shipper']='到店自取';
            $res['store']=Db::name('store')
                ->where('id',$orderExpress['store_id'])
                ->find();
        }

        $this->success('success',$res);

    }

    /**
     * 退款订单列表
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function back_order_list(){
        $db = Db::table('store_order')->alias('o')->where([
            'o.is_deleted' => '0',
            'o.mid' => UID,
            'o.status' => 6,
        ])->join('store_order_back ob','ob.order_no=o.order_no');
        $list = $db->field('o.real_price,o.id,ob.order_no,ob.status')->select();
        OrderService::buildOrderList($list);
        $this->success('success',$list);
    }
    public function press(){
        $order_id = $this->request->param('order_id');
        try{
            Db::table('store_order')->where('id',$order_id)->where('mid',UID)->setField('is_press','1');
        } catch (\Exception $e){
            $this->error('操作失败！'.$e->getMessage());
        }
        $this->success('操作成功！');
    }

}