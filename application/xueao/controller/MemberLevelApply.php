<?php
namespace app\xueao\controller;
use app\xueao\service\MemberService;
use controller\BasicAdmin;
use service\DataService;
use think\Db;
use think\Exception;
use think\exception\HttpResponseException;

class MemberLevelApply extends BasicAdmin
{
    public $table = 'StoreMemberLevelApply';

    /**
     * @Notes: 会员申请列表
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/27 16:44
     */
    public function index(){
        $this->title = '会员申请列表';
        $get = $this->request->get();
        $db = Db::name($this->table)->alias('a')
            ->join('store_member b','a.mid = b.id')
            ->where(['a.is_deleted' => '0','a.status' => '1','a.apply_status' => '0'])
            ->where(['b.status' => '1']);
        (isset($get['level']) && $get['level'] !== '') && $db->where('a.level',$get['level']);
        (isset($get['nickname']) && $get['nickname'] !== '') && $db->where('b.nickname',$get['nickname']);
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('a.create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->field('a.*,b.nickname,b.headimg')->order('a.id desc'));
    }

    /**
     * @Notes: 列表数据优化
     * @param $data
     * @throws Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/5 10:00
     */
    public function _data_filter(&$data){
        $result = MemberService::buildLevelList($data);
        $this->assign([
            'levels' => $result['levels']
        ]);
    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
        if ($result !== false) {
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('xueao/member_level_apply/index')];
            $this->success('数据保存成功！', "{$base}#{$url}?spm={$spm}");
        }
    }
    /**
     * 申请拒绝
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        if (DataService::update($this->table)) {
            $this->success("拒绝申请成功！", '');
        }
        $this->error("拒绝申请失败，请稍候再试！");
    }

    /**
     * 申请通过
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        list($id,$field, $value) = [$this->request->post('id', ''), $this->request->post('field', ''), $this->request->post('value', '')];
        /*申请信息*/
        $apply = Db::name($this->table)->where(['is_deleted' => '0','status' => '1','apply_status' => '0','id' => $id])->find();
        empty($apply) && $this->error('申请信息错误！');

        /*申请等级信息*/
        $level_info = Db::table('store_member_level')->where(['is_deleted' => '0','id' => $apply['level']])->find();
        empty($level_info) && $this->error('申请等级错误！');

        $user_level = Db::table('store_member')->where('id',$apply['mid'])->value('level');
        $user_level_sort = Db::table('store_member_level')->where('id',$user_level)->value('sort');
        $buy_level_sort = Db::table('store_member_level')->where('id',$apply['level'])->value('sort');
        if($user_level_sort > $buy_level_sort){
            $this->error('设置等级错误！');
        }
        try{
            Db::transaction(function() use($apply,$id,$field,$value){
                MemberService::setLevel($apply['mid'],$apply['level']);
                Db::table('store_member_level_apply')->where('id',$id)->setField($field,$value);
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('申请通过失败，请稍候再试！' . $e->getMessage());
        }
        $this->success("申请通过成功！", '');
    }
}