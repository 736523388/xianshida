<?php
namespace app\xueao\controller;
use app\xueao\service\MemberService;
use controller\BasicAdmin;
use service\AlismsService;
use service\DataService;
use think\Db;
use think\Exception;
use think\exception\HttpResponseException;

class MemberWithdraw extends BasicAdmin
{
    public $table = 'StoreMemberWithdraw';

    /**
     * @Notes: 会员提现申请列表
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
        $this->title = '会员提现申请';
        $get = $this->request->get();
        $db = Db::name($this->table)->alias('a')
            ->join('store_member b','a.mid = b.id')
            ->where(['a.is_deleted' => '0'])
            ->where(['b.status' => '1']);
        (isset($get['nickname']) && $get['nickname'] !== '') && $db->where('b.nickname',$get['nickname']);
        (isset($get['type']) && $get['type'] !== '') && $db->where('a.type',$get['type']);
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('a.create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->field('a.*,b.nickname,b.headimg,b.phone,b.level')->order('a.id desc'));
    }

    /**
     * @Notes: 列表数据优化
     * @param $data
     * @throws Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/23 10:11
     */
    public function _data_filter(&$data){
        MemberService::buildLevelList($data);
    }

    /**
     * 添加成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
        if ($result !== false) {
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('xueao/member_withdraw/index')];
            $this->success('数据保存成功！', "{$base}#{$url}?spm={$spm}");
        }
    }
    /**
     * 拒绝申请
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
        list($id,$field, $value) = [$this->request->post('id', ''), $this->request->post('field', ''), $this->request->post('value', '')];
        try{
            $info = Db::name($this->table)->where(['is_deleted' => '0','status' => '1','made_status' => '0','id' => $id])->find();
            Db::transaction(function () use ($info, $id, $field, $value) {
                Db::name($this->table)->where('id',$id)->setField($field,$value);
                Db::table('store_member')->where('id',$info['mid'])->setInc('integral',$info['integral']);
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('操作失败，请稍候再试！' . $e->getMessage());
        }
        $phone = '';
        if($info['type'] == 'bankcard'){
            $phone = $info['bank_phone'];
        }elseif ($info['type'] == 'alipay'){
            $phone = $info['alipay_code'];
        }
        if(!empty($phone)){
            AlismsService::send($phone,'SMS_156895752','小红猪海淘',[
                'mtname' => '提现',
                'submittime' => date('Y/m/d H:i:s')
            ]);
        }
        $this->success('操作成功','');
    }

    /**
     * 打款确认
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        list($id,$field, $value) = [$this->request->post('id', ''), $this->request->post('field', ''), $this->request->post('value', '')];
        $info = Db::name($this->table)->where(['is_deleted' => '0','status' => '1','made_status' => '0','id' => $id])->find();
        if (DataService::update($this->table)) {
            $phone = '';
            if($info['type'] == 'bankcard'){
                $phone = $info['bank_phone'];
            }elseif ($info['type'] == 'alipay'){
                $phone = $info['alipay_code'];
            }
            if(!empty($phone)){
                AlismsService::send($phone,'SMS_156277859','小红猪海淘',[
                    'mtname' => '提现',
                    'submittime' => date('Y/m/d H:i:s')
                ]);
            }
            $this->success("操作成功！", '');
        }
        $this->error("操作失败，请稍候再试！");
    }
}