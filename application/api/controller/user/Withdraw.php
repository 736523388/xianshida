<?php
namespace app\api\controller\user;
use app\api\controller\base\System;
use app\api\controller\BasicUserApi;
use app\api\service\IntegralService;
use service\AlismsService;
use think\Db;
use think\exception\HttpResponseException;

class Withdraw extends BasicUserApi
{
    public $tahle = 'StoreMemberWithdraw';

    /**
     * @Notes: 积分提现配置
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/6 14:37
     */
    public function index(){
        $data = [
            'open_integral_exchange' => (boolean)sysconf('open_integral_exchange'),
            'integral_start_num' => sysconf('integral_start_num'),
            'integral_exchange_ratio' => sysconf('integral_exchange_ratio'),
            'user_integral' => Db::table('store_member')->where('id',UID)->value('integral')
        ];
        $data['all_amount'] = sprintf("%.2f",$data['user_integral'] * $data['integral_exchange_ratio']);
        $this->success('success',$data);
    }
    /**
     * @Notes: 发起积分提现
     * @return \think\Response
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/26 20:13
     */
    public function create(){
        if(!sysconf('open_integral_exchange')){
            $this->error('积分提现已关闭！');
        }
        //用户今日提现次数
        $integral_take_day_num = sysconf('integral_take_day_num');
        $start_day = date('Y-m-d 00:00:00');
        $end_day = date('Y-m-d 23:59:59');
        $user_integral_take_day_num = Db::table('store_member_withdraw')->where('mid',UID)->whereBetween('create_at', ["{$start_day} 00:00:00", "{$end_day} 23:59:59"])->where('status','1')->count();
        if($user_integral_take_day_num >= $integral_take_day_num){
            $this->error('今日提现已达到限额，请隔天重试！');
        }
        //用户今日提现次数
        $integral_take_day_num = sysconf('integral_take_day_num');
        $start_day = date('Y-m-d 00:00:00');
        $end_day = date('Y-m-d 23:59:59');
        $user_integral_take_day_num = Db::table('store_member_withdraw')->where('mid',UID)->whereBetween('create_at', ["{$start_day} 00:00:00", "{$end_day} 23:59:59"])->where('status','1')->count();
        if($user_integral_take_day_num >= $integral_take_day_num){
            $this->error('今日提现已达到限额，请隔天重试！');
        }
        //用户本月提现次数
        $integral_take_month_num = sysconf('integral_take_month_num');
        $start_month = date('Y-m-01 00:00:00');
        $end_month = date('Y-m-t 23:59:59');
        $user_integral_take_month_num = Db::table('store_member_withdraw')->where('mid',UID)->whereBetween('create_at', ["{$start_month} 00:00:00", "{$end_month} 23:59:59"])->where('status','1')->count();
        if($user_integral_take_month_num >= $integral_take_month_num){
            $this->error('本月提现已达到限额，请隔月重试！');
        }
        $integral = $this->request->param('integral');
        if(!is_numeric($integral) || $integral < sysconf('integral_start_num')){
            $this->error('请输入正确的积分！');
        }
        $type = trim($this->request->param('type'));
        if($type != 'alipay' && $type != 'bankcard' && $type != 'wechat'){
            $this->error('网络错误，请稍后再试！');
        }
        $data = [
            'mid' => UID,
            'integral' => $integral,
            'type' => $type
        ];
        $phone = '';
        if($type == 'alipay'){
            $alipay_name = $this->request->param('alipay_name','');
            $alipay_code = $this->request->param('alipay_code','');
            empty($alipay_name) && $this->error('请输入支付宝姓名');
            empty($alipay_code) && $this->error('请输入支付宝账户');
            $data['alipay_name'] = $alipay_name;
            $data['alipay_code'] = $alipay_code;
            $phone = $alipay_code;
        }elseif ($type == 'bankcard'){
            $bank_name = $this->request->param('bank_name','');
            $bank_code = $this->request->param('bank_code','');
            $bank_user_name = $this->request->param('bank_user_name','');
            $bank_phone = $this->request->param('bank_phone','');
            $bank_address = $this->request->param('bank_address','');
            $bank_dot = $this->request->param('bank_dot','');
            empty($bank_name) && $this->error('请输入银行卡开户行');
            empty($bank_code) && $this->error('请输入银行卡账户');
            empty($bank_user_name) && $this->error('请输入持卡人姓名');
            empty($bank_phone) && $this->error('请输入预留手机号');
            empty($bank_address) && $this->error('请输入银行卡开户地');
            empty($bank_dot) && $this->error('请输入开户网点');
            if(!preg_match("/^1[34578]\d{9}$/", $bank_phone)){
                $this->error('请输入正确的预留手机号');
            }
            $data['bank_name'] = $bank_name;
            $data['bank_code'] = $bank_code;
            $data['bank_user_name'] = $bank_user_name;
            $data['bank_phone'] = $bank_phone;
            $data['bank_address'] = $bank_address;
            $data['bank_dot'] = $bank_dot;
            $phone = $bank_phone;
        }
        /*当前积分兑换比例*/
        $integral_exchange_ratio = sysconf('integral_exchange_ratio');
        $amount = $integral * $integral_exchange_ratio;
        $data['amount'] = $amount;
        try{
            Db::transaction(function () use ($data, $integral) {
                Db::name($this->tahle)->insert($data);
                IntegralService::RecordLog(UID,$integral,'积分提现');
                Db::table('store_member')->where('id',UID)->setDec('integral',$integral);
            });
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('提现失败，请稍候再试！' . $e->getMessage());
        }
        AlismsService::send($phone,'SMS_156900684','小红猪海淘',[
            'name' => '提现'
        ]);
        $this->success('提现成功');
    }
    public static function send_msg($PhoneNumbers, $TemplateCode, $SignName, array $TemplateParam){
        return AlismsService::send($PhoneNumbers,$TemplateCode,$SignName,$TemplateParam);
    }
}