<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/7/23
 * Time: 17:41
 */
namespace app\xueao\controller;

use controller\XueaoAdmin;
use think\Db;

class RebateTemplate extends XueaoAdmin
{
    public $table='rebate_template';
    /**
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '分销模板管理';
        $get = $this->request->get();
        $db = Db::name($this->table);
        if (isset($get['title']) && $get['title'] !== '') {
            $db->whereLike('title', "%{$get['title']}%");
        }
        return parent::_list($db->order('id desc'));
    }

    function _data_filter(&$data){
        foreach ($data as &$v){
            $v['ratio']=json_decode($v['ratio'],true);
        }
    }


    /**
     * @param $data
     */
    public function _form_filter(&$data,$extend_data)
    {
        if($this->request->isGet()){
            if($this->request->get('id')){
                $this->title='编辑分销模板';
                $data['ratio']=json_decode($data['ratio'],true);
            }else{
                $this->title='添加分销模板';
            }
        }else{
            $data['ratio']=json_encode($data['ratio']);
            if(!$this->request->post('id')){
                $data['status']=1;
            }
        }
    }

}