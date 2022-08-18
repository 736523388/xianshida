<?php
namespace app\store\controller;
use controller\BasicAdmin;
use service\DataService;
use think\Db;

class Bargain extends BasicAdmin
{
    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreGoodsBargain';

    /**
     * @Notes: 砍价活动列表
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/2 15:21
     */
    public function index(){
        $this->title = '砍价活动管理';
        $get = $this->request->get();
        $db = Db::name($this->table);
        if(isset($get['status_id']) && $get['status_id'] !== ''){
            if($get['status_id'] == '1'){
                $db->where('activity_start_time','>',date('Y-m-d H:i:s'));
            }elseif ($get['status_id'] == '3'){
                $db->where('activity_end_time','<',date('Y-m-d H:i:s'));
            }else{
                $db->whereBetweenTimeField('activity_start_time','activity_end_time');
            }
        }
        return parent::_list($db->order('id asc'));
    }
    public function _data_filter(&$data){
        foreach ($data as $key => $value) {
            if($value['activity_start_time'] > date('Y-m-d H:i:s')){
                $status = '未开始';
            }elseif ($value['activity_end_time'] < date('Y-m-d H:i:s')){
                $status = '已结束';
            }else{
                $status = '进行中';
            }
            $data[$key]['status_txt'] = $status;
        }
    }

    /**
     * @Notes: 添加活动
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/2 15:20
     */
    public function add(){
        $this->title = '添加砍价活动';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes: 编辑活动
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/2 15:20
     */
    public function edit()
    {
        $this->title = '编辑砍价活动';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes: 选择商品
     * @return mixed|\think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/8 11:39
     */
    public function choose_goods(){
        $db = Db::table('store_goods')->where(['is_deleted' => '0','status' => '1']);
        if($this->request->isGet()){
//            $list = $db->limit(20)->order('create_at desc')->select();
//            $this->assign('list',json_encode($list));
            return $this->fetch();
        }else{
            $keyword = $this->request->get('keyword');
            $page = $this->request->get('page');
            $pagesize = $this->request->get('pagesize');
            if ($keyword !== '') {
                $db->whereLike('goods_title', "%{$keyword}%");
            }
            $data = [
                'code' => 0,
                'msg' => '',
                'count' => $db->count(),
                'data' => $db->page($page)->limit($pagesize)->select()
            ];
            return json($data);
        }

    }
    /**
     * 表单提交数据处理
     * @param array $data
     */
    protected function _form_filter(&$data)
    {
        if ($this->request->isPost()) {
            if($data['activity_end_time'] < date('Y-m-d H:i:s')){
                $this->error('请选择正确的时间段！');
            }
            if($data['activity_start_time'] >= $data['activity_end_time']){
                $this->error('活动结束时间必须大于开始时间！');
            }
            empty($data['goods_id']) && $this->error('请选择商品！');
            //检测商品是否已经参加活动
            $map1 = [
                ['activity_start_time', '>=', $data['activity_start_time']],
                ['activity_start_time', '<=', $data['activity_end_time']],
            ];
            $map2 = [
                ['activity_start_time', '<=', $data['activity_start_time']],
                ['activity_end_time', '>=', $data['activity_end_time']],
            ];
            $map3 = [
                ['activity_end_time', '>=', $data['activity_start_time']],
                ['activity_end_time', '<=', $data['activity_end_time']],
            ];
            //是否团购活动
            if(Db::table('store_goods_group')->where('goods_id',$data['goods_id'])->where(function ($query) use($map1,$map2,$map3){
                $query->whereOr([$map1,$map2,$map3]);
            })->count()){
                $this->error('该商品在此活动期间已有其他的拼团活动了！');
            }
            //是否秒杀活动
            if(Db::table('store_goods_spike')->where('goods_id',$data['goods_id'])->where(function ($query) use($map1,$map2,$map3){
                $query->whereOr([$map1,$map2,$map3]);
            })->count()){
                $this->error('该商品在此活动期间已有其他的秒杀活动了！');
            }
            if(empty($data['id'])){
                if(Db::table('store_goods_bargain')->where('goods_id',$data['goods_id'])->where(function ($query) use($map1,$map2,$map3){
                    $query->whereOr([$map1,$map2,$map3]);
                })->count()){
                    $this->error('该商品在此活动期间已有其他的砍价活动了！');
                }
            }else{
                if(Db::table('store_goods_spike')->where('goods_id',$data['goods_id'])->where('id','<>',$data['id'])->where(function ($query) use($map1,$map2,$map3){
                    $query->whereOr([$map1,$map2,$map3]);
                })->count()){
                    $this->error('该商品在此活动期间已有其他的砍价活动了！');
                }
            }
            if($data['low_price']>=$data['activity_price']){
                $this->error('底价必须小于活动价');
            }
            if(!isset($data['stock']))$data['stock']=$data['activity_stock'];

        }else{
            $data['goods_title'] = isset($data['goods_id']) ? Db::table('store_goods')->where('id',$data['goods_id'])->value('goods_title') : '';
        }
    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
        if ($result !== false) {
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('index')];
            $this->success('数据保存成功！', "{$base}#{$url}?spm={$spm}");
        }
    }
    /**
     * 删除商品
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function del()
    {
        if (DataService::update($this->table)) {
            $this->success("商品删除成功！", '');
        }
        $this->error("商品删除失败，请稍候再试！");
    }

    /**
     * 商品禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("活动下架成功！", '');
        }
        $this->error("活动下架失败，请稍候再试！");
    }

    /**
     * 商品禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("活动上架成功！", '');
        }
        $this->error("活动上架失败，请稍候再试！");
    }
}