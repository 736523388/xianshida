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

class Store extends XueaoAdmin
{
    public $table='store';
    /**
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '门店管理';
        $get = $this->request->get();
        $db = Db::name($this->table);
        if (isset($get['name']) && $get['name'] !== '') {
            $db->whereLike('name', "%{$get['name']}%");
        }
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->order('sort,id desc'));
    }

    /**
     * @param $data
     */
    public function _form_filter(&$data,$extend_data)
    {
        if($this->request->isGet()){
            if($this->request->get('id')){
                $this->title='编辑门店';
            }else{
                $this->title='添加门店';
            }
        }else{
            if(!$this->request->post('id')){
                $data['status']=1;
                $data['create_at']=date('Y-m-d H:i:s');
            }
        }
    }
    function choose_position(){
        return $this->fetch();
    }
}