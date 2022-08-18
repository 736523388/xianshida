<?php

// +----------------------------------------------------------------------
// | Think.Admin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/Think.Admin
// +----------------------------------------------------------------------

namespace app\xueao\controller;

use app\api\service\CouponService;
use app\xueao\service\MemberService;
use app\api\service\MemberService as ApiMemberService;
use controller\BasicAdmin;
use service\DataService;
use service\ToolsService;
use think\Db;

/**
 * 商店品牌管理
 * Class Brand
 * @package app\store\controller
 * @author Anyon <zoujingli@qq.com>
 * @date 2017/03/27 14:43
 */
class Member extends BasicAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreMember';

    /**
     * 品牌列表
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '会员列表';
        $get = $this->request->get();
        $db = Db::name($this->table);
        if (isset($get['nickname']) && $get['nickname'] !== '') {
            $db->whereLike('nickname', "%{$get['nickname']}%");
        }
        if (isset($get['user_id']) && $get['user_id'] !== '') {
            $db->where('id', $get['user_id']);
        }
        if (isset($get['mobile']) && $get['mobile'] !== '') {
            $db->where('phone', $get['mobile']);
        }
        foreach (['level'] as $field) {
            (isset($get[$field]) && $get[$field] !== '') && $db->where($field, $get[$field]);
        }
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        if (isset($get['parent_id']) && $get['parent_id'] !== '') {
            $db->where('parent_id', $get['parent_id']);
        }
        $this->assign('members',Db::name('store_member')->cache(60*60)->field('id,nickname')->select());
        return parent::_list($db->order('id desc'));
    }

    /**
     * @Notes: 列表数据优化
     * @param $data
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/7 14:55
     */
    public function _data_filter(&$data){
        $result = MemberService::buildLevelList($data);
        $this->assign([
            'levels' => $result['levels']
        ]);
        /*foreach ($data as $key => $value) {
            $data[$key]['teams'] = ApiMemberService::ChildNumber($value['id']);
        }*/
    }

    /**
     * 编辑品牌
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function edit()
    {
        $this->title = '编辑会员';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes: 表单数据操作
     * @param $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 16:06
     */
    protected function _form_filter(&$data)
    {
        if ($this->request->isPost()) {
            if(isset($data['level']) && $data['level'] !== '0'){
                $level = Db::table('store_member_level')->where(['is_deleted'=>'0','status' => '1','id'=>$data['level']])->find();
                empty($level) && $this->error('设置错误');
//                $data['level_duration'] = $level['often_by_default'] * 24 * 60 * 60 + time();
                $data['level_duration'] = 0;//目前这个没用
            }else{
                $data['level_duration'] = 0;
            }
        }else{
            $this->assign('levels',Db::table('store_member_level')->where(['is_deleted' => '0','status' => '1'])->select());
        }
    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
        if ($result !== false) {
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('xueao/member/index')];
            $this->success('数据保存成功！', "{$base}#{$url}?spm={$spm}");
        }
    }

    /**
     * 会员禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("会员禁用成功！", '');
        }
        $this->error("会员禁用失败，请稍候再试！");
    }

    /**
     * 会员启用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("会员启用成功！", '');
        }
        $this->error("会员启用失败，请稍候再试！");
    }

    /**
     * 赠送优惠券
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function send_coupon()
    {
        if($this->request->isPost()){
            $data=$this->request->only(['coupon_name','coupon_quota','use_threshold','uid'],'post');
            try{
                CouponService::SendCoupons($data);
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
            $this->success('操作成功');
        }else{
            $this->assign('uid',$this->request->get('uid'));
            return $this->fetch();
        }
    }

    /**
     * 删除品牌
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function del()
    {
        if (DataService::update($this->table)) {
            $this->success("删除成功！", '');
        }
        $this->error("删除失败，请稍候再试！");
    }

}
