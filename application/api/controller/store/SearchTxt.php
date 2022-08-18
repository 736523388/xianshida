<?php
namespace app\api\controller\store;
use controller\BasicApi;
use think\Db;

class SearchTxt extends BasicApi{
    public $table = 'StoreSearchTxt';

    /**
     * @Notes: 获取搜索词列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/13 9:51
     */
    public function index(){
        $list = (array)Db::name($this->table)->where(['is_deleted'=>'0','status'=>'1'])->order('sort asc,create_at desc')->select();
        $this->success('success',$list);
    }
}