<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2019/1/11
 * Time: 9:56
 */

namespace app\api\controller\promotion;


use controller\BasicApi;
use think\Db;

class Spike extends BasicApi
{
    /**
     * 秒杀商品列表
     * @author jungshen
     * @TODO 此处除了商品信息 其他应该单独写接口
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    function lists(){
        $page_now=$this->request->get('page_now',1);
        $page_size=$this->request->get('page_size',10);
        $type=$this->request->get('type',1);//1.秒杀中 2.即将开始 3.明日预告
        $current_date=date('Y-m-d H:i:s');
        $db=Db::name('store_goods_spike')
            ->alias('gs')
            ->join('store_goods g','g.id=gs.goods_id')
            ->where('g.status',1)
            ->where('g.is_deleted',0)
            ->join('store_goods_list gl','gl.goods_id=g.id')
            ->where('gs.status',1);
        switch ($type){
            case 1:
                //正在疯抢
                //开始时间<=当前时间，结束时间>=当前时间
                if($page_now==1){
                    $data['current_hour']=date('H:00');
                    $data['remaining_second']=strtotime(date('Y-m-d H:00',time()+60*60))-time();
                }
                $db->where('gs.activity_start_time','<=',$current_date)
                    ->where('gs.activity_end_time','>=',$current_date);
                break;
            case 2:
                //即将开始 下个小时开始的
                //开始时间下个小时-下2个小时之间 结束时间>=下个小时
                //得到整点小时
                if($page_now==1){
                    $data['next_hour']=date('H:00',time()+60*60);
                    $data['remaining_second']=strtotime(date('Y-m-d H:00',time()+60*60))-time();
                }
                $current_hour=date('Y-m-d H',time()+60*60);
                $next_hour=date('Y-m-d H',time()+2*60*60);
                $db->where('gs.activity_start_time','between',[$current_hour.':00:00',$next_hour.':00:00'])
                    ->where('gs.activity_end_time','>=',$current_hour.':00:00');
                break;
            case 3:
                //明日预告
                //开始时间 明天0-24点 结束时间>=0点
                //明天的时间
                if($page_now==1) $data['tomorrow_remaining_second']=strtotime(date('Y-m-d',time()+24*60*60))-time();
                $tomorrow=date('Y-m-d',time()+24*60*60);
                $db->where('gs.activity_start_time','between',[$tomorrow.' 00:00:00',$tomorrow.' 23:59:59'])
                    ->where('gs.activity_end_time','>=',$tomorrow.' 00:00:00');
                break;
        }

        $list=$db->field('g.goods_logo,g.goods_title,g.goods_desc,g.id,
            gs.activity_price,gs.activity_stock,gs.stock,
            left(group_concat(gl.market_price order by gl.market_price),locate(\',\',concat(group_concat(gl.market_price order by gl.market_price),\',\'))-1) selling_price,
            gs.activity_price/selling_price zhekou,
            (1-gs.stock/gs.activity_stock) jindu')
            ->group('g.id')
            ->page($page_now,$page_size)
            ->select();
        foreach ($list as &$value) {
            $value['goods_logo'] = sysconf('applet_url') . $value['goods_logo'];
        }
        $data['goods_list']=$list;
        if($page_now==1){
            $data['bg_img']=sysconf('applet_url') . sysconf('spike_bg_img');
        }
        return $this->success('success',$data);
    }

}