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

class Ad extends XueaoAdmin
{
    public $table='SystemAd';
    /**
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '广告管理';
        $get = $this->request->get();
        $db = Db::name($this->table);
        if (isset($get['name']) && $get['name'] !== '') {
            $db->whereLike('name', "%{$get['name']}%");
        }
        foreach (['type', 'status'] as $field) {
            (isset($get[$field]) && $get[$field] !== '') && $db->where($field, $get[$field]);
        }
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        $this->_form_assign();
        return parent::_list($db->order('sort,id desc'));
    }

    public function _data_filter(&$list)
    {
        foreach ($list as $k=>&$v){
            $v['type']=Db::name('system_cate')->where('id',$v['type'])->value('title');
        }
    }


    private function _form_assign(){
        //查询广告分类
        $cateWhere = ['status' => '1', 'pid' => '68'];
        $this->assign('cates',
            Db::name('system_cate')
            ->where($cateWhere)
            ->select()
        );
    }

    /**
     * @param $data
     */
    public function _form_filter(&$data,$extend_data)
    {
        if($this->request->isGet()){
            $goods_list = Db::name('StoreGoods')
                ->where('status',1)
                ->where('is_deleted',0)
                ->field('id,goods_title title')->select();
            $this->assign('goods_list',$goods_list);
            if($this->request->get('id')){
                $this->title='编辑广告';
            }else{
                $this->title='添加广告';
            }
            $this->_form_assign();
        }else{
            if(!$this->request->post('id')){
                $data['status']=1;
                $data['create_at']=date('Y-m-d H:i:s');
            }
            $data['target_type']=0;
            if($data['target_type']==0){
                $data['url']=$data['goods_id'];
            }
            if(empty($this->request->post('image'))){
                $this->error('广告图片不能为空');
            }
        }
    }

}