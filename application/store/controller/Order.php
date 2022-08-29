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

namespace app\store\controller;

use app\store\service\OrderService;
use controller\BasicAdmin;
use service\DataService;
use think\Db;

/**
 * 商店订单管理
 * Class Order
 * @package app\store\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/03/27 14:43
 */
class Order extends BasicAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreOrder';

    /**
     * 订单列表
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '订单管理';
        $db = Db::name($this->table);
        $get = $this->request->get();
        // 会员信息查询过滤
        $memberWhere = [];
        foreach (['phone', 'nickname'] as $field) {
            if (isset($get[$field]) && $get[$field] !== '') {
                $memberWhere[] = [$field, 'like', "%{$get[$field]}%"];
            }
        }
        if (!empty($memberWhere)) {
            $memberWhere[] = ['status','eq',1];
            $sql = Db::name('store_member')->field('id')->where($memberWhere)->buildSql(true);
            $db->where("mid in {$sql}");
        }
        // =============== 商品信息查询过滤 ===============
        $goodsWhere = [];
        foreach (['goods_title'] as $field) {
            if (isset($get[$field]) && $get[$field] !== '') {
                $goodsWhere[] = [$field, 'like', "%{$get[$field]}%"];
            }
        }
        if (!empty($goodsWhere)) {
            $sql = Db::name('StoreOrderList')->field('order_no')->where($goodsWhere)->buildSql(true);
            $db->where("order_no in {$sql}");
        }
        // =============== 收货地址过滤 ===============
        $expressWhere = [];
        if (isset($get['express_title']) && $get['express_title'] !== '') {
            $expressWhere[] = ['send_company_title|company_title', 'like', "%{$get['express_title']}%"];
        }
        foreach (['send_no', 'username', 'phone', 'province', 'city', 'area', 'address'] as $field) {
            if (isset($get[$field]) && $get[$field] !== '') {
                $expressWhere[] = [$field, 'like', "%{$get[$field]}%"];
            }
        }
        if (isset($get['send_status']) && $get['send_status'] !== '') {
            $expressWhere[] = empty($get['send_status']) ? ['send_no', 'eq', ''] : ['send_no', 'neq', ''];
        }
        if (!empty($expressWhere)) {
            $sql = Db::name('StoreOrderExpress')->field('order_no')->where($expressWhere)->buildSql(true);
            $db->where("order_no in {$sql}");
        }
        // =============== 主订单过滤 ===============
        foreach (['order_no', 'desc'] as $field) {
            (isset($get[$field]) && $get[$field] !== '') && $db->whereLike($field, "%{$get[$field]}%");
        }
        (isset($get['status']) && $get['status'] !== '') && $db->where('status', $get['status']);
        // 订单是否包邮状态检索
        if (isset($get['express_zero']) && $get['express_zero'] !== '') {
            empty($get['express_zero']) ? $db->where('freight_price', '>', '0') : $db->where('freight_price', '0');
        }
        // 订单时间过滤
        foreach (['create_at', 'pay_at'] as $field) {
            if (isset($get[$field]) && $get[$field] !== '') {
                list($start, $end) = explode(' - ', $get[$field]);
                $db->whereBetween($field, ["{$start} 00:00:00", "{$end} 23:59:59"]);
            }
        }
        return parent::_list($db->order('create_at desc'));
    }

    /**
     * 订单列表数据处理
     * @param array $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function _data_filter(&$data)
    {
        OrderService::buildOrderList($data);
        foreach ($data as &$item) {
            if($item['type'] == 2){
                $order_group = Db::table('store_goods_group_pre')->where('order_no',$item['order_no'])->find();
                $item['success_time'] = isset($order_group['success_time']) ? $order_group['success_time'] : -1;
            }
        }
        //dump($data);exit();
    }

    /**
     * 订单地址修改
     * @author jungshen
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function address()
    {
        $order_no = $this->request->get('order_no');
        if ($this->request->isGet()) {
            $order = Db::name('StoreOrder')->where(['order_no' => $order_no])->find();
            empty($order) && $this->error('该订单无法进行地址修改，订单数据不存在！');
            $orderExpress = Db::name('StoreOrderExpress')->where(['order_no' => $order_no])->find();
            empty($orderExpress) && $this->error('该订单无法进行地址修改！');
            return $this->fetch('', $orderExpress);
        }
        $data = [
            'order_no' => $order_no,
            'express_username' => $this->request->post('express_username'),
            'express_phone'    => $this->request->post('express_phone'),
            'express_province' => get_city_name($this->request->post('province')),
            'express_city'     => get_city_name($this->request->post('city')),
            'express_area'     => get_city_name($this->request->post('district')),
            'express_address'  => $this->request->post('express_address'),
            'desc'     => $this->request->post('express_desc'),
        ];
        if (DataService::save('StoreOrderExpress', $data, 'order_no')) {
            $this->success('收货地址修改成功！', '');
        }
        $this->error('收货地址修改失败，请稍候再试！');
    }

    /**
     * 退款审核
     * @author jungshen
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checkrefund(){
        $order_no = $this->request->get('order_no');
        if($this->request->isGet()){
            $order = Db::name('StoreOrder')->where(['order_no' => $order_no])->find();
            empty($order) && $this->error('该订单无法进行退款，订单数据不存在！');
            $orderBack = Db::name('store_order_back')->where(['order_no' => $order_no])->find();
            empty($orderBack) && $this->error('该订单无法进行退款，退款申请不存在！');
            $orderBack['images']&&$orderBack['images']=explode('|',$orderBack['images']);
            $this->assign('orderBack',$orderBack);
            if($orderBack['is_back_goods']==1){
                //查询退货快递单
                $orderExpress = Db::name('StoreOrderExpress')->where([
                    'order_no' => $order_no,
                    'type' => 1
                ])->order('id desc')->find();
                empty($orderExpress) && $this->error('该订单无法进行退款，快递单不存在！');
                $this->assign('orderExpress',$orderExpress);
            }
            return $this->fetch();
        }else{
            $status=$this->request->post('status');
            if($status==0){
                //同意退款
                Db::name('store_order_back')->where(['order_no' => $order_no])->setField('status',1);
            }elseif($status==1){
                //确认退款
                Db::transaction(function()use($order_no){
                    Db::name('store_order_back')->where(['order_no' => $order_no])->setField('status',2);
                    do_back_order($order_no);
                });
            }else{
                $this->error('状态错误');
            }
            $this->success('操作成功！', '');
        }
    }
    public function setpay(){
        //$order_no = $this->request->get('order_no');
        $order_no = $this->request->post('id', '');
        try{
            Db::table('store_order')->where('order_no',$order_no)
                ->update([
                    'is_pay' => 1,
                    'pay_type' => 'under',
                    'pay_price' => Db::raw('real_price'),
                    'pay_at' => date('Y-m-d H:i:s'),
                    'status' => 2
                ]);
            $order = Db::table('store_order')->where('order_no',$order_no)->find();
            \app\api\service\OrderService::manzeng($order);
        } catch (\Exception $e){
            $this->error('操作失败.'.$e->getMessage());
        }
        $this->success('操作成功','');
    }

    /**
     * 订单导出
     * @author jungshen
     */
    public function export(){
        if($this->request->isGet()){
            //查询所有仓库
            $depots=Db::name('store_goods_depot')
                ->where('status',1)
                ->where('is_deleted',0)
                ->field('id,depot_title')
                ->select();
            $this->assign('depots',$depots);
            return $this->fetch();
        }else{
            $post=$this->request->post();

            //查询全部订单列表
            $db=Db::name('store_order')
                ->alias('o')
                ->leftJoin('store_order_express oe','o.order_no = oe.order_no')
                ->leftJoin('store_order_goods og','o.order_no = og.order_no')
                ->field('o.id,o.create_at,o.order_no,o.goods_price,o.status,o.desc,
                oe.send_company_title,oe.send_no,oe.express_username,oe.express_phone,oe.express_idcard,oe.way,oe.store_id,
                CONCAT(oe.express_province,oe.express_city,oe.express_area,oe.express_address) full_express_address,
                GROUP_CONCAT(CONCAT_WS(\'_|_\',og.goods_title,og.selling_price,og.number,og.cost_price,og.depot_title) SEPARATOR \'_||_\') order_goods_info')
                ->group('o.order_no');
            if($post['status']!=''){
                $db->where('o.status',$post['status']);
            }
//            if($post['depot_id']!=''){
//                $db->where('og.depot_id',$post['depot_id']);
//            }
            if($post['create_at']!=''){
                $post['create_at']=explode(' - ',$post['create_at']);
                /*if($post['create_at'][0]>$post['create_at'][1]){
                    $tmp=$post['create_at'][0];
                    $post['create_at'][0]=$post['create_at'][1];
                    $post['create_at'][1]=$tmp;
                }*/
                $post['create_at'][0].=' 00:00:00';
                $post['create_at'][1].=' 23:59:59';
                $db->where('o.create_at','between',$post['create_at']);
            }
            if($post['province']>0){
                $db->where('oe.express_province',get_city_name($post['province']));
            }
            if($post['city']>0){
                $db->where('oe.express_city',get_city_name($post['city']));
            }
            if($post['district']>0){
                $db->where('oe.express_area',get_city_name($post['district']));
            }
            $list=$db->select();

            //导出数据

            $orderService=new OrderService();
            $fileName='订单列表_'.date('Y-m-d H:i:s');
            $head=['编码','下单时间','姓名','电话','订单号','订单状态','商品信息','商品信息','商品信息','商品信息','商品信息','收货地址','快递公司','物流单号','备注','取货门店','发货方式'];
            $keys=['id','create_at','express_username','express_phone','order_no','status','order_goods_info','order_goods_info','order_goods_info','order_goods_info',
                'order_goods_info','full_express_address','send_company_title','send_no','desc','store_id','way'];
            $orderService->export($fileName,$list,$head,$keys);

        }
    }


}
