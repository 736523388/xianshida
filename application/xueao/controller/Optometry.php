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

class Optometry extends XueaoAdmin
{
    public $table='optometry';
    /**
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '验光记录管理';
        $get = $this->request->get();
        $db = Db::name($this->table);
        if (isset($get['name']) && $get['name'] !== '') {
            $db->whereLike('name', "%{$get['name']}%");
        }
        if (isset($get['addtime']) && $get['addtime'] !== '') {
            list($start, $end) = explode(' - ', $get['addtime']);
            $db->whereBetween('addtime', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->order('id desc'));
    }

    /**
     * @param $data
     */
    public function _form_filter(&$data,$extend_data)
    {
        if($this->request->isGet()){
            $this->get_all_member();
            if($this->request->get('id')){
                $this->title='编辑验光记录';
                $data['birthday']?$data['birthday']=date('Y-m-d',$data['birthday']):'';
                $data['test_time']?$data['test_time']=date('Y-m-d',$data['test_time']):'';
                $data['review_time']?$data['review_time']=date('Y-m-d',$data['review_time']):'';
                $data['yanguangchufang']=json_decode($data['yanguangchufang'],true);
                $data['peijingchufang']=json_decode($data['peijingchufang'],true);
            }else{
                $this->title='添加验光记录';
                $data['mid']=input('get.mid',0);
            }
        }else{
            if(!$this->request->post('id')){
                $data['status']=1;
                $data['addtime']=time();
            }
            $data['birthday']?$data['birthday']=strtotime($data['birthday']):'';
            $data['test_time']?$data['test_time']=strtotime($data['test_time']):'';
            $data['review_time']?$data['review_time']=strtotime($data['review_time']):'';
            $data['yanguangchufang']=json_encode($data['yanguangchufang']);
            $data['peijingchufang']=json_encode($data['peijingchufang']);
        }
    }

    private function get_all_member(){
        $this->assign('members',Db::name('store_member')
            ->where('status',1)
            ->field('nickname,id')
            ->select()
        );
    }
}