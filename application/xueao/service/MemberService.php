<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

namespace app\xueao\service;

use service\DataService;
use think\Db;
use think\Exception;

/**
 * 会员数据初始化
 * Class MemberService
 * @package app\store\service
 */
class MemberService
{
    /**
     * @Notes: 会员列表数据处理
     * @param $memberlist
     * @return array
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/28 15:37
     */
    public static function buildLevelList(&$memberlist){
        // 会员级别处理
        $levelField = 'id,level_title,level_desc';
        $levelWhere = ['status' => '1', 'is_deleted' => '0'];
        $levelList = Db::name('StoreMemberLevel')->where($levelWhere)->order('sort asc,id desc')->column($levelField);
//        $levelList=[];
        if (empty($memberlist)) {
            return ['list' => $memberlist, 'levels' => $levelList];
        }
        foreach ($memberlist as $key => $vo) {
            $memberlist[$key]['level_title'] = isset($vo['level']) ? isset($levelList[$vo['level']]) ? $levelList[$vo['level']]['level_title'] : sysconf('registered_name') : '';
            if($vo['level']>0){
                //获取营业执照
                $memberlist[$key]['wholesaler']=Db::name('wholesaler')->where('mid',$vo['id'])->find();
            }
            //$memberlist[$key]['parent_member_name'] = Db::table('store_member')->where('id',$vo['parent_id'])->value('nickname') ?: '无';
            $memberlist[$key]['parent_member_name'] = isset($vo['parent_id']) ? Db::table('store_member')->where('id',$vo['parent_id'])->value('nickname') ?: '无' : '';
        }
        return ['list' => $memberlist, 'levels' => $levelList];
    }

    /**
     * @Notes: 设置会员等级
     * @param int $mid 会员ID
     * @param int $level 等级ID
     * @return array
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/19 21:13
     */
    public static function setLevel($mid = 0,$level = 0){
        $user = Db::table('store_member')->where(['id' => $mid])->field('level,level_duration')->findOrFail();
        if($level == 0){
            Db::table('store_member')->where('id',$mid)->update(['level' => 0,'level_duration' => 0]);
            return ['code' => 1,'msg' => '设置成功！'];
        }
        $level_info = Db::table('store_member_level')->where(['id' => $level,'is_deleted' => '0'])->field('id,often_by_default,upgrade_self_clean,upgrade_team_clean')->findOrFail();
        $time = time();
        try{
            $data = [
                'level' => $level,
                'level_duration' => $time + $level_info['often_by_default'] * 24 * 60 * 60
            ];
            if(($user['level'] == $level) && ($user['level_duration'] > $time)){
                    $data['level_duration'] = $user['level_duration'] + $level_info['often_by_default']*24*60*60;
            }
            if($level_info['upgrade_self_clean'] == '1'){
                $data['self_expenditure'] = 0;
            }
            if($level_info['upgrade_team_clean'] == '1'){
                $data['team_expenditure'] = 0;
            }
            Db::table('store_member')->where('id',$mid)->update($data);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
        return ['code' => 1,'msg' => '设置成功！'];
    }
}