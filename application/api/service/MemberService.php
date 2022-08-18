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

namespace app\api\service;

use service\LogService;
use think\Db;
use think\Exception;

/**
 * 会员服务
 * Class MemberService
 * @package app\api\service
 */
class MemberService extends \app\xueao\service\MemberService
{
    /**
     * @Notes: 更新用户等级
     * @param int $mid
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/19 20:46
     */
    public static function updateLevel($mid = 0)
    {

        $where = ['is_deleted' => '0', 'status' => '1'];

        $user = Db::table('store_member')
            ->where(['id' => $mid])
            ->field('id,level,self_expenditure,team_expenditure')
            ->findOrFail();
        //当前用户等级权重
        $level_sort = Db::table('store_member_level')
            ->where(['id' => $user['level'], 'is_deleted' => '0'])
            ->value('sort') ?: 0;
        //个人消费升级
        $self_consume_level = Db::table('store_member_level')
            ->where($where)->where(['open_self_personal_up' => '1'])
            ->where('self_personal_quota', '<=', $user['self_expenditure'])
            ->where('sort', '>', $level_sort)
            ->order('sort desc')->find();
        //团队消费升级
        $team_consume_level = Db::table('store_member_level')
            ->where($where)
            ->where(['open_team_personal_up' => '1'])
            ->where('team_personal_quota', '<=', $user['team_expenditure'])
            ->where('sort', '>', $level_sort)
            ->order('sort desc')
            ->find();

        $countInsider = self::countInsider($user['id']); //计算会员数量

        //邀请会员数量升级
        $invite_level = Db::table('store_member_level')
            ->where($where)
            ->where(['open_inviting_members' => '1'])
            ->where('inviting_member_num', '<=', $countInsider)
            ->where('sort', '>', $level_sort)
            ->order('sort desc')
            ->find();


        $upgrade_level = [];
        foreach ([$self_consume_level, $team_consume_level, $invite_level] as $item) {
            if (!empty($item)) {
                if (empty($upgrade_level) || (isset($upgrade_level['sort']) && $item['sort'] > $upgrade_level['sort'])) {
                    $upgrade_level = $item;
                }
            }
        }
        if (!empty($upgrade_level)) {
            self::setLevel($mid, $upgrade_level['id']);
        }
    }

    /**
     * @Notes: 计算会员数量
     * @param $mid
     * @return int
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/19 16:24
     */
    public static function countInsider($mid)
    {
        $child = self::Child($mid);
        $num = 0;
        foreach ($child as $item) {
            if ($item['level'] > 0) {
                $num++;
            }
        }

        return $num;
    }

    /**
     * @Notes: 返现
     * @param $order
     * @throws Exception
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/7 15:50
     */
    public static function goods_back($order, $parent = [])
    {
        if (empty($order)) {
            throw new Exception('未传入订单数据!');
        }
        if (empty($parent)) {
            $parent = self::SelfAndParent($order['mid']);
        }
        try {
            $user_info = Db::table('store_member')->where('id', $order['mid'])->field('id,nickname,level')->findOrFail();
            $levels = Db::table('store_member_level')->where(['is_deleted' => '0'])->field('id,level_title,sort')->select();
            $goodsList = Db::name('StoreOrderGoods')->alias('a')
                ->join('store_goods b', 'a.goods_id=b.id')
                ->where('a.order_no', $order['order_no'])
                ->where('a.mid', $order['mid'])
                ->field('a.market_price,a.selling_price,a.price_field,a.number,b.insider_back_ratio,b.ordinary_back_ratio')
                ->select();
            foreach ($levels as $key => $value) {
                $levels[$key]['price'] = 0;
                foreach ($goodsList as $k => $v) {
                    $v['insider_back_ratio'] = json_decode($v['insider_back_ratio'], true);
                    $v['ordinary_back_ratio'] = json_decode($v['ordinary_back_ratio'], true);
                    $insider_back = isset($v['insider_back_ratio'][$value['id']]) ? ($v[$v['price_field']] * $v['insider_back_ratio'][$value['id']] * $v['number']) / 100 : 0;
                    $ordinary_back = isset($v['ordinary_back_ratio'][$value['id']]) ? ($v[$v['price_field']] * $v['ordinary_back_ratio'][$value['id']] * $v['number']) / 100 : 0;
                    $price = $v['price_field'] == 'selling_price' ? $insider_back : $ordinary_back;
                    $levels[$key]['price'] += $price;
                }
            }
            Db::transaction(function () use ($order, $levels, $parent, $user_info) {
                foreach ($levels as $level) {
                    $ser = [];
                    $level['price'] = intval($level['price']);
                    foreach ($parent as $item) {
                        if ($item['level_sort'] >= $level['sort']) {
                            $ser = $item;
                            break;
                        }
                    }
                    //如果没有能获取此返现的用户
                    if (empty($ser)) {
                        continue;
                    }
                    $level['price'] = floor($level['price'] / sysconf('integral_exchange_ratio'));

                    //如果折算积分小于1
                    if (empty($level['price'])) {
                        continue;
                    }
                    //修改用户表
                    Db::table('store_member')
                        ->where('id', $ser['id'])
                        ->update([
                            'integral' => Db::raw('integral+' . $level['price']),
                            'integral_total' => Db::raw('integral_total+' . $level['price']),
                        ]);
                    //插入积分记录表
                    $user_txt = '';
                    $user_txt .= $ser['id'] == $user_info['id'] ? '您' : '用户' . $user_info['nickname'] . '[id:' . $user_info['id'] . ']';
                    Db::table('store_member_integral_log')->insert([
                        'mid' => $ser['id'],
                        'integral' => $level['price'],
                        'desc' => $user_txt . '消费' . $order['real_price'] . '元，' . '您获得' . $level['level_title'] . '奖励' . $level['price'] . '积分'
                    ]);
                }
            });
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @Notes: 消费记录和返现
     * @param $order
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/7 16:14
     */
    public static function consume_and_back($order)
    {
        Db::transaction(function () use ($order) {
            //如果订单不是完成状态设置成完成
            if($order['status']!=5){
                Db::name('store_order')->where('id',$order['id'])->setField('status',5);
            }
            //TODO::消息队列
            //获取这个订单的所有商品
            $order_goods=Db::name('store_order_goods')
                ->alias('og')
                ->join('rebate_template rt','rt.id=og.rebate_template_id')
                ->where('og.order_no',$order['order_no'])
                ->where('og.is_rebate',0)
                ->where('rt.status',1)
                ->field('og.market_price,og.selling_price,price_field,number,ratio')
                ->select();
            foreach ($order_goods as $k=>$v){
                //为每个产品返佣
                $self=$parent = Db::name('store_member')
                    ->where('id', $order['mid'])
                    ->field('id,nickname,parent_id')
                    ->find();//这个是自己
                $rebate=json_decode($v['ratio'],true);
                $rebate_num=count($rebate);//分销级数
                for ($i = 0; $i < $rebate_num; $i++) {
                    $parent = Db::name('store_member')
                        ->where('id', $parent['parent_id'])
                        ->field('id,parent_id')
                        ->find();
                    if (!$parent) {
                        break;
                    } else {
                        //给他积分
                        $price=$v[$v['price_field']]*$v['number'];
                        $integral=$price*$rebate[$i+1]/100;
                        if($integral>=0.01){
                            $desc='您的下（'.($i+1).'）级['.$self['nickname'].']成功消费['.$price.']元,您获得['.$integral.']积分奖励。';
                            self::log_account_change($parent['id'],$integral,$desc);
                        }
                    }
                }
            }
            //设置已返佣
            Db::name('store_order_goods')
                ->where('order_no',$order['order_no'])
                ->where('is_rebate',0)
                ->setField('is_rebate',1);
        });
    }

    /**
     * 消费升级处理
     * @param $mid
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function check_upgrade($mid){
        //判断这个人是不是VIP
        $member=Db::name('store_member')
            ->where('id',$mid)
            ->field('id,level')
            ->find();
        if($member['level']>0){
            return true;
        }
        //查询VIP等级
        $member_level=Db::name('store_member_level')
            ->where('is_deleted',0)
            ->where('status',1)
            ->findOrEmpty();
        if(!$member_level){
            return false;
        }
        //查询这个人的总消费金额
        $order_amount=Db::name('store_order')
            ->where('mid',$mid)
            ->where('status',5)
            ->sum('pay_price');
        if($order_amount>=$member_level['purchase_price']){
            //升级为VIP
            Db::name('store_member')
                ->where('id',$mid)
                ->setField('level',$member_level['id']);
            return true;
        }
        return false;
    }

    /**
     * @Notes:
     * @param $order_sn
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/10 14:37
     */
    public static function level_log_and_back($order_sn)
    {
        $order = Db::table('store_member_level_buy')->where('order_sn', $order_sn)->findOrFail();
        $user_level = Db::table('store_member')->where('id', $order['mid'])->value('level');
        $user_level_sort = (int)Db::table('store_member_level')->where('id', $user_level)->value('sort');
        $buy_level_sort = (int)Db::table('store_member_level')->where('id', $order['level'])->value('sort');
        if ($user_level_sort > $buy_level_sort) {
            return ['code' => 0, 'msg' => '用户当前级别已超过购买级别，请退款！'];
        }
        $parent = self::SelfAndParent($order['mid']);
        Db::transaction(function () use ($order, $parent) {
            /*消费记录*/
            self::consume_log($order['mid'], $order['real_price'], $parent);
            /*设置会员等级*/
            parent::setLevel($order['mid'], $order['level']);
            /*修改等级订单审核状态为已审核*/
            Db::table('store_member_level_buy')->where('id', $order['id'])->where('apply_status', '0')->setField('apply_status', '1');
            /*邀请奖励*/
            self::level_back($order, $parent);
        });
    }

    /**
     * @Notes: 购买等级邀请奖励
     * @param $order
     * @param $parent
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/10 17:06
     */
    public static function level_back($order, $parent)
    {
        $store_member_invitation_award = Db::table('store_member_invitation_award')->where(['status' => '1', 'is_deleted' => '0'])->where('invitee_level', $order['level'])->selectOrFail();
        $user_info = Db::table('store_member')->where('id', $order['mid'])->field('id,nickname,level')->findOrFail();
        $order['level_title'] = Db::table('store_member_level')->where('id', $order['level'])->value('level_title') ?: '';
        Db::transaction(function () use ($store_member_invitation_award, $order, $parent, $user_info) {
            foreach ($store_member_invitation_award as $value) {
                $value['sort'] = Db::table('store_member_level')->where('id', $value['inviter_level'])->value('sort') ?: 0;
                $ser = [];
                foreach ($parent as $item) {
                    if ($item['level_sort'] >= $value['sort']) {
                        $ser = $item;
                        break;
                    }
                }
                //如果没有能获取此返现的用户
                if (empty($ser)) {
                    continue;
                }
                $price = floor($value['percentage'] * $order['real_price'] / sysconf('integral_exchange_ratio'));

                //如果折算积分小于1
                if (empty($price)) {
                    continue;
                }
                //修改用户表
                Db::table('store_member')
                    ->where('id', $ser['id'])
                    ->update([
                        'integral' => Db::raw('integral+' . $price),
                        'integral_total' => Db::raw('integral_total+' . $price),
                    ]);
                //插入积分记录表
                Db::table('store_member_integral_log')->insert([
                    'mid' => $ser['id'],
                    'integral' => $price,
                    'desc' => '用户' . $user_info['nickname'] . '[id:' . $user_info['id'] . ']' . '升级' . $order['level_title'] . '花费' . $order['real_price'] . '元，' . '你获得邀请奖励' . $price . '积分'
                ]);
            }
        });
    }

    /**
     * @Notes:
     * @param $order
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/10 10:45
     */
    public static function level_log_and_back_pre($order_sn)
    {
        $order = Db::table('store_member_level_buy')->where('order_sn', $order_sn)->findOrFail();
        $level = Db::table('store_member_level')->where('id', $order['level'])->findOrFail();
        //如果不需要审核
        if ($level['is_need_examine'] == '0') {
            self::level_log_and_back($order_sn);
        }

    }

    /**
     * @Notes: 订单消费记录
     * @param int $mid
     * @param int $real_price
     * @param array $parent
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/9 21:26
     */
    public static function consume_log($mid = 0, $real_price = 0, $parent = [])
    {
        if (empty($parent)) {
            $parent = self::SelfAndParent($mid);
        }
        //用户id 实际消费金额 父级列表
        Db::transaction(function () use ($mid, $real_price, $parent) {
            //插入消费统计
            //插入自己个人消费统计
            Db::table('store_member')
                ->where('id', $mid)
                ->update([
                    'self_expenditure' => Db::raw('self_expenditure+' . $real_price),
                    'self_expenditure_total' => Db::raw('self_expenditure_total+' . $real_price)
                ]);
            //插入自己及父级团队消费统计
            foreach ($parent as $item) {
                Db::table('store_member')
                    ->where('id', $item['id'])
                    ->update([
                        'team_expenditure' => Db::raw('team_expenditure+' . $real_price),
                        'team_expenditure_total' => Db::raw('team_expenditure_total+' . $real_price)
                    ]);
            }
        });
    }

    /**
     * @Notes: 计算出用户的所有上级 (不包括自己)
     * @param $mid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/18 14:45
     */
    public static function Parent($mid)
    {
        $self = Db::table('store_member')->where('id', $mid)->field('level,parent_id')->findOrFail();
        $self_unfriend = Db::table('store_member_level')->where('id', $self['level'])->value('unfriend');
        $self_level_sort = Db::table('store_member_level')->where('id', $self['level'])->value('sort');
        $parent = Db::table('store_member')->where('id', $self['parent_id'])->field('id,nickname,level')->find();
        if (!empty($parent)) {
            $parent_level_sort = Db::table('store_member_level')->where('id', $parent['level'])->value('sort');
            $parent['level_sort'] = $parent_level_sort;
            if (empty($self['level']) || empty($self_unfriend) || (!empty($parent['level']) && $parent_level_sort > $self_level_sort)) {
                return array_merge([$parent], self::Parent($parent['id']));
            } else {
                return [];
            }
        } else {
            return [];
        }
    }

    /**
     * @Notes:计算出用户的所有上级 (包括自己)
     * @param $mid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2019/1/7 15:32
     */
    public static function SelfAndParent($mid)
    {
        $parent = self::Parent($mid);
        $user_info = Db::table('store_member')->where('id', $mid)->field('id,nickname,level')->findOrFail();
        if (!empty($user_info)) {
            $user_level_sort = Db::table('store_member_level')->where('id', $user_info['level'])->value('sort');
            array_unshift($parent, ['id' => $user_info['id'], 'nickname' => $user_info['nickname'], 'level' => $user_info['level'], 'level_sort' => $user_level_sort]);
        }
        return $parent;
    }

    /**
     * @Notes: 获取用户所有下级会员 （除去解除关系的）（不包括自己）
     * @param $mid
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/4 16:53
     */
    public static function Child($mid)
    {
        $data = Db::table('store_member')->where('parent_id', $mid)->select(); //所有下线
        $m_level = Db::table('store_member')->where('id', $mid)->value('level'); //自己等级
        $m_level_sort = Db::table('store_member_level')->where('id', $m_level)->value('sort'); // 我的等级权重
        foreach ($data as $key => $item) {                 // todo 这里使用循环查询   非常严重的坑
            //同级解除关系
            $item_unfriend = Db::table('store_member_level')->where('id', $item['level'])->value('unfriend');
            $item_level_sort = Db::table('store_member_level')->where('id', $item['level'])->value('sort');
            if (empty($item['level']) || empty($item_unfriend) || (!empty($m_level) && $m_level_sort > $item_level_sort)) {
                $data = array_merge($data, self::Child($item['id']));      // todo 递归调用   循环调用递归
            } else {
                unset($data[$key]);
            }
        }
        return $data;
    }

    /**
     * @Notes: 获取会员下级 不同等级的人数
     * @param $mid
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/4 16:59
     */
    public static function ChildNumber($mid, $Child = [])
    {
        $levels = [
            [
                'level' => 0,
                'level_title' => sysconf('registered_name')
            ]
        ];
        $user_level = Db::table('store_member')->where('id', $mid)->value('level');
        if ($user_level > 0) {
            $user_level_sort = Db::table('store_member_level')->where('id', $user_level)->value('sort');
            $map1 = [
                ['sort', '<', $user_level_sort]
            ];

            $map2 = [
                ['sort', '=', $user_level_sort],
                ['unfriend', '=', '0'],
            ];
            $subordinate_level = Db::table('store_member_level')
                ->whereOr([$map1, $map2])
                ->where('is_deleted', '0')
                ->where('unfriend', '0')
                ->field('id,level_title')
                ->select();
            foreach ($subordinate_level as $item) {
                $levels[] = [
                    'level' => $item['id'],
                    'level_title' => $item['level_title']
                ];
            }
        }

        if (empty($Child)) {
            $Child = self::Child($mid);
        }
        foreach ($levels as $key => $level) {
            $num = 0;
            foreach ($Child as $item) {
                if ($item['level'] == $level['level']) {
                    $num++;
                }
            }
            $levels[$key]['number'] = $num;
        }
        return $levels;
    }

    /**
     * @Notes:
     * @param $mid
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/14 16:19
     */
    public static function memberTeams($mid)
    {
        $Child = self::Child($mid);
        $levels = self::ChildNumber($mid, $Child);
        //return $levels;
        foreach ($levels as $key => $level) {
            $title = ['昵称'];
            foreach ($Child as $item) {
                if ($item['level'] == $level['level']) {
                    //$show = ['id：'.$item['id']];
                    $show = [$item['nickname'] . '[id:' . $item['id'] . ']'];
                    $teams = self::ChildNumber($item['id']);
                    foreach ($teams as $team) {
                        if (!in_array($team['level_title'], $title)) {
                            $title[] = $team['level_title'];
                        }
                        $show[] = $team['number'] . '人';
                    }
                    if (!in_array('总消费额', $title)) {
                        $title[] = '总消费额';
                    }
                    $show[] = $item['self_expenditure_total'];
                    if ($item['level'] > 0) {
                        if (!in_array('累计收益', $title)) {
                            $title[] = '累计收益';
                        }
                        if (!in_array('剩余天数', $title)) {
                            $title[] = '剩余天数';
                        }
                        $show[] = sprintf("%.2f", $item['integral_total'] * sysconf('integral_exchange_ratio'));
                        $show[] = (self::maktimes2day($item['level_duration']) > 0) ? self::maktimes2day($item['level_duration']) . '天' : '已过期';
                    }
                    //$levels[$key]['title'] = $title;
                    $levels[$key]['show'][] = $show;
                }

            }
            //$levels[$key]['title'] = $title;
            if (empty($levels[$key]['show'])) {
                $levels[$key]['show'] = [];
            } else {
                array_unshift($levels[$key]['show'], $title);
            }

        }
        array_multisort(array_column($levels, 'level'), SORT_DESC, $levels);
        return $levels;
    }

    public static function maktimes2day($time)
    {
        $t = $time - time();
        $day = $t / (24 * 60 * 60);
        return ceil($day);
    }

    /**
     * @Notes: 获取可用等级 包括可以购买以及可以申请
     * @param int $level 当前等级
     * @return array|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/4 17:50
     */
    public static function AvailableLevels($level = 0)
    {
        $db = Db::table('store_member_level')
            ->where('status', '1')
            ->where('pid', '0')
            ->where('is_deleted', '0')
            ->where('open_purchase|open_apply', '1');
        if ($level) {
            $user_level = Db::table('store_member_level')->where('is_deleted', '0')->where('id', $level)->find();
            if (!empty($user_level)) {
                $db->where('sort', '>=', $user_level['sort']);
            }
        }
        $data = $db->select();
        foreach ($data as $key => $item) {
            if ($item['id'] == 2) {
                $data[$key]['activeTime'] = Db::table('store_member_level')
                    ->where('id', 'in', '2,5,6')
                    ->column('id,level_title,purchase_price');
            }
            $data[$key]['level_logo'] = sysconf('applet_url') . $data[$key]['level_logo'];
            $data[$key]['level_image'] = sysconf('applet_url') . $data[$key]['level_image'];
            if ($item['id'] === $level) {
                $data[$key]['available_desc'] = '续费';
            } else {
                $data[$key]['available_desc'] = '升级';
            }
        }

        return $data;
    }

    /**
     * @Notes: 获取当前颜色方案
     * @param int $level 当前等级
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/4 17:57
     */
    public static function getColor($level = 0)
    {
        $data = [];
        $level_info = Db::table('store_member_level')->where(['id' => $level, 'is_deleted' => '0'])->find();
        if (!empty($level_info)) {
            $data = [
                'background_color_bcard_left' => $level_info['background_color_bcard_left'],
                'background_color_bcard_right' => $level_info['background_color_bcard_right'],
                'font_color_bcard_text' => $level_info['font_color_bcard_text'],
                'font_color_bcard_number' => $level_info['font_color_bcard_number'],
                'background_color_bcard_button' => $level_info['background_color_bcard_button'],
                'font_color_bcard_button' => $level_info['font_color_bcard_button'],
                'background_color_tips' => $level_info['background_color_tips'],
                'font_color_tips' => $level_info['font_color_tips'],
                'background_color_count' => $level_info['background_color_count'],
                'font_color_count_text' => $level_info['font_color_count_text'],
                'font_color_count_number' => $level_info['font_color_count_number'],
                'background_color_button' => $level_info['background_color_button'],
                'background_color_menu_extra_top_top' => $level_info['background_color_menu_extra_top_top'],
                'background_color_menu_extra_top_button' => $level_info['background_color_menu_extra_top_button'],
                'background_color_menu_extra_button' => $level_info['background_color_menu_extra_button'],
                'background_color_menu_top' => $level_info['background_color_menu_top'],
                'background_color_menu_bottom' => $level_info['background_color_menu_bottom'],
                'font_color_menu_h' => $level_info['font_color_menu_h'],
                'font_color_menu_p' => $level_info['font_color_menu_p'],
                'background_color_order_icon_top' => $level_info['background_color_order_icon_top'],
                'background_color_order_icon_bottom' => $level_info['background_color_order_icon_bottom'],
            ];
        } else {
            $data = [
                'background_color_bcard_left' => sysconf('background_color_bcard_left'),
                'background_color_bcard_right' => sysconf('background_color_bcard_right'),
                'font_color_bcard_text' => sysconf('font_color_bcard_text'),
                'font_color_bcard_number' => sysconf('font_color_bcard_number'),
                'background_color_bcard_button' => sysconf('background_color_bcard_button'),
                'font_color_bcard_button' => sysconf('font_color_bcard_button'),
                'background_color_tips' => sysconf('background_color_tips'),
                'font_color_tips' => sysconf('font_color_tips'),
                'background_color_button' => sysconf('background_color_button'),
                'background_color_menu_extra_top_top' => sysconf('background_color_menu_extra_top_top'),
                'background_color_menu_extra_top_button' => sysconf('background_color_menu_extra_top_button'),
                'background_color_menu_extra_button' => sysconf('background_color_menu_extra_button'),
                'background_color_menu_top' => sysconf('background_color_menu_top'),
                'background_color_menu_bottom' => sysconf('background_color_menu_bottom'),
                'font_color_menu_h' => sysconf('font_color_menu_h'),
                'font_color_menu_p' => sysconf('font_color_menu_p'),
                'background_color_order_icon_top' => sysconf('background_color_order_icon_top'),
                'background_color_order_icon_bottom' => sysconf('background_color_order_icon_bottom'),
            ];
        }
        return $data;
    }

    /**
     * 会员资金变动
     * @author jungshen
     * @param int $mid
     * @param int $integral
     * @param string $desc
     * @return bool
     */
    static function log_account_change($mid = 0, $integral = 0, $desc = '暂无备注')
    {
        if (!$mid || !$integral) return false;
        Db::startTrans();
        try {
            //修改用户积分
            unset($data);
            $data['integral'] = Db::raw('integral+' . $integral);
            if ($integral > 0) {
                $data['integral_total'] = Db::raw('integral_total+' . $integral);
            }
            Db::name('store_member')
                ->where('id', $mid)
                ->update($data);
            //记录明细
            $smil_data['mid'] = $mid;
            $smil_data['integral'] = $integral;
            $smil_data['desc'] = $desc;
            $smil_data['status'] = 1;
            $smil_data['is_deleted'] = 0;
            $smil_data['create_at'] = date('Y-m-d H:i:s');
            Db::name('store_member_integral_log')
                ->insert($smil_data);
        } catch (Exception $e) {
            Db::rollback();
            LogService::write('账户资金变动', '账户资金变动失败：' . $e->getMessage());
        }
        Db::commit();
    }
}