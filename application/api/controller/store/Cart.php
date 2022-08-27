<?php
/**
 * Created by PhpStorm.
 * User: forska
 * Date: 2018/11/8
 * Time: 11:37
 */

namespace app\api\controller\store;

use app\api\controller\BasicUserApi;
use think\Db;
use think\exception\HttpResponseException;

class Cart extends BasicUserApi
{
    public $table = 'StoreGoodsCart';

    /**
     * @Notes: 购物车商品列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/9 18:07
     */
    public function lists(){
        $list = Db::name($this->table)->alias('a')
            ->join('store_goods_list b','b.goods_spec = a.goods_spec and b.goods_id = a.goods_id')
            ->join('store_goods c','c.id = a.goods_id')
            /*->leftJoin('store_goods_brand d','d.id = c.brand_id')*/
            ->where(['a.mid' => UID,'a.is_deleted' => '0','b.is_deleted' => '0','c.is_deleted' => '0'])
            ->field('a.*,b.market_price,b.selling_price,b.status spec_status,b.goods_stock,b.goods_sale,b.goods_stock-b.goods_sale stock,c.status goods_status')
            ->select();
       foreach ($list as &$item) {
           $item['brand_title']=sysconf('app_name');
           $item['goods_spec_alias'] = \app\api\service\GoodsService::getSpecAlias($item['goods_spec'], 2);
           $item['goods_image'] = explode('|',!empty($item['goods_image']) ? $item['goods_image'] : '');
           $item['goods_logo'] = sysconf('applet_url'). $item['goods_logo'];
           $item['status'] = $item['goods_status'] && $item['spec_status'] && $item['stock'] ? 1 : 0;
           $item['desc'] = '';
           if($spike = Db::table('store_goods_spike')->where(['goods_id' => $item['goods_id'],'status' => '1'])->where('activity_start_time','<',date('Y-m-d H:i:s'))->where('activity_end_time','>',date('Y-m-d H:i:s'))->where('stock','>',0)->find()){
               $item['show_price'] = $spike['activity_price'];
               $item['cancel_price'] = IS_INSIDER ? $item['selling_price'] : $item['market_price'];
               $item['cancel_price_txt'] = '原价';
               $item['desc'] = '限时抢购 '.date('m月d日H:i:s',strtotime($spike['activity_end_time'])).'结束';
               $item['stock'] = $spike['stock'] > $item['stock'] ? $item['stock'] : $spike['stock'];
           }else{
               $item['show_price'] = IS_INSIDER ? $item['selling_price'] : $item['market_price'];
               $item['cancel_price'] = IS_INSIDER ? $item['market_price'] : $item['selling_price'];
               $item['cancel_price_txt'] = IS_INSIDER ? '原价' : '会员价';
           }




       }
       $list = $this->group_same_key($list,'brand_title');
       $data = [];
        foreach ($list as $key => $item) {
            $data[] = [
                'brand_title' => $key,
                'list' => $item
            ];
       }
       $this->success('success',$data);
    }

    /**
     * @Notes: 更新购物车商品
     * @return \think\Response
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/9 19:09
     */
    public function update(){
        try{
            $cart_id = $this->request->param('cart_id');
            $number = (int)$this->request->param('number');
            $db = Db::name($this->table)->where(['is_deleted'=>'0','mid'=>UID,'id'=>$cart_id]);
            if($number === 0){
                $data = ['is_deleted'=>'1'];
            }else{
                $data = ['number'=>$number];
            }
            $db->update($data);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('更新购物车失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('更新购物车成功');
    }

    /**
     * @Notes: 添加商品到购物车
     * @return \think\Response
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/9 17:34
     */
    public function add(){
        try {
            $goods_id = $this->request->param('goods_id');
            $goods_spec = $this->request->param('goods_spec');
            $number = $this->request->param('number');


            $goods = Db::table('store_goods')->where(['is_deleted' => '0','status'=>'1','id'=>$goods_id])->field('id,goods_title,goods_logo,goods_image')->find();

            Db::transaction(function () use ($goods_id, $goods_spec, $number, $goods) {
                $data = [
                    'mid'=>UID,
                    'goods_id' => $goods_id,
                    'goods_title' => $goods['goods_title'],
                    'goods_spec' => $goods_spec,
                    'goods_logo' => $goods['goods_logo'],
                    'goods_image' => $goods['goods_image'],
                    'number'=>$number
                ];
                $info = Db::name('StoreGoodsCart')->where(['is_deleted'=>'0','mid'=>UID,'goods_id' => $goods_id,'goods_spec' => $goods_spec])->find();
                if(empty($info)){
                    Db::name('StoreGoodsCart')->insert($data);
                }else{
                    $data['number'] += $info['number'];
                    Db::name('StoreGoodsCart')->where(['id'=>$info['id']])->update($data);
                }
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('添加购物车失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('添加购物车成功');
    }

    /**
     * @Notes: 二维数组分组
     * @param $arr
     * @param $key
     * @return array
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/9 17:59
     */
    public function group_same_key($arr,$key){
        $new_arr = array();
        foreach ($arr as $k => $v) {
            $new_arr[$v[$key]][] = $v;
        }
        return $new_arr;
    }

    /**
     * 获取某个用户的购物车数量
     * @author jungshen
     */
    public function cartnum(){
        $count=Db::name('store_goods_cart')
            ->alias('c')
            ->join('store_goods g','g.id=c.goods_id')
            ->where('g.status',1)
            ->where('g.is_deleted',0)
            ->where('c.mid',UID)
            ->where('c.is_deleted','0')
            ->value('sum(c.number) num');
        $this->success('success',intval($count));
    }
}