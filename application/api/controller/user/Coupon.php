<?php
namespace app\api\controller\user;
use app\api\controller\BasicUserApi;
use app\api\service\CouponService;
use think\Db;
use think\exception\HttpResponseException;

class Coupon extends BasicUserApi
{
    public $table = 'StoreCoupon';

    /**
     * @Notes: 可领取优惠券列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/15 10:41
     */
    public function index(){
        $goods_id = $this->request->param('goods_id',0);
        $coupon_list = CouponService::getAvailableCoupon(UID,$goods_id);
        foreach ($coupon_list as &$item) {
            $item['coupon_quota'] = floatval($item['coupon_quota']);
            $item['coupon_discount'] = floatval($item['coupon_discount']);
            $item['use_threshold'] = floatval($item['use_threshold']);
        }
        $this->success('success',$coupon_list);
    }

    /**
     * @Notes: 我的优惠券
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/15 10:47
     */
    public function mycoupon(){
        $status = $this->request->param('status',0);
        $db = Db::table('store_coupon_user')->where('mid',UID);
        if($status != 0){
            if($status == 1){
                $db->where('status','1')
                    ->where('coupon_end_time','>',time());
            }elseif ($status == 2){
                $db->where('status','0');
            }elseif ($status == 3){
                $db->where('status','1')
                    ->where('coupon_end_time','<',time());
            }
        }
        $db->where(function ($query){
            $query->where('level_limits','=','0');
            if(!empty(USER_LEVEL)){
                $map1 = [
                    ['level_limits', '=', '1'],
                    ['', 'EXP', Db::raw("FIND_IN_SET(".USER_LEVEL.",use_level)")],
                ];
                $query->whereOr([$map1]);
            }
        });
        $list = (array)$db
            ->order('create_at desc')
            ->field('*,FROM_UNIXTIME(coupon_start_time,\'%Y-%m-%d\') start_time_format,FROM_UNIXTIME(coupon_end_time,\'%Y-%m-%d\') end_time_format')
            ->select();
        foreach ($list as &$item) {
            $item['coupon_quota'] = floatval($item['coupon_quota']);
            $item['coupon_discount'] = floatval($item['coupon_discount']);
            $item['use_threshold'] = floatval($item['use_threshold']);
        }
        $this->success('success',$list);
    }

    /**
     * @Notes: 领取优惠券
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/15 14:48
     */
    public function receive(){
        $id = $this->request->param('id');
        try{
            CouponService::ReceiveCoupon(UID,$id);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('领取优惠券失败,' . $e->getMessage());
        }
        $this->success('领取优惠券成功');
    }
}