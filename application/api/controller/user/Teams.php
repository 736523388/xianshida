<?php
namespace app\api\controller\user;
use app\api\controller\BasicUserApi;
use app\api\service\MemberService;

class Teams extends BasicUserApi
{

    /**
     * @Notes: 团队数据
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/14 16:37
     */
    public function index(){
        $memberTeams = MemberService::memberTeams(UID);
        $this->success('success',$memberTeams);

    }
}