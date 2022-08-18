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

class Group extends BasicApi
{
    /**
     * 团购列表
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function lists(){
        $cate_id=$this->request->get('cate_id',0);//商品一级分类ID 0：精品推荐
        $page_now=$this->request->get('page_now',1);//当前页
        $page_size=$this->request->get('page_size',10);//每页记录数
        $current_date=date('Y-m-d H:i:s');
        //查询列表
        $db=Db::name('store_goods_group')
            ->alias('gg')
            ->join('store_goods g','g.id=gg.goods_id')
            ->join('store_goods_list gl','gl.goods_id=g.id')
            ->where('gg.status',1)
            ->where('g.status',1)
            ->where('g.is_deleted',0)
            ->where('gg.activity_start_time','<=',$current_date)
            ->where('gg.activity_end_time','>=',$current_date);
        if($cate_id>0){
            //按分类筛选
            $cate_ids=Db::name('store_goods_cate')
                ->alias('gc1')
                /*->join('store_goods_cate gc2','gc1.pid=gc2.id')
                ->where('gc2.pid',$cate_id)
                ->where('gc2.is_deleted',0)*/
                ->where('gc1.id',$cate_id)
                ->where('gc1.is_deleted',0)
                ->column('gc1.id');
            $db->where('g.cate_id','in',$cate_ids);
        }else{
            //只看精选
            $db->where('gg.perfect',1);
        }
      //修改了显示原价
        $list=$db->field('g.goods_logo,g.goods_title,g.goods_desc,g.id,
            gg.activity_price,gg.complete_num,gg.stock,
            left(group_concat(gl.market_price order by gl.market_price),locate(\',\',concat(group_concat(gl.market_price order by gl.market_price),\',\'))-1) selling_price')
            ->group('g.id')
            ->page($page_now,$page_size)
            ->select();
        foreach ($list as &$value) {
            $value['goods_logo'] = sysconf('applet_url') . $value['goods_logo'];
        }
        return $this->success('success',$list);
    }
}