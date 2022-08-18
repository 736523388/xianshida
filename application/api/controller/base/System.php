<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/12/12
 * Time: 15:24
 */

namespace app\api\controller\base;
use app\api\validate\Feedback;
use controller\BasicApi;
use service\AlismsService;
use think\Db;
use think\facade\Cache;
use think\Validate;

/**
 * 系统相关
 * @author jungshen
 * Class System
 * @package app\api\controller\store
 */
class System extends BasicApi
{
    /**
     * 帮助中心问题列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function help_list(){
        $ret=Db::name('system_cate')
            ->where('pid',33)
            ->where('status',1)
            ->field('id,title')
            ->order('sort')
            ->select();
        $this->success('success',$ret);
    }

    /**
     * 帮助中心详情
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function help_view(){
        $id=$this->request->get('id');
        $ret=Db::name('system_cate')
            ->where('pid',$id)
            ->where('status',1)
            ->order('sort')
            ->field('title,content')
            ->select();
        $this->success('success',$ret);
    }

    /**
     * 获取关于我们的内容
     */
    function about(){
        $ret=Db::name('system_cate')
            ->where('id',47)
            ->value('content');
        $this->success('success',$ret);
    }

    /**
     * 私人订制内容
     */
    function tailor(){
        $ret=Db::name('system_cate')
            ->where('id',48)
            ->value('content');
        $this->success('success',$ret);
    }

    /**
     * 协议内容（系统文章详情）
     */
    function agreement(){
        $id=$this->request->get('id/d');
        $ret=Db::name('system_cate')
            ->where('id',$id)
            ->value('content');
        $this->success('success',$ret);
    }

    /**
     * 意见反馈
     */
    function feedback(){
        $post=$this->request->only(['content','phone'],'post');
        $validate=new Feedback();
        if(false===$validate->check($post)){
            $this->error($validate->getError());
        }
        $post['create_at']=time();
        Db::name('feedback')->insert($post);
        $this->success('提交成功');
    }

    /**
     * 获取物流公司
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function logistics_company(){
        $ret=Db::name('store_express')
            ->where('status',1)
            ->where('is_deleted',0)
            ->order('sort')
            ->field('express_title,express_code')
            ->select();
        $this->success('success',$ret);
    }

    /**
     * 发送短信验证码
     */
    function send_msg_code(){
        $mobile=$this->request->get('mobile');
        $validate=Validate::make([
            'mobile|手机号'=>'require|mobile'
        ]);
        if(false===$validate->check(['mobile'=>$mobile])){
            $this->error($validate->getError());
        }
        if(Cache::get($mobile.'_bind_mobile_resend')){
            $this->error('发送时间过短');
        }
        $code=createRandomStr(config('aliyun.code_length'),1);
        $res=AlismsService::send($mobile,'SMS_173470925','菜芽到家',
            ['code'=>$code]);
        if(isset($res['Code'])&&$res['Code']=='OK'){
            //保存验证码
            Cache::set($mobile.'_bind_mobile_resend',true,config('aliyun.resend_time'));
            Cache::set($mobile.'_bind_mobile',$code,config('aliyun.valid_time'));
            $this->success('发送成功',[
                'resend_time'=>config('aliyun.resend_time'),
                'valid_time'=>config('aliyun.valid_time')
            ]);
        }else{
            $this->error('发送失败,请联系管理员');
        }
    }

    /**
     * 获取后台配置
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    function sysconf(){
        $name=$this->request->get('name');
        $sysconf = sysconf($name);
        if($name == 'group_bg_img' || $name == 'bargain_bg_img'){
            $sysconf = sysconf('applet_url') . $sysconf;
        }
        $this->success('success',$sysconf);
    }

    /**
     * 门店列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function store_list(){
        $list=Db::name('store')
            ->where('status',1)
            ->hidden('id,create_at,status,sort')
            ->order('sort')
            ->select();
        $this->success('success',$list);
    }

}