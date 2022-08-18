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
use think\Db;

/**
 * 商店品牌管理
 * Class Brand
 * @package app\store\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/03/27 14:43
 */
class GoodsTags extends BasicAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreGoodsTags';

    /**
     * @Notes: 标签列表
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 14:33
     */
    public function index()
    {
        $this->title = '标签管理';
        $get = $this->request->get();
        $db = Db::name($this->table)->where(['is_deleted' => '0']);
        if (isset($get['tags_title']) && $get['tags_title'] !== '') {
            $db->whereLike('tags_title', "%{$get['tags_title']}%");
        }
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->order('sort asc,id desc'));
    }

    /**
     * @Notes: 添加标签
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 14:34
     */
    public function add()
    {
        $this->title = '添加标签';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes: 编辑标签
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 14:34
     */
    public function edit()
    {
        $this->title = '编辑标签';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes: 表单提交数据处理
     * @param $data
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 14:34
     */
    protected function _form_filter($data)
    {
        if ($this->request->isPost()) {
            if(!isset($data['image']) || $data['image'] === ''){
                $this->error('请上传图片');
            }
        }
    }

    /**
     * @Notes: 删除标签
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 14:34
     */
    public function del()
    {
        if (DataService::update($this->table)) {
            $this->success("标签删除成功！", '');
        }
        $this->error("标签删除失败，请稍候再试！");
    }

    /**
     * @Notes: 禁用标签
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 14:34
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("标签禁用成功！", '');
        }
        $this->error("标签禁用失败，请稍候再试！");
    }

    /**
     * @Notes: 启用标签
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 14:34
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("标签启用成功！", '');
        }
        $this->error("标签启用失败，请稍候再试！");
    }
}
