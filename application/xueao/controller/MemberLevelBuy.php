<?php
namespace app\xueao\controller;
use app\api\service\WeChatPayService;
use app\xueao\service\MemberService;
use controller\BasicAdmin;
use service\DataService;
use think\Db;
use think\Exception;
use think\exception\HttpResponseException;
use think\facade\Env;

class MemberLevelBuy extends BasicAdmin
{
    public $table = 'StoreMemberLevelBuy';

    /**
     * @Notes: 会员购买列表
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
        $this->title = '会员购买列表';
        $get = $this->request->get();
        $db = Db::name($this->table)->alias('a')
            ->join('store_member b','a.mid = b.id')
            ->where(['a.is_deleted' => '0','a.pay_status' => '1','a.status' => '1'])
            ->where(['b.status' => '1']);
        (isset($get['level']) && $get['level'] !== '') && $db->where('a.level',$get['level']);
        (isset($get['order_sn']) && $get['order_sn'] !== '') && $db->where('a.order_sn',$get['order_sn']);
        (isset($get['nickname']) && $get['nickname'] !== '') && $db->where('b.nickname',$get['nickname']);
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('a.create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->field('a.*,b.nickname,b.headimg')->order('a.apply_status asc,a.id desc'));
    }

    /**
     * @Notes:
     * @param $data 列表数据优化
     * @throws Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/5 11:38
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
            list($base, $spm, $url) = [url('@admin'), $this->request->get('spm'), url('xueao/member_level_buy/index')];
            $this->success('数据保存成功！', "{$base}#{$url}?spm={$spm}");
        }
    }

    /**
     * 拒绝购买
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function forbid()
    {
//        sysconf('wechat_cert_cert',Env::get('root_path').'static/cert/apiclient_cert.pem');
//        sysconf('wechat_cert_key',Env::get('root_path').'static/cert/apiclient_key.pem');
        list($id,$field, $value) = [$this->request->post('id', ''), $this->request->post('field', ''), $this->request->post('value', '')];
        try{
            $buy = Db::name($this->table)->where(['is_deleted' => '0','status' => '1','apply_status' => '0','pay_status' => '1','id' => $id])->find();
            Db::transaction(function () use ($buy, $id, $field, $value) {
                Db::name($this->table)->where('id',$id)->setField($field,$value);
                if($buy['use_integral'] == '1'){
                    /*积分返还*/
                    $res = Db::table('store_member')->where('id',$buy['mid'])->setInc('integral',$buy['use_integral_num']);
                    if(!$res){
                        throw new Exception('积分返还失败！');
                    }
                }else{
                    /*微信退款*/
                    $options = [
                        'out_trade_no' => $buy['order_sn'],
                        'total_fee' => $buy['real_price'] * 100,
                        'refund_fee' => $buy['real_price'] * 100,
                        'out_refund_no' => DataService::createSequence(10, 'ORDER')
                    ];
                    $result = WeChatPayService::WePayRefund($options);
                    if($result['return_code'] !== 'SUCCESS' || $result['result_code'] !== 'SUCCESS'){
                        throw new Exception('微信退款失败！'.isset($result['err_code_des']) ? $result['err_code_des'] : '发起退款请求失败！');
                    }
                }
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('操作失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('操作成功','');
    }

    /**
     * 品牌签禁用
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function resume()
    {
        $id = $this->request->post('id', '');
        try{
            /*申请信息*/
            $buy = Db::name($this->table)->where(['is_deleted' => '0','status' => '1','pay_status' => '1','apply_status' => '0','id' => $id])->findOrFail();

            $result = \app\api\service\MemberService::level_log_and_back($buy['order_sn']);
            if(!empty($result)){
                if(isset($result['code']) && $result['code'] == 0){
                    $this->error('申请通过失败，请稍候再试！' .(isset($result['msg']) ? $result['msg'] : ''));
                }
            }
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('申请通过失败，请稍候再试！' . $e->getMessage());
        }
        $this->success("申请通过成功！", '');
    }
}