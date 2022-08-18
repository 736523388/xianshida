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
use think\Db;

/**
 * 商店品牌管理
 * Class Brand
 * @package app\store\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/03/27 14:43
 */
class MemberLevel extends BasicAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreMemberLevel';

    /**
     * 品牌列表
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '会员等级管理';
        $get = $this->request->get();
        $db = Db::name($this->table)->where(['is_deleted' => '0']);
        if (isset($get['level_title']) && $get['level_title'] !== '') {
            $db->whereLike('level_title', "%{$get['level_title']}%");
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
        $this->title = '添加等级';
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
        $this->title = '编辑等级';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes:
     * @param $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 15:58
     */
    protected function _form_filter(&$data)
    {
        if (!$this->request->isPost()) {
            $get = $this->request->get();
            $db = Db::name($this->table)->where(['is_deleted' => '0']);
            if(isset($get['id']) && $get['id'] !== ''){
                $db->where('id','<>',$get['id']);
            }
            //dump($db->select());
            $this->assign('levels',$db->select());
        }
    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
        if ($result !== false) {
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('xueao/member_level/index')];
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
            $this->success("等级删除成功！", '');
        }
        $this->error("等级删除失败，请稍候再试！");
    }

    /**
     * 品牌禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("等级禁用成功！", '');
        }
        $this->error("等级禁用失败，请稍候再试！");
    }

    /**
     * 品牌签禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("等级启用成功！", '');
        }
        $this->error("等级启用失败，请稍候再试！");
    }

}
