<?php
namespace app\api\controller\store;

use controller\BasicApi;
use service\ToolsService;
use think\Db;

class GoodsCate extends BasicApi
{
    public $table = 'StoreGoodsCate';

    /**
     * @Notes: 商品分类列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/14 17:13
     */
    public function index(){
        $pid = $this->request->param('pid','0');
        $field = 'id,image,cate_title';
        $order = 'sort asc,id desc';
        $db = Db::name($this->table)->where(['is_deleted'=>'0','status' => '1'])->where('pid',$pid);
        $list = (array)$db->field($field)->order($order)->select();
        $this->success('success',$list);
    }

    /**
     * @Notes: 获取商品分类树
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/14 17:35
     */
    public function lists(){
        $field = 'id,pid,image,image_cover,cate_title,cate_desc,status';
        $order = 'sort asc,id desc';
        $list = (array)Db::name($this->table)->where(['is_deleted'=>'0','status' => '1'])->field($field)->order($order)->select();
        foreach ($list as &$value) {
            $value['image'] = sysconf('applet_url').$value['image'];
            $value['image_cover'] = sysconf('applet_url').$value['image_cover'];
        }
        $list = ToolsService::arr2tree($list);
        $this->success('success',$list);
    }

    /**
     * 获取推荐分类
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function special(){
        $field = 'id,image,cate_title';
        $order = 'sort asc,id desc';
        $db = Db::name($this->table)->where(['is_deleted'=>'0','is_homepage'=>'1','pid'=>'0']);
        $list = (array)$db->field($field)->order($order)->select();
        $this->success('success',$list);
    }
}