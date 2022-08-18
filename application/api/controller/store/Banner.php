<?php

namespace app\api\controller\store;
use controller\BasicApi;
use think\Db;

class Banner extends BasicApi
{

    public $table = 'system_ad';

    /**
     * 获取轮播图
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index(){
        $identity=$this->request->get('identity',1);
        $list = (array)Db::name($this->table)
            ->where('status',1)
            ->where('identity','in',[0,$identity])
            ->order('sort asc')->select();
        foreach ($list as &$value) {
            $value['image'] = sysconf('applet_url').$value['image'];
        }
        $this->success('success',$list);
    }

    /**
     * 广告详情
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function detail(){
        $id = $this->request->param('id');
        $detail = Db::name($this->table)->where('id',$id)->find();
        $detail['image'] = sysconf('applet_url').$detail['image'];
        $detail['content'] = str_replace('/static/upload/', sysconf('applet_url').'/static/upload/', $detail['content']);
        $this->success('success',$detail);
    }
}