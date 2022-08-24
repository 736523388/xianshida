<?php
namespace app\api\controller\user;
use app\api\controller\BasicUserApi;
use app\api\service\MemberService;
use service\WechatService;
use think\Db;
use think\Validate;

class Member extends BasicUserApi
{
    /**
     * @Notes: 会员基本信息
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/4 17:09
     */
    public function index(){
        $member = Db::table('store_member')->where('id',UID)->find();
        $data = [];
        $data['id'] = $member['id'];
        $data['nickname'] = $member['nickname'];
        $data['headimg'] = $member['headimg'];
        $data['is_insider'] = IS_INSIDER;
        $data['user_level'] = USER_LEVEL;
        $data['level_title'] = $member['level'] ? Db::table('store_member_level')->where('id',$member['level'])->value('level_title'): sysconf('registered_name');
        $data['tips'] = $member['level'] ? Db::table('store_member_level')->where('id',$member['level'])->value('level_tips'): sysconf('member_tips');

        //是否展示团队消费
        $show_team_expenditure = false;
        if($member['level'] > 0){
            if(Db::table('store_member_level')->where('id',$member['level'])->value('show_team_expenditure')){
                $show_team_expenditure = true;
            }
        }
        //名片展示内容
        $bcard_data = [
            [
            'show' => ($member['level'] > 0) ? true : false,
            'title' => '剩余天数',
            'value' => $member['level'] ? ($this->maktimes2day($member['level_duration']) > 0) ? $this->maktimes2day($member['level_duration']): '' : '',
            'nuit' => $member['level'] ? $this->maktimes2day($member['level_duration']) ? '天' : '已过期' : ''
            ],
            [
                'show' => true,
                'title' => '可使用积分',
                'value' => $member['integral']+0,
                'nuit' => ''
            ],
            [
                'show' => true,
                'title' => '个人消费',
                'value' => $member['self_expenditure'],
                'nuit' => '元'
            ],
            [
                'show' => $show_team_expenditure,
                'title' => '团队消费',
                'value' => $member['team_expenditure'],
                'nuit' => '元'
            ]
        ];
        $subordinate = MemberService::ChildNumber(UID);
        foreach ($subordinate as $item) {
            $bcard_data = array_merge($bcard_data,[[
                'show' => true,
                'title' => '邀请'.$item['level_title'],
                'value' => $item['number'],
                'unit' => '人'
            ]]);
        }
        $data['bcard_data'] = $bcard_data;
        if(IS_INSIDER){
            $data['statistics'] = [
                [
                    'title' => '今日积分',
                    'value' => self::todayIntegral(UID),
                    'unit' => ''
                ],
                [
                    'title' => '累计积分',
                    'value' => $member['integral_total'],
                    'unit' => ''
                ],
                [
                    'title' => '为你节省',
                    'value' => $member['save_amount'],
                    'unit' => '元'
                ]
            ];
        }
        //$data['AvailableLevels'] = MemberService::AvailableLevels(USER_LEVEL);

        //$data['ColorPlan'] = MemberService::getColor(USER_LEVEL);
        $this->success('success',$data);
    }
    public function maktimes2day($time){
        $t = $time - time();
        $day = $t/(24*60*60);
        return ceil($day);
    }
    public static function todayIntegral($mid){
        $db = Db::table('store_member_integral_log')->where('integral','>','0')->where('mid',UID);
        $today = date('Y-m-d');
        $db->whereBetween('create_at', ["{$today} 00:00:00", "{$today} 23:59:59"]);
        return $db->sum('integral');
    }

    /**
     * 获取推广二维码
     * @author jungshen
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     */
    public function qr_code(){
        $dir='./static/upload/qr_code/';
        $file=UID.'.jpg';
        if(!is_dir($dir)){
            mkdir($dir,0755,true);
        }
        if(!file_exists($dir.$file)){
            $sence=UID;
            $page='pages/my/my/my';
            $res=WechatService::WeMiniQrcode()->createMiniScene($sence,$page);
            file_put_contents($dir.$file,$res);
        }
        $this->success('success',['mid'=>UID,'url'=>$_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].trim($dir,'.').$file]);
    }

    /**
     * 绑定手机号
     * @author jungshen
     */
    public function bind_mobile(){
        $post=$this->request->only(['mobile','code'],'post');
        $validate=new \app\api\validate\Member();
        if(false===$validate->scene('bindmobile')->check($post)){
            $this->error($validate->getError());
        }
        Db::name('store_member')->where('id',UID)->setField('phone',$post['mobile']);
        $this->success('绑定成功');
    }

    /**
     * 查询我的足迹
     * @author jungshen
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function footprint(){
        $page_now=$this->request->get('page_now',1);
        $page_size=$this->request->get('page_size',10);
        $list=Db::name('store_footprint')
            ->where('mid',UID)
            ->page($page_now,$page_size)
            ->field('id,goods_id,goods_title,goods_image,create_at')
            ->select();
        $this->success('success',$list);
    }

    /**
     * 获取个人信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function member_info(){
        $item=Db::name('store_member')
            ->where('id',UID)
            ->field('phone,nickname,headimg,sex')
            ->find();
        $this->success('success',$item);
    }

}