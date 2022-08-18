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

namespace app\xueao\controller;

use controller\BasicAdmin;
use service\DataService;
use service\ToolsService;
use think\Db;

/**
 * Class Banner
 * @package app\xueao\controller
 */
class Banner extends BasicAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreBanner';

    /**
     * 优惠券列表
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '轮播图管理';
        $get = $this->request->get();
        $db = Db::name($this->table)->where(['is_deleted' => '0']);
        if (isset($get['banner_title']) && $get['banner_title'] !== '') {
            $db->whereLike('banner_title', "%{$get['banner_title']}%");
        }
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->order('sort asc,id desc'));
    }

    /**
     * @Notes: 列表数据优化
     * @param $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/10/31 14:49
     */
    public function _data_filter(&$data){

    }
    /**
     * 添加品牌
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function add()
    {
        $this->title = '添加轮播图';
        return $this->_form($this->table, 'form');
    }

    /**
     * 编辑品牌
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function edit()
    {
        $this->title = '编辑轮播图';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes: 标签前置回调
     * @param $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/5 21:33
     */
    protected function _form_filter(&$data)
    {
        if ($this->request->isGet()) {
            $this->_form_assign();
        }else{
            if(!isset($data['image']) || $data['image'] == ''){
                $this->error('请上传图片');
            }
            if($data['banner_type'] == 'brand'){
                if(!isset($data['brand_id']) || $data['brand_id'] == ''){
                    $this->error('请选择商品品牌');
                }
            }elseif ($data['banner_type'] == 'special'){
                if(!isset($data['special_id']) || $data['special_id'] == ''){
                    $this->error('请选择推荐专题');
                }
            }elseif ($data['banner_type'] == 'goods'){
                if(!isset($data['goods_id']) || $data['goods_id'] == ''){
                    $this->error('请选择商品');
                }
            }
        }
    }

    /**
     * @Notes: 表单数据处理
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/5 21:33
     */
    protected function _form_assign(){
        list($where, $order) = [['status' => '1', 'is_deleted' => '0'], 'sort asc,id desc'];
        $brands = (array)Db::name('StoreGoodsBrand')->field('id,brand_title')->where($where)->order($order)->select();
        $goods = (array)Db::name('StoreGoods')->field('id,goods_title')->where($where)->select();
        $special = (array)Db::name('StoreGoodsSpecial')->field('id,special_title')->where($where)->order($order)->select();
        $this->assign([
            'brands' => $brands,
            'goods' => $goods,
            'special' => $special
        ]);
    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
        if ($result !== false) {
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('xueao/banner/index')];
            $this->success('数据保存成功！', "{$base}#{$url}?spm={$spm}");
        }
    }

    /**
     * 删除品牌
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function del()
    {
        if (DataService::update($this->table)) {
            $this->success("轮播图删除成功！", '');
        }
        $this->error("轮播图删除失败，请稍候再试！");
    }

    /**
     * 品牌禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("轮播图禁用成功！", '');
        }
        $this->error("轮播图禁用失败，请稍候再试！");
    }

    /**
     * 品牌签禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("轮播图启用成功！", '');
        }
        $this->error("轮播图启用失败，请稍候再试！");
    }

}
