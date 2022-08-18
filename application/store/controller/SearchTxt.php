<?php
namespace app\store\controller;
use controller\BasicAdmin;
use service\DataService;
use think\Db;

class SearchTxt extends BasicAdmin {
    public $table = 'StoreSearchTxt';

    /**
     * @Notes:
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/17 17:41
     */
    public function index(){
        $this->title = '热门搜索词管理';
        $get = $this->request->get();
        $db = Db::name($this->table)->where(['is_deleted' => '0']);
        if (isset($get['title']) && $get['title'] !== '') {
            $db->whereLike('title', "%{$get['title']}%");
        }
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->order('sort asc,id desc'));
    }

    /**
     * @Notes:
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/17 17:41
     */
    public function add()
    {
        $this->title = '添加搜索词';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes:
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/17 17:41
     */
    public function edit()
    {
        $this->title = '编辑搜索词';
        return $this->_form($this->table, 'form');
    }
    /**
     * 表单提交数据处理
     * @param array $data
     */
    protected function _form_filter(&$data)
    {

    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
//    protected function _form_result($result)
//    {
//        if ($result !== false) {
//            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('store/search_txt/index')];
//            $this->success('数据保存成功！', "{$base}#{$url}?spm={$spm}");
//        }
//    }

    /**
     * 删除品牌
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function del()
    {
        if (DataService::update($this->table)) {
            $this->success("删除成功！", '');
        }
        $this->error("删除失败，请稍候再试！");
    }

    /**
     * 品牌禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("禁用成功！", '');
        }
        $this->error("禁用失败，请稍候再试！");
    }

    /**
     * 品牌签禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("启用成功！", '');
        }
        $this->error("启用失败，请稍候再试！");
    }
}