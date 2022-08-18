<?php
namespace app\store\controller;
use controller\BasicAdmin;
use service\DataService;
use think\Db;

class FullGift extends BasicAdmin
{
    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'store_full_gift';

    /**
     * @Notes: 满赠活动列表
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
        $this->title = '满赠活动管理';
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
        return parent::_list($db->order('id','asc'));
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
        $this->title = '添加满赠活动';
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
        $this->title = '编辑满赠活动';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes: 表单提交数据处理
     * @param $data
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/11 13:47
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
        }
    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
        if ($result !== false) {
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('store/full_gift/index')];
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
            $this->success("删除成功！", '');
        }
        $this->error("删除失败，请稍候再试！");
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