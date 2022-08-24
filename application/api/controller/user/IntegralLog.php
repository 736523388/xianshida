<?php
namespace app\api\controller\user;
use app\api\controller\BasicUserApi;
use app\api\service\IntegralService;
use think\Db;
use think\exception\HttpResponseException;

class IntegralLog extends BasicUserApi
{
    public $tahle = 'StoreMemberIntegralLog';
    public $pagesize = 20;
    /**
     * @Notes: 积分记录列表
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/6 14:37
     */
    public function index(){
        $page = $this->request->param('page',1);
        $list = (array)Db::name($this->tahle)->where(['mid' => UID,'status' => '1','is_deleted' => '0'])->order('create_at desc')->page($page,$this->pagesize)->select();
        $this->success('success',['data' => $list,'pagesize' => $this->pagesize]);
    }

    public function balance()
    {
        $member = Db::table('store_member')->where('id',UID)->field('integral,integral_total')->find();
        $member['integral'] = $member['integral'] + 0;
        $member['integral_total'] = $member['integral_total'] + 0;
        $this->success('success', $member);
    }
}