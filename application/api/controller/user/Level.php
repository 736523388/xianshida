<?php
namespace app\api\controller\user;

use app\api\controller\BasicUserApi;
use app\api\service\MemberService;
use app\api\service\WeChatPayService;
use service\DataService;
use think\Db;
use think\exception\HttpResponseException;

class Level extends BasicUserApi
{
    public $table = 'StoreMemberLevel';

    /**
     * @Notes: 可用等级
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/21 19:06
     */
    public function available(){
        MemberService::updateLevel(UID);  //更新当前用户等级
        $levels = MemberService::AvailableLevels(USER_LEVEL);
        $this->success('success',$levels);
    }

    /**
     * @Notes: 提交购买订单
     * @return \think\Response|\think\response\Json
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/5 14:57
     */
    public function create_order(){
        $where = ['status'=>'1','is_deleted'=>'0','apply_status'=>'0','mid'=>UID];
        if(Db::table('store_member_level_apply')->where($where)->find() || Db::table('store_member_level_buy')->where($where)->where('pay_status','1')->find()){
            $this->error('请等待审核结束');
        }
        $level_id = $this->request->param('level_id');
        $db = Db::name($this->table)->where('status','1')->where('is_deleted','0')->where('open_purchase','1')->where('id',$level_id);
        if(USER_LEVEL){
            $user_level = Db::name($this->table)->where('is_deleted','0')->where('id',USER_LEVEL)->find();
            if(!empty($user_level)){
                $db->where('sort','>=',$user_level['sort']);
            }
        }
        $use_integral = $this->request->param('use_integral','');
        if($use_integral){
            $db->where('open_purchase_integral','1');
        }
        $level = $db->find();
        empty($level) && $this->error('操作错误，请核实后再试！');
        $user_info = Db::table('store_member')->where('id',UID)->find();
        $integral_price = 0;
        if($use_integral){
            $integral_price = ceil($level['purchase_price']/sysconf('integral_exchange_ratio'));
            ($integral_price > $user_info['integral']) && $this->error('您的积分不足');
        }
        try{
            $order_sn = DataService::createSequence(10, 'ORDER');
            $data = [
                'mid' => UID,
                'level' => $level_id,
                'order_sn' => $order_sn,
                'use_integral' => $use_integral ? '1' : '0',
                'use_integral_num' => $integral_price,
                'real_price' => $use_integral ? '0' : $level['purchase_price']
            ];
            Db::transaction(function () use ($data, $use_integral,$integral_price) {
                if($use_integral){
                    $data['pay_status'] = '1';
                    Db::table('store_member')->where('id',UID)->setDec('integral',$integral_price);
                }
                Db::table('store_member_level_buy')->insert($data);
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('操作失败，请稍候再试！' . $e->getMessage());
        }
        $options = [
            'body' => '可优猫-购买会员',
            'out_trade_no' => $order_sn,
            'total_fee' => $data['real_price'] * 100,
            'openid' => Db::table('store_member')->where('id',UID)->value('openid')
        ];
        $params = [];
        if(!$use_integral){
            $result = WeChatPayService::createLevelOrder($options);
            if(empty($result['code'])){
                return json($result);
            }
            Db::table('store_member_level_buy')->where('order_sn',$order_sn)->setField(['prepay_id' => $result['result']['prepay_id']]);
            $params = $result['data'];
        }
        $this->success('操作成功',['order_sn'=>$order_sn,'pay_status'=> $use_integral ? 1 : 0,'params'=>$params]);
    }

    /**
     * @Notes: 提交申请
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/27 14:09
     */
    public function create_apply(){
        $where = ['status'=>'1','is_deleted'=>'0','apply_status'=>'0','mid'=>UID];
        if(Db::table('store_member_level_apply')->where($where)->find() || Db::table('store_member_level_buy')->where($where)->where('pay_status','1')->find()){
            $this->error('请等待审核结束');
        }
        $level_id = $this->request->param('level_id');
        $db = Db::name($this->table)->where('status','1')->where('is_deleted','0')->where('open_apply','1')->where('id',$level_id);
        if(USER_LEVEL){
            $user_level = Db::name($this->table)->where('is_deleted','0')->where('id',USER_LEVEL)->find();
            if(!empty($user_level)){
                $db->where('sort','>=',$user_level['sort']);
            }
        }
        $level = $db->find();
        empty($level) && $this->error('网络错误，请稍后再试！');
        try{
            $data = [
                'mid' => UID,
                'level' => $level_id,
                'name' => $this->request->param('name'),
                'phone' => $this->request->param('phone'),
                'address' => $this->request->param('address'),
                'condition' => $this->request->param('condition')
            ];
            $validate = Validate('LevelApply');
            $res = $validate->check($data);
            if(true !== $res) {
                $this->error($validate->getError());
            }
            Db::table('store_member_level_apply')->insert($data);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('提交申请失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('提交申请成功');
    }
}