<?php
namespace app\xueao\controller;
use app\xueao\service\MemberService;
use controller\BasicAdmin;
use service\DataService;
use think\Db;

class MemberInvitationAward extends BasicAdmin
{
    public $table = 'StoreMemberInvitationAward';

    /**
     * @Notes: 邀请奖励
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/28 16:01
     */
    public function index(){
        $this->title = '邀请奖励';
        $get = $this->request->get();
        $db = Db::name($this->table)->where(['is_deleted' => '0']);
        (isset($get['inviter_level']) && $get['inviter_level'] !== '') && $db->where('inviter_level',$get['inviter_level']);
        (isset($get['invitee_level']) && $get['invitee_level'] !== '') && $db->where('invitee_level',$get['invitee_level']);
        return parent::_list($db->order('inviter_level desc,id desc'));
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
     * @DateTime: 2018/11/28 16:14\
     */
    public function _data_filter(&$data){
        $result = MemberService::buildLevelList($data);
        $this->assign([
            'levels' => $result['levels']
        ]);
        foreach ($data as $key => $value) {
            $inviter_level = Db::table('store_member_level')->where('is_deleted','0')->where('id',$value['inviter_level'])->find();
            $invitee_level = Db::table('store_member_level')->where('is_deleted','0')->where('id',$value['invitee_level'])->find();
            if(empty($invitee_level) || empty($inviter_level)){
                unset($data[$key]);
                continue;
            }
            $data[$key]['inviter_level_title'] = $inviter_level['level_title'];
            $data[$key]['invitee_level_title'] = $invitee_level['level_title'];
        }
        $data = array_values($data);
    }
    /**
     * 添加品牌
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\Exception
     */
    public function add()
    {
        $this->title = '添加等级';
        return $this->_form($this->table, 'form');
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
        $this->title = '编辑等级';
        return $this->_form($this->table, 'form');
    }

    /**
     * @Notes:
     * @param $data
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/6 15:58
     */
    protected function _form_filter(&$data)
    {
        if ($this->request->isPost()) {
            $data['is_deleted'] = '0';
            $validate = Validate('MemberInvitationAward');
            $res = $validate->check($data);
            if(true !== $res) {
                $this->error($validate->getError());
            }
        }else{
            $db = Db::table('store_member_level')->where(['is_deleted' => '0']);
            $this->assign('levels',$db->select());
        }
    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
        if ($result !== false) {
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('xueao/member_invitation_award/index')];
            $this->success('数据保存成功！', "{$base}#{$url}?spm={$spm}");
        }
    }
    /**
     * 删除规则
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function del()
    {
        if (DataService::update($this->table)) {
            $this->success("规则删除成功！", '');
        }
        $this->error("规则删除失败，请稍候再试！");
    }
    /**
     * 规则禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("规则禁用成功！", '');
        }
        $this->error("规则禁用失败，请稍候再试！");
    }

    /**
     * 规则启用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        if (DataService::update($this->table)) {
            $this->success("规则启用成功！", '');
        }
        $this->error("规则启用失败，请稍候再试！");
    }
}