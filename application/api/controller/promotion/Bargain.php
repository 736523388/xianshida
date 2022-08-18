<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2019/1/12
 * Time: 14:29
 */

namespace app\api\controller\promotion;


use controller\BasicApi;
use think\Db;
use think\Exception;

class Bargain extends BasicApi
{
    /**
     * 砍价列表
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function lists(){
        $page_now=$this->request->get('page_now',1);//当前页
        $page_size=$this->request->get('page_size',10);//每页记录数
        $current_date=date('Y-m-d H:i:s');
        //查询列表
        $db=Db::name('store_goods_bargain')
            ->alias('gb')
            ->join('store_goods g','g.id=gb.goods_id')
            ->join('store_goods_list gl','gl.goods_id=g.id')
            ->leftJoin('store_order_bargain ob','ob.goods_id=gb.goods_id and ob.success_time>0')
            ->where('gb.status',1)
            ->where('g.status',1)
            ->where('g.is_deleted',0)
            ->where('gb.stock','>',0)
            ->where('gb.activity_start_time','<=',$current_date)
            ->where('gb.activity_end_time','>=',$current_date);
        $list=$db->field('g.goods_logo,g.goods_title,g.goods_desc,g.id goods_id,
            gb.activity_price,gb.activity_stock,gb.stock,gb.id,gb.low_price,
            left(group_concat(gl.market_price order by gl.market_price),locate(\',\',concat(group_concat(gl.market_price order by gl.market_price),\',\'))-1) selling_price,
            count(distinct ob.id) total_success')
            ->group('g.id')
            ->page($page_now,$page_size)
            ->select();
            foreach ($list as &$value) {
                $value['goods_logo'] = sysconf('applet_url') . $value['goods_logo'];
            }
        return $this->success('success',$list);
    }

    /**
     * 获取产品SKU信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function goods_sku(){
        $goods_id=$this->request->get('goods_id');
        $goodsdetail=Db::name('store_goods')->where('id',$goods_id)->field('spec_id,id')->find();
        $specWhere = [['status', '=', '1'], ['is_deleted', '=', '0'], ['goods_id', '=', $goods_id]];
        $specField = 'id,goods_id,goods_spec,goods_number,market_price,selling_price,goods_stock,goods_sale';
        $specList = Db::name('StoreGoodsList')->where($specWhere)->column($specField);
        foreach ($specList as $key => $spec) {
            if ($spec['goods_spec'] === 'default:default') {
                $specList[$key]['goods_spec_alias'] = '默认规格';
            } else {
                $specList[$key]['goods_spec_alias'] = str_replace([':', ','], [': ', ', '], $spec['goods_spec']);
            }
        }
        $goodsdetail['spec'] = [];
        foreach ($specList as $spec) {
            if ($goodsdetail['id'] === $spec['goods_id']) {
                $goodsdetail['spec'][] = $spec;
            }
        }
        if(!empty($goodsdetail['spec_id'])){
            $goods_spec = Db::name('StoreGoodsSpec')->where('id',$goodsdetail['spec_id'])->find();
            $spec_list = json_decode(isset($goods_spec['spec_param']) ? $goods_spec['spec_param'] : '',true);
            foreach ($spec_list as $key => $item) {
                $spec_list[$key]['value'] = !empty(explode(',',$item['value'])) ? explode(',',$item['value']) : !empty(explode(' ',$item['value'])) ? explode(' ',$item['value']) :[];
            }
            $goodsdetail['spec_list'] = $spec_list;
        }else{
            $goodsdetail['spec_list'] = [];
        }
        return $this->success('success',$goodsdetail);
    }

    /**
     * 砍价详情
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function detail(){
        $token=$this->request->get('token','');
        $mid=get_login_info($token,'id');
        if(!$mid)$this->error('请先登录');
        $id=$this->request->get('id');//活动ID
        $order_bargain_id=$this->request->get('order_bargain_id',0);//订单砍价ID
        $current_date=date('Y-m-d H:i:s');
        $goods_spec=$this->request->get('goods_spec','default:default');//主人第一次点进来必填
        //砍价
        //查询这个活动
        $goods_bargain=Db::name('store_goods_bargain')
            ->where('id',$id)
            /*->where('stock','>',0)*/
            ->where('status',1)
            ->where('activity_start_time','<=',$current_date)
            ->where('activity_end_time','>=',$current_date)
            ->field('activity_price,activity_quantity,max_price,min_price,low_price,goods_id,id,stock')
            ->find();
        if(!$goods_bargain)$this->error('该活动已过期');
        $dadao_price=0;
        if($order_bargain_id==0){
            //不是分享的 自己砍价 是否有未完成的砍价 没有 查询限购 砍价
            $store_order_bargaining=Db::name('store_order_bargain')
                ->where('activity_id',$id)
                ->where('mid',$mid)
                ->where('success_time',0)
                ->field('id')->find();
            if(!$store_order_bargaining){
                if($goods_bargain['stock']==0){
                    //没有库存了
                    $this->error('该商品已被抢光');
                }
                //查询砍价成功未支付的
                $store_order_bargain_success_noorder=Db::name('store_order_bargain')
                    ->where('activity_id',$id)
                    ->where('mid',$mid)
                    ->where('success_time','>',0)
                    ->where('order_no','')
                    ->field('id')
                    ->find();
                if($store_order_bargain_success_noorder['id']){
                    $this->error('该商品已砍价成功，请您提交订单',['order_bargain_id'=>$store_order_bargain_success_noorder['id']]);
                }
                //查询砍价成功的数量
                $store_order_bargain_success_count=Db::name('store_order_bargain')
                    ->where('activity_id',$id)
                    ->where('mid',$mid)
                    ->where('success_time','>',0)
                    ->count();
                if($goods_bargain['activity_quantity']>0&&$store_order_bargain_success_count>=$goods_bargain['activity_quantity']){
                    //限购 已经不能购买了
                    $this->error('您已购买了'.$store_order_bargain_success_count.'件产品，不能参加该商品的活动了');
                }else{
                    DB::startTrans();
                    try{
                        $success_time=0;
                        $dadao_price=rand($goods_bargain['min_price']*100,$goods_bargain['max_price']*100)/100;
                        $max_bargain_price=(intval($goods_bargain['activity_price']*100)-intval($goods_bargain['low_price']*100))/100;
                        if($max_bargain_price<=$dadao_price){
                            $dadao_price=$max_bargain_price;
                            $success_time=time();
                        }
                        //插入订单砍价信息
                        $order_bargain_data['mid']=$mid;
                        $order_bargain_data['goods_id']=$goods_bargain['goods_id'];
                        $order_bargain_data['activity_id']=$goods_bargain['id'];
                        $order_bargain_data['end_time']=time()+sysconf('bargain_hour')*60*60;
                        $order_bargain_data['create_at']=time();
                        $order_bargain_data['success_time']=$success_time;
                        $order_bargain_data['goods_spec']=$goods_spec;
                        $order_bargain_data['start_price']=$goods_bargain['activity_price'];
                        $order_bargain_data['now_price']=(intval($goods_bargain['activity_price']*100)-intval($dadao_price*100))/100;
                        $order_bargain_data['bargain_number']=1;
                        $order_bargain_id=Db::name('store_order_bargain')->insertGetId($order_bargain_data);
                        //为自己砍一刀
                        $bargain_log_data['mid']=$mid;
                        $bargain_log_data['order_bargain_id']=$order_bargain_id;
                        $bargain_log_data['create_at']=$order_bargain_data['create_at'];
                        $bargain_log_data['price']=$dadao_price;
                        $desc_arr=config('mall.bargain_word.self');
                        $bargain_log_data['desc']=$desc_arr[array_rand($desc_arr,1)];
                        Db::name('bargain_log')->insert($bargain_log_data);
                        //减去一个库存
                        Db::name('store_goods_bargain')->where('id',$id)->setDec('stock',1);
                    }catch (Exception $e){
                        Db::rollback();
                        $this->error($e->getMessage().' Line:'.$e->getFile().' '.$e->getLine());
                    }
                    Db::commit();
                }
            }else{
                //存在未完成的砍价
                $order_bargain_id=$store_order_bargaining['id'];
                $this->error('该商品正在砍价中,快去邀请好友帮忙砍价吧',['order_bargain_id'=>$order_bargain_id]);
            }
        }else{
            //是分享的或者从我的砍价列表进来，判断当前用户这个商品砍价了没 没砍&还需要砍 砍一刀
            //当前正在砍价的订单砍价信息
            $store_order_bargin=Db::name('store_order_bargain')
                ->where('id',$order_bargain_id)
                ->field('success_time,now_price,bargain_number,mid')
                ->find();
            if(!$store_order_bargin||$store_order_bargin['success_time']==-1||($store_order_bargin['success_time']>1&&$mid!=$store_order_bargin['mid'])){
                $this->error('砍价订单已失效');
            }
            $bargain_log_count=Db::name('bargain_log')
                ->where('order_bargain_id',$order_bargain_id)
                ->where('mid',$mid)
                ->count();
            if($bargain_log_count==0){
                //没砍&还需要砍
                DB::startTrans();
                try{
                    $dadao_price=rand($goods_bargain['min_price']*100,$goods_bargain['max_price']*100)/100;
                    $success_time=0;
                    $max_bargain_price=(intval($store_order_bargin['now_price']*100)-intval($goods_bargain['low_price']*100))/100;
                    if($max_bargain_price<=$dadao_price){
                        $dadao_price=$max_bargain_price;
                        $success_time=time();
                    }
                    //修改订单砍价信息
                    $order_bargain_data['success_time']=$success_time;
                    $order_bargain_data['now_price']=(intval($store_order_bargin['now_price']*100)-intval($dadao_price*100))/100;
                    $order_bargain_data['bargain_number']=$store_order_bargin['bargain_number']+1;
                    Db::name('store_order_bargain')
                        ->where('id',$order_bargain_id)
                        ->update($order_bargain_data);
                    //为好友砍一刀
                    $bargain_log_data['mid']=$mid;
                    $bargain_log_data['order_bargain_id']=$order_bargain_id;
                    $bargain_log_data['create_at']=time();
                    $bargain_log_data['price']=$dadao_price;
                    if($dadao_price<config('mall.bargain_word.less.price')){
                        $desc_arr=config('mall.bargain_word.less.data');
                    }elseif ($dadao_price<config('mall.bargain_word.middle.price')){
                        $desc_arr=config('mall.bargain_word.middle.data');
                    }else{
                        $desc_arr=config('mall.bargain_word.much.data');
                    }
                    $bargain_log_data['desc']=$desc_arr[array_rand($desc_arr,1)];
                    Db::name('bargain_log')->insert($bargain_log_data);
                }catch (Exception $e){
                    Db::rollback();
                    $this->error($e->getMessage().' Line:'.$e->getFile().' '.$e->getLine());
                }
                Db::commit();
            }
        }
        //查询产品详情
        $db=Db::name('store_goods_bargain')
            ->alias('gb')
            ->join('store_goods g','g.id=gb.goods_id')
            ->join('store_goods_list gl','gl.goods_id=g.id')
            ->join('store_order_bargain ob','ob.activity_id=gb.id')
            ->where('gb.id',$id)
            ->where('gb.status',1)
            ->where('g.status',1)
            ->where('g.is_deleted',0)
            ->where('gb.activity_start_time','<=',$current_date)
            ->where('gb.activity_end_time','>=',$current_date);
        $db->where('ob.id',$order_bargain_id);
        $goods=$db->field('g.goods_logo,g.goods_title,g.goods_desc,g.id goods_id,
            gb.activity_price,gb.activity_stock,gb.stock,gb.id,gb.low_price,
            left(group_concat(gl.selling_price order by gl.selling_price),locate(\',\',concat(group_concat(gl.selling_price order by gl.selling_price),\',\'))-1) selling_price,
            ob.end_time,ob.goods_spec,ob.start_price,ob.now_price,ob.bargain_number,ob.id order_bargain_id,ob.success_time,ob.order_no
            ')
            ->group('g.id')
            ->find();
            $goods['goods_logo'] = sysconf('applet_url') . $goods['goods_logo'];
        //获取已砍价信息
        $bargain_log=Db::name('bargain_log')
            ->alias('bl')
            ->join('store_member m','m.id=bl.mid')
            ->where('bl.order_bargain_id',$goods['order_bargain_id'])
            ->field('bl.price,bl.desc,m.nickname,m.headimg')
            ->select();
        //查询总人数
        $total_success=DB::name('store_order_bargain')
            ->where('goods_id',$goods_bargain['goods_id'])
            ->where('success_time','>',0)
            ->count();
        //查询作者（发起人）
        $author=Db::name('store_order_bargain')
            ->alias('ob')
            ->join('store_member m','m.id=ob.mid')
            ->where('ob.id',$order_bargain_id)
            ->field('m.headimg,m.nickname,m.id')
            ->find();

        return $this->success('success',[
            'goods_info'=>$goods,
            'bargain_log'=>$bargain_log,
            'dadao_price'=>$dadao_price,
            'current_mid'=>$mid,
            'author'=>$author,
            'total_success'=>$total_success
        ]);
    }

    /**
     * 查询6个猜你喜欢
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function recommend(){
        //查询6个猜你喜欢
        $id=$this->request->get('id');
        $current_date=date('Y-m-d H:i:s');
        //查询列表
        $db=Db::name('store_goods_bargain')
            ->alias('gb')
            ->join('store_goods g','g.id=gb.goods_id')
            ->join('store_goods_list gl','gl.goods_id=g.id')
            ->leftJoin('store_order_bargain ob','ob.goods_id=gb.goods_id and ob.success_time>0')
            ->where('gb.status',1)
            ->where('g.status',1)
            ->where('g.is_deleted',0)
            ->where('gb.stock','>',0)
            ->where('gb.activity_start_time','<=',$current_date)
            ->where('gb.activity_end_time','>=',$current_date);
        $list=$db->field('g.goods_logo,g.goods_title,g.goods_desc,g.id goods_id,
            gb.activity_price,gb.activity_stock,gb.stock,gb.id,gb.low_price,
            left(group_concat(gl.selling_price order by gl.selling_price),locate(\',\',concat(group_concat(gl.selling_price order by gl.selling_price),\',\'))-1) selling_price,
            count(distinct ob.id) total_success')
            ->group('g.id')
            ->order('gb.stock asc')
            ->page(1,6)
            ->select();
            foreach ($list as &$value) {
                $value['goods_logo'] = sysconf('applet_url') . $value['goods_logo'];
            }
        return $this->success('success',$list);
    }

    /**
     * 快斧榜
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function fast_success(){
        //查询10个快斧榜
        $list=Db::name('store_order_bargain')
            ->alias('ob')
            ->join('store_member m','m.id=ob.mid')
            ->join('store_goods g','g.id=ob.goods_id')
            ->where('ob.success_time','>',0)
            ->order('ob.bargain_number asc')
            ->field('m.nickname,m.headimg,ob.now_price,ob.bargain_number,g.goods_title')
            ->limit(10)
            ->select();
        $this->success('success',$list);
    }

    function my_bargain(){
        $token=$this->request->get('token','');
        $mid=get_login_info($token,'id');
        if(!$mid)$this->error('请先登录');
        //查询我的砍价
        $list=Db::name('store_order_bargain')
            ->alias('ob')
            ->join('store_goods g','g.id=ob.goods_id')
            ->join('store_goods_bargain gb','gb.id=ob.activity_id')
            ->where('ob.mid',$mid)
            ->where('g.status',1)
            ->where('g.is_deleted',0)
            ->field('g.goods_title,g.goods_logo,
                ob.activity_id,ob.end_time,ob.start_price,ob.now_price,ob.bargain_number,ob.id order_bargain_id,ob.success_time,
                gb.low_price')
            ->select();
        foreach ($list as &$value) {
            $value['goods_logo'] = sysconf('applet_url') . $value['goods_logo'];
        }
        return $this->success('success',$list);
    }
}