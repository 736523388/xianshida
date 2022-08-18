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

use controller\BasicAdmin;
use service\DataService;
use think\Db;

/**
 * 商店规格管理
 * Class Spec
 * @package app\store\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/03/27 14:43
 */
class GoodsDepot extends BasicAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreGoodsDepot';

    /**
     * 商品列表
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '仓库管理';
        $get = $this->request->get();
        $db = Db::name($this->table)->where(['is_deleted' => '0']);
        if (isset($get['depot_title']) && $get['depot_title'] !== '') {
            $db->whereLike('depot_title', "%{$get['depot_title']}%");
        }
        if (isset($get['date']) && $get['date'] !== '') {
            list($start, $end) = explode(' - ', $get['date']);
            $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->order('id desc'));
    }

    /**
     * 列表数据处理
     * @param array $data
     */
    protected function _index_data_filter(&$data)
    {

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
        $this->title = '添加仓库';
        return $this->_form($this->table, 'form');
    }

    /**
     * 编辑商品
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function edit()
    {
        $this->title = '编辑仓库';
        return $this->_form($this->table, 'form');
    }

    /**
     * 表单数据处理
     * @param array $vo
     */
    protected function _form_filter(&$vo)
    {

    }

    /**
     * 删除商品规格
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function del()
    {
        if (DataService::update($this->table)) {
            $this->success("商品仓库删除成功！", '');
        }
        $this->error("商品仓库删除失败，请稍候再试！");
    }

    /**
     * 商品规格禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("商品仓库禁用成功！", '');
        }
        $this->error("商品仓库禁用失败，请稍候再试！");
    }

    /**
     * 商品规格禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("商品仓库启用成功！", '');
        }
        $this->error("商品仓库启用失败，请稍候再试！");
    }

}
