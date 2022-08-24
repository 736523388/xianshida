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
use service\KdniaoService;
use think\Db;

/**
 * 商店订单管理
 * Class Order
 * @package app\store\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/03/27 14:43
 */
class Deliver extends BasicAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreOrderExpress';

    /**
     * 发货
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function express()
    {
        $order_no = $this->request->get('order_no');
        if ($this->request->isGet()) {
            $order = Db::name('StoreOrder')->where([
                'order_no' => $order_no,
                'status' => 2,
                'is_pay' => 1,
            ])->find();
            empty($order) && $this->error('订单数据不存在！');
            $orderExpress = Db::name('StoreOrderExpress')
                ->where(['order_no' => $order_no])
                ->find();
            empty($orderExpress) && $this->error('该订单无法发货！');
            //查询所有物流公司
            $express=Db::name('store_express')
                ->where('status',1)
                ->where('is_deleted',0)
                ->field('express_title,express_code')
                ->select();
            $this->assign('express',$express);
            //查询所有门店
            $store=Db::name('store')
                ->where('status',1)
                ->field('id,title')
                ->select();
            $this->assign('stores',$store);
            return $this->fetch('', $orderExpress);
        }
        $way=input('way',1);
        $data = [
            'way' => $way,
            'order_no' => $order_no,
            'send_username'    => $this->request->post('send_username'),
            'send_phone'    => $this->request->post('send_phone'),
            'send_province' => $this->request->post('send_province'),
            'send_city'     => $this->request->post('send_city'),
            'send_area'     => $this->request->post('send_area'),
            'send_address'  => $this->request->post('send_address'),
            'send_at'  => date('Y-m-d H:i:s'),
            'desc'     => $this->request->post('express_desc'),
        ];
        if($way==1){
            //快递
            $express=explode('@',$this->request->post('express'));
            $data['send_no'] = $this->request->post('send_no');
            $data['send_company_title'] = $express[0];
            $data['send_company_code'] = $express[1];
        }else{
            //自取
            $data['store_id'] = $this->request->post('store_id');
        }

        //验证数据
        $validate=new \app\store\validate\Deliver();
        if(false===$validate->check($data)){
            $this->error($validate->getError());
        }
        if (DataService::save('StoreOrderExpress', $data, 'order_no')) {
            //将订单状态改为已发货
            Db::name('store_order')->where('order_no',$order_no)->setField('status',3);
            $this->success('发货成功！', '');
        }
        $this->error('发货失败，请稍候再试！');
    }
    
    public function tracking(){
        $express_code=$this->request->get('express_code');
        $express_no=$this->request->get('express_no');
        $company_title=$this->request->get('company_title');

        $kdniaoService=KdniaoService::getInstance();

        $param['ShipperCode']=$express_code;
        $param['LogisticCode']=$express_no;

        $res=$kdniaoService->getOrderTracesByJson($param);
        try{
            array_multisort(array_column($res['Traces'],'AcceptTime'),SORT_DESC,$res['Traces']);
            $res['Shipper']=$company_title;
            $this->assign('result',$res);
        }catch (\Exception $e){
            $this->assign('result', []);
        }
        return $this->fetch();
    }


}
