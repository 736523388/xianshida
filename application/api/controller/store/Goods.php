<?php
/**
 * Created by PhpStorm.
 * User: forska
 * Date: 2018/11/8
 * Time: 11:37
 */

namespace app\api\controller\store;

use app\api\service\CouponService;
use app\api\service\GoodsService;
use controller\BasicApi;
use service\ToolsService;
use think\Db;
use think\Exception;
use think\exception\HttpResponseException;

class Goods extends BasicApi
{
    /**
     * @var int 默认每页显示条数
     */
    public $pagesize = 10;
    /**
     * @var int 默认显示页码
     */
    public $page = 1;
    /**
     * @var string 定义当前操作表名
     */
    public $table = 'StoreGoods';
    public $price_field = 'market_price';
    public $hide_price_field = 'huaxian_price';
    public $hide_price_txt = '批发价';
    public $user_level = 0;

    public function __construct()
    {
        parent::__construct();
        $token = request()->header('token', input('token', ''));
        $user_level = get_login_info($token,'level');
        if($user_level){
            $this->user_level = $user_level;
            $this->price_field = 'selling_price';
            $this->hide_price_field = 'huaxian_price';
            $this->hide_price_txt = '零售价';
        }
    }

    public function success($msg, $data = [], $code = 1)
    {
        parent::success($msg, ['data' => $data,'show_price' => $this->price_field,'hide_price' => $this->hide_price_field,'hide_price_txt' => $this->hide_price_txt], $code);// TODO: Change the autogenerated stub
    }

    /**
     * @Notes: 查询基础sql
     * @return \think\db\Query
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/7 15:49
     */
    public function baseSql(){
        $spesql = Db::table('store_goods_list')
            ->where(['status' => '1','is_deleted' => '0'])
            ->field('left(group_concat('.$this->price_field.' order by '.$this->price_field.'),
            locate(\',\',concat(group_concat('.$this->price_field.' order by '.$this->price_field.'),\',\'))-1
        )+0 '.$this->price_field.','.
                'left(group_concat('.$this->hide_price_field.' order by '.$this->price_field.'),
            locate(\',\',concat(group_concat('.$this->hide_price_field.' order by '.$this->price_field.'),\',\'))-1
        )+0 '.$this->hide_price_field.','.
                'left(group_concat(goods_spec order by '.$this->price_field.'),
            locate(\',\',concat(group_concat(goods_spec order by '.$this->price_field.'),\',\'))-1
        ) goods_spec,goods_id')
            ->group('goods_id')
            ->buildSql();
        $db = Db::name($this->table)
            ->alias('a')
            ->join([$spesql=> 'b'], 'a.id = b.goods_id')
            ->where(['a.is_deleted' => '0','a.status' => '1']);
        return $db;
    }

    /**
     * @Notes: 商品列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/7 20:11
     */
    public function allgoods(){
        $page = (int)$this->request->param('page',$this->page);
        $goods_title = $this->request->param('goods_title','');
        $cate_id = $this->request->param('cate_id','');
        $brand_id = $this->request->param('brand_id','');
        $sort = $this->request->param('sort','default'); //hot：人气，pricehight：价格高到低 pricelow：价格低到高 time:最新 默认：综合

        $db = $this->baseSql();
        if (isset($goods_title) && $goods_title !== '') {
            $db->whereLike('a.goods_title|a.goods_desc|b.goods_spec', "%{$goods_title}%");
        }
        ($cate_id !== '') && is_numeric($cate_id) && $db->whereIn('a.cate_id', self::childCate($cate_id));
        ($brand_id !== '') && $db->where('a.brand_id', $brand_id);
        switch ($sort){
            case 'pricehight':
                $order = 'b.'.$this->price_field.' desc';
                break;
            case 'pricelow':
                $order = 'b.'.$this->price_field.' asc';
                break;
            case 'hot':
                $order = 'a.browse desc';
                break;
            case 'time':
                $order = 'a.create_at desc';
                break;
            default:
                $order = 'a.id desc';
        }
        $list = (array)$db
            ->order($order)
            ->page($page,$this->pagesize)
            ->field('a.id,a.goods_title,a.goods_logo,huaxian_price,'.$this->price_field)
            ->select();
        foreach ($list as &$item) {
            $item['huaxian_price'] = bcadd($item['huaxian_price'],0,2);
            $item[$this->price_field] = bcadd($item[$this->price_field], 0, 2);
            $item['goods_logo'] .= "?x-oss-process=style/base_image";
        }
//        GoodsService::buildGoodsList($list);
        $this->success('success',$list);
    }
    /**
     * @Notes: 获取分类下所有ID
     * @param int $pid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/10 20:18
     */
    public static function childCate($pid=0){
        $list = Db::table('store_goods_cate')->where(['status' => '1','is_deleted' => '0'])->field('id,pid')->select();
        if(empty($pid)){
            return array_column($list, 'id');
        }
        return ToolsService::getArrSubIds($list,$pid);
    }
    /**
     * @Notes: 商品详情
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/7 16:05
     */
    public function detail(){
        $goods_id = $this->request->param('id');

        $goods = (array)Db::name($this->table)->where(['id' => $goods_id, 'is_deleted' => '0','status' => '1'])->find();
        if(empty($goods)) $this->error("网络异常，请稍后再试~");
        Db::name($this->table)->where(['id' => $goods_id, 'is_deleted' => '0','status' => '1'])->setInc('browse',1);

        GoodsService::buildGoodsDetail($goods);
        $goods['activity_info'] = [];
        //分享二维码END

        $this->success('success',$goods);
    }

    /**
     * 获取活动信息
     * @param $goods_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function activity_info($goods_id){
        //查询秒杀、团购
        //秒杀
        $spike_info=Db::name('store_goods_spike')
            ->where('goods_id',$goods_id)
            ->where('activity_start_time','<=',date('Y-m-d H:i:s'))
            ->where('activity_end_time','>=',date('Y-m-d H:i:s'))
            ->where('stock','>',0)
            ->where('status',1)
            ->hidden('activity_name,create_at,status')
            ->find();
        if($spike_info)return [
            'type'=>'spike',
            'activity'=>$spike_info
        ];
        //团购
        $group_info=Db::name('store_goods_group')
            ->where('goods_id',$goods_id)
            ->where('activity_start_time','<=',date('Y-m-d H:i:s'))
            ->where('activity_end_time','>=',date('Y-m-d H:i:s'))
            ->where('stock','>',0)
            ->where('status',1)
            ->hidden('activity_name,create_at,status,perfect')
            ->find();
        if($group_info){
            $db = Db::table('store_goods_group_pre')
                ->alias('a')
                ->join('store_order order','order.order_no = a.order_no')
                ->join('store_member m','m.id = a.mid')
                ->where('a.goods_id',$goods_id)
                ->where('a.end_time','>',time())
                ->where('a.success_time',0);
            $dba = clone $db;
            //查询10条当前尚未完成的团
            $grouping =$dba->where('a.parent_id',0)->where('order.is_pay',1)->field(
                'a.id,a.mid,a.end_time,m.headimg,m.nickname'
            )->limit(10)->select();
            foreach ($grouping as $key => $item) {
                $grouping[$key]['after_time'] = $item['end_time'] - time();
                $dbb = clone $db;
                $child_num = $dbb->where('a.parent_id',$item['id'])->count();
                $grouping[$key]['residue'] = $group_info['complete_num'] - $child_num - 1;
            }

            //查询拼团总人数
            $dbc = clone $db;
            $group_number = $dbc->where('order.is_pay',1)->count();
            return [
                'type'=>'group',
                'activity'=>$group_info,
                'grouping'=>$grouping,
                'group_number'=>$group_number
            ];
        }
        return ['type'=>''];

    }

    private static function favorablerate($goods_id){
        $count = Db::table('store_goods_comment')->where(['is_deleted' => '0','status' => '1','goods_id' => $goods_id])->count();
        $sum = Db::table('store_goods_comment')->where(['is_deleted' => '0','status' => '1','goods_id' => $goods_id])->where('goods_score','>',3)->count();
        $FavorableRate = $count ? ceil($sum *100/$count) : 100;
        return $FavorableRate;
    }

    /**
     * @Notes: 获取商品评价
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/3 20:57
     */
    public function goods_comment(){
        $goods_id = $this->request->param('goods_id');
        $type=$this->request->post('type',0);//0：全部  1：好评 2：有图
        $page = $this->request->param('page',1);
        $db = Db::table('store_goods_comment')->where(['is_deleted' => '0','status' => '1','goods_id' => $goods_id]);
        //聚合查询
        $count['haoping']=Db::table('store_goods_comment')
            ->where(['is_deleted' => '0','status' => '1','goods_id' => $goods_id])
            ->where('goods_score','>',3)
            ->count();
        $count['youtu']=Db::table('store_goods_comment')
            ->where(['is_deleted' => '0','status' => '1','goods_id' => $goods_id])
            ->whereRaw('image is not null')
            ->count();
        $count['quanbu']=Db::table('store_goods_comment')
            ->where(['is_deleted' => '0','status' => '1','goods_id' => $goods_id])
            ->count();
        switch ($type){
            case 1:
                //好评
                $db->where('goods_score','>',3);
                break;
            case 2:
                //有图
                $db->whereRaw('image is not null');
                break;
        }
        $comment = $db->page($page,$this->pagesize)->order('create_at desc')->select();

        $mids = array_unique(array_column($comment, 'mid'));
        $memberList = Db::name("StoreMember")->whereIn('id', $mids)->select();
        foreach ($comment as $key => $item) {
            $comment[$key]['image'] = $comment[$key]['image']?explode('|',$item['image']):[];
            foreach ($memberList as $member) {
                $member['nickname'] = ToolsService::emojiDecode($member['nickname']);
                ($item['mid'] === $member['id']) && $comment[$key]['member'] = $member;
            }
        }

        $data['comment']=$comment;
        $data['count']=$count;

        $this->success('success',$data);

    }


    /**
     * 首页推荐产品
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function recommendgoods(){
        $cates = Db::name('StoreGoodsCate')
            ->where(['is_deleted'=>'0','is_homepage'=>'1','pid'=>'0'])
            ->field('id,cate_title')
            ->order('sort asc,id desc')
            ->select();

        foreach ($cates as &$v){
            $goods=$this->baseSql()
                ->where('a.cate_id','in',self::childCate($v['id']))
                ->where('a.is_homepage',1)
                ->where('a.status',1)
                ->where('a.is_deleted',0)
                ->field('a.id,a.goods_title,a.goods_logo,huaxian_price,'.$this->price_field)
                ->limit(8)
                ->select();
            $v['goods']=$goods;
        }
        $this->success('success',$cates);
    }

    /**
     * @Notes: 猜你喜欢
     * @param int $goods_id
     * @param int $num
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/15 16:44
     */
    public static function LovelyGoods($goods_id = 0,$num = 2){
        $cate_id = Db::table('store_goods')->where('id',$goods_id)->value('cate_id');
        $cate_ids = self::childCate($cate_id);
        $goods_ids = Db::table('store_goods')->where(['status' => '1','is_deleted' => '0'])->where('id','<>',$goods_id)->whereIn('cate_id',$cate_ids)->order("browse desc")->limit($num)->column('id');
        if(count($goods_ids) < $num){
            $goods_ids_supplement = Db::table('store_goods')->where(['status' => '1','is_deleted' => '0'])->whereNotIn('id',$goods_ids)->where('id','<>',$goods_id)->order("browse desc")->limit(10)->column('id');
            $goods_ids_supplement_rand = array_rand($goods_ids_supplement,$num-count($goods_ids));
            if(is_array($goods_ids_supplement_rand)){
                for ($i = 0;$i<$num-count($goods_ids);$i++){
                    $goods_ids[] = $goods_ids_supplement[$goods_ids_supplement_rand[$i]];
                }
            }else{
                $goods_ids[] = $goods_ids_supplement[$goods_ids_supplement_rand];
            }
        }
        return $goods_ids;
    }

    /**
     * @Notes: 获取配置
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/21 17:17
     */
    public function getconfig(){
        $data = [
            'special_brand_title' => sysconf('special_brand_title'),
            'special_brand_desc' => sysconf('special_brand_desc'),
            'special_brand_cover' => sysconf('applet_url'). sysconf('special_brand_cover'),
            'special_cate_title' => sysconf('special_cate_title'),
            'special_cate_desc' => sysconf('special_cate_desc'),
            'special_cate_cover' => sysconf('applet_url'). sysconf('special_cate_cover'),
            'special_goods_title' => sysconf('special_goods_title'),
            'special_goods_desc' => sysconf('special_goods_desc'),
            'special_goods_cover' => sysconf('applet_url'). sysconf('special_goods_cover'),
            'group_nav_img' => sysconf('applet_url'). sysconf('group_nav_img'),
            'spike_nav_img' => sysconf('applet_url'). sysconf('spike_nav_img'),
            'coupon_nav_img' => sysconf('applet_url'). sysconf('coupon_nav_img'),
            'bargain_nav_img' => sysconf('applet_url'). sysconf('bargain_nav_img'),
            'member_nav_img' => sysconf('applet_url'). sysconf('member_nav_img')
        ];
        parent::success('success',$data);
    }

}