<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\store\controller;

use app\store\service\GoodsService;
use controller\BasicAdmin;
use service\DataService;
use service\ToolsService;
use think\Db;
use think\db\Query;
use think\exception\HttpResponseException;

/**
 * 商店商品管理
 * Class Goods
 * @package app\store\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/03/27 14:43
 */
class Goods extends BasicAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreGoods';

    /**
     * 普通商品
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '商品管理';
        $get = $this->request->get();
        $db = Db::name($this->table)->where(['is_deleted' => '0']);
        if (isset($get['tags_id']) && $get['tags_id'] !== '') {
            $db->where('FIND_IN_SET(:id,tags_id)',['id' => $get['tags_id']]);
        }

        if (isset($get['goods_title']) && $get['goods_title'] !== '') {
            $db->whereLike('goods_title', "%{$get['goods_title']}%");
        }
        foreach (['cate_id', 'brand_id', 'type_id','depot_id'] as $field) {
            (isset($get[$field]) && $get[$field] !== '') && $db->where($field, $get[$field]);
        }
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        $get=$this->request->get();
        $query='';
        foreach ($get as $k=>$item) {
            if(!$item)continue;
            if($query){
                $query.='&'.$k.'='.$item;
            }else{
                $query.=$k.'='.$item;
            }
        }
        $this->assign('query',urlencode($query));
        $db->field('*,package_stock-package_sale as residue_stock');
        $order = 'status desc,sort asc,id desc';
        if (isset($get['sort_type']) && $get['sort_type'] !== '') {
            if($get['sort_type'] == '1'){
                $order = 'create_at desc';
            }elseif ($get['sort_type'] == '2'){
                $order = 'create_at asc';
            }elseif ($get['sort_type'] == '3'){
                $order = 'residue_stock asc';
            }
        }
        return parent::_list($db->order($order));
    }

    /**
     * @Notes: 商城数据处理
     * @param $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/23 10:43
     */
    protected function _data_filter(&$data)
    {
        $result = GoodsService::buildGoodsList($data);
        $depots = Db::table('store_goods_depot')->where(['is_deleted' => '0','status' => '1'])->select();
        $this->assign([
            'brands' => $result['brand'],
            'cates'  => ToolsService::arr2table($result['cate']),
            'types' => $result['type'],
            'tags' => $result['tags'],
            'depots' => $depots
        ]);
    }

    /**
     * 添加商品
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function add()
    {
        $this->title = '添加商品';
        $this->assign('isAddMode', '1');
        $this->_form($this->table, 'form');
        if ($this->request->isGet()) {


            $this->_form_assign();
            return $this->_form($this->table, 'form');
        }
        try {
//            $data = $this->_form_build_data();
            $data['lists'] = json_decode($this->request->post('lists'), true);
            $data['list'] = [];
            foreach ($data['lists'] as $list) {
                $data['list'][] = [
                    'goods_id'       => 0,
                    'goods_spec'     => $list[0]['key'],
                    'huaxian_price'   => $list[0]['huaxian'],
                    'market_price'  => $list[0]['market'],
                    'status'         => 1,
                ];
            }
            dump($data);exit();
            Db::transaction(function () use ($data) {
                $goodsID = Db::name($this->table)->insertGetId($data['main']);
                foreach ($data['list'] as &$vo) {
                    $vo['goods_id'] = $goodsID;
                }
                Db::name('StoreGoodsList')->insertAll($data['list']);
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('商品添加失败，请稍候再试！msg:'.$e->getMessage());
        }
        list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('store/goods/index')];
        $this->success('添加商品成功！', "{$base}#{$url}?spm={$spm}");
    }

    /**
     * 编辑商品
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit()
    {
        $this->title = '编辑商品';
        $this->assign('isAddMode', '0');
        $this->_form($this->table, 'form');

        if (!$this->request->isPost()) {
            $goods_id = $this->request->get('id');
            $goods = Db::name($this->table)->where(['id' => $goods_id, 'is_deleted' => '0'])->find();
            empty($goods) && $this->error('需要编辑的商品不存在！');
            $goods['list'] = Db::name('StoreGoodsList')->where(['goods_id' => $goods_id, 'is_deleted' => '0'])->select();
            $goods['tags_id'] = explode(',', isset($goods['tags_id']) ? $goods['tags_id'] : '');
            $goods['insider_back_ratio'] = json_decode($goods['insider_back_ratio'],true);
            $goods['ordinary_back_ratio'] = json_decode($goods['ordinary_back_ratio'],true);
            $this->assign('isAddMode', '0');
            $fields = 'goods_spec,goods_id,huaxian_price huaxian,market_price market';
            $defaultValues = Db::name('StoreGoodsList')->where(['goods_id' => $goods_id, 'is_deleted' => '0'])->column($fields);
            $this->assign('defaultValues', json_encode($defaultValues, JSON_UNESCAPED_UNICODE));
            $this->_form_assign();
            return $this->fetch('form', ['vo' => $goods, 'title' => '编辑商品']);
        }
        try {
            $data = $this->_form_build_data();
            unset($data['main']['spec_id']);
            $goods_id = $this->request->post('id');
            $goods = Db::name($this->table)->where(['id' => $goods_id, 'is_deleted' => '0'])->find();
            empty($goods) && $this->error('商品编辑失败，请稍候再试！');
            foreach ($data['list'] as &$vo) {
                $vo['goods_id'] = $goods_id;
            }
            Db::transaction(function () use ($data, $goods_id, $goods) {
                // 更新商品主表
                $where = ['id' => $goods_id, 'is_deleted' => '0'];
                Db::name('StoreGoods')->where($where)->update(array_merge($goods, $data['main']));
                /**
                 * 库存不置零
                 * @author  jungshen
                 */
                //循环规格 存在的修改 不存在的添加 没有了的删除
                $valid_id_arr=[];
                foreach ($data['list'] as $k=>$v){
                    $sgl_whr=[
                        'goods_spec'=>$v['goods_spec'],
                        'goods_id'=>$v['goods_id'],
                    ];
                    $goodsList=Db::name('StoreGoodsList')->where($sgl_whr)->field('id')->find();
                    if(isset($goodsList['id'])&&$goodsList['id']>0){
                        //存在这个规格
                        array_push($valid_id_arr,$goodsList['id']);
                        Db::name('StoreGoodsList')->where('id',$goodsList['id'])->update($v);
                    }else{
                        //不存在这个规格
                        $goodsListID=Db::name('StoreGoodsList')->insertGetId($v);
                        array_push($valid_id_arr,$goodsListID);
                    }
                }
                Db::name('StoreGoodsList')
                    ->where('goods_id',$goods_id)
                    ->where('id','not in',$valid_id_arr)
                    ->delete();
                // 更新商品详细
                //Db::name('StoreGoodsList')->where(['goods_id' => $goods_id])->delete();
                //Db::name('StoreGoodsList')->insertAll($data['list']);
                //更新库存
                //Db::name('StoreGoodsStock')->where(['goods_id' => $goods_id])->delete();
                GoodsService::syncGoodsStock($goods_id);
                /**
                 * 库存不置零END
                 */
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('商品编辑失败，请稍候再试！');
        }
        list($base, $query, $url) = [url('@admin'), $this->request->get('q'), url('store/goods/index')];
        $this->success('商品编辑成功！', "{$base}#{$url}?{$query}");
    }

    /**
     * 唯一数字编码
     * @param integer $length
     * @return string
     */
    public static function uniqidNumberCode($length = 10)
    {
        $time = time() . '';
        if ($length < 10) $length = 10;
        $string = ($time[0] + $time[1]) . substr($time, 2) . rand(0, 9);
        while (strlen($string) < $length) $string .= rand(0, 9);
        return $string;
    }

    /**
     * 数据增量保存
     * @param Query|string $dbQuery 数据查询对象
     * @param array $data 需要保存或更新的数据
     * @param string $key 条件主键限制
     * @param array $where 其它的where条件
     * @return boolean|integer
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public static function dataSave($dbQuery, $data, $key = 'id', $where = [])
    {
        $db = is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery;
        list($table, $value) = [$db->getTable(), $data[$key] ?? null];
        $map = isset($where[$key]) ? [] : (is_string($value) ? [[$key, 'in', explode(',', $value)]] : [$key => $value]);
        if (is_array($info = Db::table($table)->master()->where($where)->where($map)->find()) && !empty($info)) {
            if (Db::table($table)->strict(false)->where($where)->where($map)->update($data) !== false) {
                return isset($info[$key]) ? $info[$key] : true;
            } else {
                return false;
            }
        } else {
            return Db::table($table)->strict(false)->insertGetId($data);
        }
    }

    protected function _form_filter(&$data)
    {
        // 生成商品ID
        if (empty($data['id'])) $data['id'] = self::uniqidNumberCode();
        if($this->request->isGet()){
            $fields = 'goods_spec,goods_id,huaxian_price huaxian,market_price market';
            $defaultValues = Db::name('StoreGoodsList')->where(['goods_id' => $data['id'], 'is_deleted' => '0'])->column($fields);
            $this->assign('defaultValues', json_encode($defaultValues, JSON_UNESCAPED_UNICODE));

            $cates = (array)Db::name('StoreGoodsCate')->where(['status' => '1', 'is_deleted' => '0'])->order('sort asc,id desc')->select();
            $this->assign('cates', ToolsService::arr2table($cates));
        } elseif ($this->request->isPost()){
            if (empty($data['goods_logo'])) $this->error('商品LOGO不能为空，请上传图片');
            if (empty($data['goods_image'])) $this->error('商品展示图片不能为空，请上传图片');
            Db::name('StoreGoodsList')->where(['goods_id' => $data['id']])->update(['status' => '0']);
            foreach (json_decode($data['lists'], true) as $vo) {
                self::dataSave('StoreGoodsList', [
                    'goods_id'       => $data['id'],
                    'goods_spec'     => $vo[0]['key'],
                    'huaxian_price'   => $vo[0]['huaxian'],
                    'market_price'  => $vo[0]['market'],
                    'status'         => 1,
                ], 'goods_spec', ['goods_id' => $data['id']]);
            }
//            Db::name('StoreGoodsList')->insertAll($list);
//            dump($data);exit();
        }
//        dump($data);
    }

    /**
     * 表单结果处理
     * @param boolean $result
     */
    protected function _form_result($result)
    {
        if ($result && $this->request->isPost()) {
            list($base, $query, $url) = [url('@admin'), $this->request->get('q'), url('store/goods/index')];
            $this->success('商品编辑成功！', "{$base}#{$url}?{$query}");
        }
    }

    /**
     * 表单数据处理
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    protected function _form_assign()
    {
        list($where, $order) = [['status' => '1', 'is_deleted' => '0'], 'sort asc,id desc'];
        $specs = (array)Db::name('StoreGoodsSpec')->where($where)->order($order)->select();
        $brands = (array)Db::name('StoreGoodsBrand')->where($where)->order($order)->select();
        $cates = (array)Db::name('StoreGoodsCate')->where($where)->order($order)->select();
        $tags = (array)Db::name('StoreGoodsTags')->where($where)->order($order)->select();
        $types = (array)Db::name('StoreGoodsType')->where($where)->order($order)->select();
        // 所有的商品信息
        $where = ['is_deleted' => '0', 'status' => '1'];
        $goodsListField = 'goods_id,goods_spec,goods_stock,goods_sale';
        $goods = Db::name('StoreGoods')->field('id,goods_title')->where($where)->select();
        $list = Db::name('StoreGoodsList')->field($goodsListField)->where($where)->select();
        foreach ($goods as $k => $g) {
            $goods[$k]['list'] = [];
            foreach ($list as $v) {
                ($g['id'] === $v['goods_id']) && $goods[$k]['list'][] = $v;
            }
        }
        array_unshift($specs, ['spec_title' => ' - 不使用规格模板 -', 'spec_param' => '[]', 'id' => '0']);
        $depots = (array)Db::table('store_goods_depot')->where(['is_deleted' => '0','status' => '1'])->select();
        $levels = (array)Db::table('store_member_level')->where(['is_deleted' => '0','status' => '1'])->select();
        $this->assign([
            'specs'  => $specs,
            'cates'  => ToolsService::arr2table($cates),
            'brands' => $brands,
            'tags' => $tags,
            'all'    => $goods,
            'types' => $types,
            'depots' => $depots,
            'levels' => $levels
        ]);
        //所有分销模板
        $ratio_templates=Db::name('rebate_template')
            ->where('status',1)
            ->field('id,title')
            ->select();
        $this->assign('ratio_templates',$ratio_templates);
    }

    /**
     * @Notes: 读取POST表单数据
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/5 14:27
     */
    protected function _form_build_data()
    {
        list($main, $list, $post, $verify, $ratio) = [[], [], $this->request->post(), false,[]];
        empty($post['goods_logo']) && $this->error('商品LOGO不能为空，请上传后再提交数据！');
        // 商品主数据组装
        $main['cate_id'] = $this->request->post('cate_id', '0');
        $main['spec_id'] = $this->request->post('spec_id', '0');
        $main['brand_id'] = $this->request->post('brand_id', '0');
        $main['type_id'] = $this->request->post('type_id', '0');
        $main['depot_id'] = $this->request->post('depot_id', '0');
        $main['goods_logo'] = $this->request->post('goods_logo', '');
        $main['goods_title'] = $this->request->post('goods_title', '');
        $main['goods_video'] = $this->request->post('goods_video', '');
        $main['goods_image'] = $this->request->post('goods_image', '');
        $main['goods_desc'] = $this->request->post('goods_desc', '', null);
        $main['goods_content'] = $this->request->post('goods_content', '');
        $main['tags_id'] = join(',', isset($post['tags_id']) ? $post['tags_id'] : []);
        $main['is_homepage'] = $this->request->post('is_homepage', '');
        $main['weight'] = $this->request->post('weight', '');
        $main['back_ratio'] = $this->request->post('back_ratio', '');
        $main['insider_back_ratio'] = $this->request->post('insider_back_ratio', '');
        $main['ordinary_back_ratio'] = $this->request->post('ordinary_back_ratio', '');
        $main['exemption_from_postage'] = $this->request->post('exemption_from_postage', '');
        $main['service_txt'] = $this->request->post('service_txt', '');

        // 商品从数据组装
        if (!empty($post['goods_spec'])) {
            foreach ($post['goods_spec'] as $key => $value) {
                $goods = [];
                $goods['goods_spec'] = $value;
                $goods['huaxian_price'] = $post['huaxian_price'][$key];
                $goods['market_price'] = $post['market_price'][$key];
//                $goods['selling_price'] = $post['selling_price'][$key];
//                $goods['status'] = intval(!empty($post['spec_status'][$key]));
                $goods['status'] = 1;
                !empty($goods['status']) && $verify = true;
                $list[] = $goods;
            }
        } else {
            $this->error('没有商品规格或套餐信息哦！');
        }
        !$verify && $this->error('没有设置有效的商品规格！');
        //级别返现比例组装
        if(!empty($post['level_ratio'])){
            $levels = Db::table('store_member_level')->where(['is_deleted' => '0','status' => '1'])->select();
            foreach ($post['level_ratio'] as $key => $value) {
                $level_ratio = [];
                foreach ($levels as $level) {
                    $level_ratio[$level['id']] = $post['level_ratio_'.$level['id']][$key];
                }
                $ratio[$value] = $level_ratio;
                $main[$value] = json_encode($level_ratio);
            }
        }
        return ['main' => $main, 'list' => $list, 'ratio' => $ratio];
    }

    /**
     * 商品库存信息更新
     * @return string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function stock()
    {
        if (!$this->request->post()) {
            $goods_id = $this->request->get('id');
            $goods = Db::name('StoreGoods')->where(['id' => $goods_id, 'is_deleted' => '0'])->find();
            empty($goods) && $this->error('该商品无法操作入库操作！');
            $where = ['goods_id' => $goods_id, 'status' => '1', 'is_deleted' => '0'];
            $goods['list'] = Db::name('StoreGoodsList')->where($where)->select();
            return $this->fetch('', ['vo' => $goods]);
        }
        // 入库保存
        $goods_id = $this->request->post('id');
        list($post, $data) = [$this->request->post(), []];
        foreach ($post['spec'] as $key => $spec) {
            if ($post['stock'][$key] > 0 || $post['stock'][$key] < 0) {
                $data[] = [
                    'goods_stock' => $post['stock'][$key],
                    'stock_desc'  => $this->request->post('desc'),
                    'goods_spec'  => $spec, 'goods_id' => $goods_id,
                ];
            }
        }
        empty($data) && $this->error('无需入库的数据哦！');
        if (Db::name('StoreGoodsStock')->insertAll($data) !== false) {
            GoodsService::syncGoodsStock($goods_id);
            $this->success('商品入库成功！', '');
        }
        $this->error('商品入库失败，请稍候再试！');
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
            $this->success("商品下架成功！", '');
        }
        $this->error("商品下架失败，请稍候再试！");
    }

    /**
     * 商品禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("商品上架成功！", '');
        }
        $this->error("商品上架失败，请稍候再试！");
    }
    public function export(){
        if($this->request->isGet()){
            $this->_form_assign();
            return $this->fetch();
        }else{
            $get=$this->request->post();
            $db = Db::name($this->table)->where(['is_deleted' => '0']);
            if (isset($get['tags_id']) && $get['tags_id'] !== '') {
                $db->where('FIND_IN_SET(:id,tags_id)',['id' => $get['tags_id']]);
            }

            if (isset($get['goods_title']) && $get['goods_title'] !== '') {
                $db->whereLike('goods_title', "%{$get['goods_title']}%");
            }
            foreach (['cate_id', 'brand_id', 'type_id','depot_id'] as $field) {
                (isset($get[$field]) && $get[$field] !== '') && $db->where($field, $get[$field]);
            }
            if (isset($get['create_at']) && $get['create_at'] !== '') {
                list($start, $end) = explode(' - ', $get['create_at']);
                $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
            }
            $db->field('*,package_stock-package_sale as residue_stock');
            $order = 'is_homepage desc,status desc,sort asc,id desc';
            if (isset($get['sort_type']) && $get['sort_type'] !== '') {
                if($get['sort_type'] == '1'){
                    $order = 'create_at desc';
                }elseif ($get['sort_type'] == '2'){
                    $order = 'create_at asc';
                }elseif ($get['sort_type'] == '3'){
                    $order = 'residue_stock asc';
                }
            }
            $list = $db->select();
            $result = GoodsService::buildGoodsList($list);
            $list = $result['list'];
            foreach ($list as &$item) {
                $item['brand'] = $item['brand']['brand_title'];
                $goods_cate = '';
                foreach ($item['cate'] as $k => $v) {
                    $goods_cate .= $v['cate_title'];
                    if($k + 1 < count($item['cate'])){
                        $goods_cate .= '>';
                    }
                }
                $item['cate'] = $goods_cate;
            }
            //导出数据

            $GoodsService = new GoodsService();
            $fileName='商品列表_'.date('Y-m-d H:i:s');
            $head=['ID','品牌','分类','商品名称','规格信息','规格信息','规格信息','状态','添加时间'];
            $keys=['id','brand','cate','goods_title','spec','spec','spec','status','create_at'];

            $GoodsService->export($fileName,$list,$head,$keys);

        }
    }

}
