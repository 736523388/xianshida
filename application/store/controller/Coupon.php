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

use controller\BasicAdmin;
use service\DataService;
use service\ToolsService;
use think\Db;

/**
 * 商店品牌管理
 * Class Brand
 * @package app\store\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/03/27 14:43
 */
class Coupon extends BasicAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreCoupon';

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
        $this->title = '优惠券管理';
        $get = $this->request->get();
        $db = Db::name($this->table)->where(['is_deleted' => '0']);
        if (isset($get['coupon_name']) && $get['coupon_name'] !== '') {
            $db->whereLike('coupon_name', "%{$get['coupon_name']}%");
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
        $this->title = '添加优惠券';
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
        $this->title = '编辑优惠券';
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
            $data['use_level'] = explode(',', isset($data['use_level']) ? $data['use_level'] : '');
            if(isset($data['time_type']) && $data['time_type'] == 1){
                $data['coupon_time']=  date('Y-m-d',$data['coupon_start_time']). ' - ' .date('Y-m-d',$data['coupon_end_time']);
            }else{
                $data['coupon_time']=  '';
            }

            $this->_form_assign();
        }else{
            if(!isset($data['level_limits'])) $data['level_limits'] = 0;
            if(!isset($data['time_type'])) $data['time_type'] = 1;

            if($data['level_limits']=='1'){
                if (isset($data['use_level']) && is_array($data['use_level'])) {
                    sort($data['use_level']);
                    $data['use_level'] = join(',', $data['use_level']);
                }else{
                    $this->error('请勾选会员等级');
                }
            }
            if($data['time_type'] == '1'){
                if (isset($data['coupon_time']) && $data['coupon_time'] !== '') {
                    list($start, $end) = explode(' - ', $data['coupon_time']);
                    $data['coupon_start_time'] = strtotime("{$start} 00:00:00");
                    $data['coupon_end_time'] = strtotime("{$end} 23:59:59");
                    unset($data['coupon_time']);
                }else{
                    $this->error('请选择日期范围');
                }
            }
            if($data['coupon_auth_type'] == '2'){
                if(!isset($data['coupon_auth_cate']) || $data['coupon_auth_cate'] == ''){
                    $this->error('请选择商品分类');
                }
            }elseif ($data['coupon_auth_type'] == '3'){
                if(!isset($data['coupon_auth_brand']) || $data['coupon_auth_brand'] == ''){
                    $this->error('请选择商品品牌');
                }
            }elseif ($data['coupon_auth_type'] == '4'){
                if(!isset($data['coupon_auth_goods']) || $data['coupon_auth_goods'] == ''){
                    $this->error('请选择商品');
                }
            }

            //dump($data);exit();
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
        $levels = (array)Db::table('store_member_level')->where($where)->order($order)->select();
        $brands = (array)Db::name('StoreGoodsBrand')->where($where)->order($order)->select();
        $cates = (array)Db::name('StoreGoodsCate')->where($where)->order($order)->select();
        $goods = (array)Db::name('StoreGoods')->field('id,goods_title')->where($where)->select();
        $this->assign([
            'cates'  => ToolsService::arr2table($cates),
            'brands' => $brands,
            'levels' => $levels,
            'goods' => $goods
        ]);
    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
        if ($result !== false) {
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('store/coupon/index')];
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
            $this->success("优惠券删除成功！", '');
        }
        $this->error("优惠券删除失败，请稍候再试！");
    }

    /**
     * 品牌禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("优惠券禁用成功！", '');
        }
        $this->error("优惠券禁用失败，请稍候再试！");
    }

    /**
     * 品牌签禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("优惠券启用成功！", '');
        }
        $this->error("优惠券启用失败，请稍候再试！");
    }

}
