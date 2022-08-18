<?php

// +----------------------------------------------------------------------
// | ThinkAdmin
// +----------------------------------------------------------------------
// | 版权所有 2014~2017 广州楚才信息科技有限公司 [ http://www.cuci.cc ]
// +----------------------------------------------------------------------
// | 官方网站: http://think.ctolog.com
// +----------------------------------------------------------------------
// | 开源协议 ( https://mit-license.org )
// +----------------------------------------------------------------------
// | github开源项目：https://github.com/zoujingli/ThinkAdmin
// +----------------------------------------------------------------------

use app\api\service\OrderService;
use service\DataService;
use service\NodeService;
use think\Db;
use think\facade\Env;

const ORDER_STATUS_INVALID=0;//无效订单
const ORDER_STATUS_UNPAID=1;//未支付（新订单）
const ORDER_STATUS_UNSHIPPED=2;//待发货
const ORDER_STATUS_SHIPPED=3;//已发货
const ORDER_STATUS_RECEIVED=4;//已收货
const ORDER_STATUS_COMPLETED=5;//已完成
const ORDER_STATUS_BACKED=6;//已退货/款
const ORDER_STATUS_ARR=[
    ORDER_STATUS_INVALID=>'无效',
    ORDER_STATUS_UNPAID=>'新订单',
    ORDER_STATUS_UNSHIPPED=>'待发货',
    ORDER_STATUS_SHIPPED=>'已发货',
    ORDER_STATUS_RECEIVED=>'已收货',
    ORDER_STATUS_COMPLETED=>'已完成',
    ORDER_STATUS_BACKED=>'已退货及退款',
];



/**
 * 通过id主键获取记录或者字段值
 * @param $id
 * @param $table_name
 * @param null $field
 * @return array|mixed|null|PDOStatement|string|\think\Model
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function getmodel($id,$table_name,$field=null){
    $vo = Db::name($table_name)->where('id',$id)->find();
    if(is_null($field)){
        return $vo;
    }else{
        return $vo[$field];
    }
}

/**
 * 得到城市名称
 * @param $id
 * @return array|mixed|null|PDOStatement|string|\think\Model
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function get_city_name($id){
    if($id>0){
        return getmodel($id, 'system_city', 'name');
    }else{
        return '';
    }
}

/**
 * 生成指定长度的随机字符串
 * @param unknown $length
 * @param unknown $type 0:全部字符 1：只要数字 2：小写字母 3：大写字母
 * @return string
 */
function createRandomStr($length,$type=0){
    switch ($type){
        case 0:
            $str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';//62个字符
            break;
        case 1:
            $str = '0123456789';
            break;
        case 2:
            $str = 'abcdefghijklmnopqrstuvwxyz';
            break;
        case 3:
            $str = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            break;
    }
    $strlen = strlen($str);
    while($length > $strlen){
        $str .= $str;
        $strlen *=2;
    }
    $str = str_shuffle($str);
    return substr($str,0,$length);
}

/**
 * @author jungshen
 * 订单完成 收货后佣金分配
 * @param string $order_ids 订单ID，多个逗号隔开
 * @return bool
 * @throws \think\Exception
 */
function finish_order($order_ids=''){
    if(!$order_ids)return false;
    $list = Db::table('store_order')->whereIn('id',$order_ids)
        ->field('id,mid,order_no,real_price,goods_price,freight_price,discount_amount,member_discount_amount,status')->select();
    foreach ($list as $item) {
        /*Db::transaction(function () use($item){
            //计算节省金额
            Db::table('store_member')->where('id',$item['mid'])->setInc('save_amount',$item['member_discount_amount']);
            //记录消费统计和执行分佣操作
            \app\api\service\MemberService::consume_and_back($item);
        });*/
        /*记录消费统计和执行分佣操作*/
        \app\api\service\MemberService::consume_and_back($item);
        /*消费升级*/
        //\app\api\service\MemberService::check_upgrade($item['mid']);
    }
}
/**
 * 给用户退款
 * @author jungshen
 * @param $order_no
 * @return bool
 * @throws \think\Exception
 */
function do_back_order($order_no){
    if(!$order_no)return false;
    $order = Db::table('store_order')->where('order_no',$order_no)->find();
    $pay_price = $order['pay_price'];
    $pay_integral = $order['use_integral'];
    Db::transaction(function () use($order,$pay_price,$pay_integral){
        if($pay_integral){
            Db::table('store_member')->where('id',$order['mid'])->setInc('integral',$pay_integral);
            \app\api\service\IntegralService::RecordLog($order['mid'],$pay_integral,'购买商品');
        }
        if($pay_price>0&&$order['pay_type']=='wechat'){
            sysconf('wechat_cert_cert',Env::get('root_path').'static/cert/apiclient_cert.pem');
            sysconf('wechat_cert_key',Env::get('root_path').'static/cert/apiclient_key.pem');
            /*微信退款*/
            $options = [
                'out_trade_no' => $order['order_no'],
                'total_fee' => $pay_price * 100,
                'refund_fee' => $pay_price * 100,
                'out_refund_no' => DataService::createSequence(10, 'ORDER')
            ];
            $result = \app\api\service\WeChatPayService::WePayRefund($options);
            if($result['return_code'] !== 'SUCCESS' || $result['result_code'] !== 'SUCCESS'){
                throw new Exception('微信退款失败！'.isset($result['err_code_des']) ? $result['err_code_des'] : '发起退款请求失败！');
            }
        }
    });
}
/**
 * 根据订单号获取退款类型
 * @author jungshen
 * @param $order_no
 * @return string
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
function get_back_order_text($order_no,$type=0){
    $back_order=Db::name('store_order_back')
        ->where('order_no',$order_no)
        ->field('status,is_back_goods')->find();
    if($type==0){
        return '退'.($back_order['is_back_goods']==0?'款':'货');
    }else{
        if($back_order['status'] == 0){
            return '审核';
        }elseif ($back_order['status'] == 1){
           return '退款';
        }
    }
}
/**
 * 打印输出数据到文件
 * @param mixed $data 输出的数据
 * @param bool $force 强制替换
 * @param string|null $file
 */
function p($data, $force = false, $file = null)
{
    is_null($file) && $file = env('runtime_path') . date('Ymd') . '.txt';
    $str = (is_string($data) ? $data : ((is_array($data) || is_object($data)) ? print_r($data, true) : var_export($data, true))) . PHP_EOL;
    $force ? file_put_contents($file, $str) : file_put_contents($file, $str, FILE_APPEND);
}

/**
 * RBAC节点权限验证
 * @param string $node
 * @return bool
 */
function auth($node)
{
    return NodeService::checkAuthNode($node);
}

/**
 * 设备或配置系统参数
 * @param string $name 参数名称
 * @param bool $value 默认是null为获取值，否则为更新
 * @return string|bool
 * @throws \think\Exception
 * @throws \think\exception\PDOException
 */
function sysconf($name, $value = null)
{
    static $config = [];
    if ($value !== null) {
        list($config, $data) = [[], ['name' => $name, 'value' => $value]];
        return DataService::save('SystemConfig', $data, 'name');
    }
    if (empty($config)) {
        $config = Db::name('SystemConfig')->column('name,value');
    }
    return isset($config[$name]) ? $config[$name] : '';
}
/**
 * 设备或配置系统参数
 * @param string $name 参数名称
 * @param bool $value 默认是null为获取值，否则为更新
 * @return string|bool
 * @throws \think\Exception
 * @throws \think\exception\PDOException
 */
function memberconf($name, $value = null)
{
    static $config = [];
    if ($value !== null) {
        list($config, $data) = [[], ['name' => $name, 'value' => $value]];
        return DataService::save('StoreMemberConfig', $data, 'name');
    }
    if (empty($config)) {
        $config = Db::name('StoreMemberConfig')->column('name,value');
    }
    return isset($config[$name]) ? $config[$name] : '';
}
/**
 * 日期格式标准输出
 * @param string $datetime 输入日期
 * @param string $format 输出格式
 * @return false|string
 */
function format_datetime($datetime, $format = 'Y年m月d日 H:i:s')
{
    return date($format, strtotime($datetime));
}

/**
 * UTF8字符串加密
 * @param string $string
 * @return string
 */
function encode($string)
{
    list($chars, $length) = ['', strlen($string = iconv('utf-8', 'gbk', $string))];
    for ($i = 0; $i < $length; $i++) {
        $chars .= str_pad(base_convert(ord($string[$i]), 10, 36), 2, 0, 0);
    }
    return $chars;
}

/**
 * UTF8字符串解密
 * @param string $string
 * @return string
 */
function decode($string)
{
    $chars = '';
    foreach (str_split($string, 2) as $char) {
        $chars .= chr(intval(base_convert($char, 36, 10)));
    }
    return iconv('gbk', 'utf-8', $chars);
}

/**
 * 下载远程文件到本地
 * @param string $url 远程图片地址
 * @return string
 */
function local_image($url)
{
    return \service\FileService::download($url)['url'];
}
function getFirstCharter($str) {
    if (empty($str)) {return '';}
    if(is_numeric($str[0])) return $str[0];// 如果是数字开头 则返回数字
    if($str[0]=='I' || $str[0]=='i'){
        return "I";
    }elseif($str[0]=='U' || $str[0]=='u'){
        return 'U';
    }elseif($str[0]=='V' || $str[0]=='v'){
        return 'V';
    }else {
        $fchar = ord($str[0]);
        if ($fchar >= ord('A') && $fchar <= ord('z')) return strtoupper($str[0]);
        $s1 = iconv('UTF-8', 'gb2312', $str);
        $s2 = iconv('gb2312', 'UTF-8', $s1);
        $s = $s2 == $str ? $s1 : $str;
        $asc = ord($s[0]) * 256 + ord($s[1]) - 65536;
        if ($asc >= -20319 && $asc <= -20284) return 'A';
        if ($asc >= -20283 && $asc <= -19776) return 'B';
        if ($asc >= -19775 && $asc <= -19219) return 'C';
        if ($asc >= -19218 && $asc <= -18711) return 'D';
        if ($asc >= -18710 && $asc <= -18527) return 'E';
        if ($asc >= -18526 && $asc <= -18240) return 'F';
        if ($asc >= -18239 && $asc <= -17923) return 'G';
        if ($asc >= -17922 && $asc <= -17418) return 'H';
        if ($asc >= -17417 && $asc <= -16475) return 'J';
        if ($asc >= -16474 && $asc <= -16213) return 'K';
        if ($asc >= -16212 && $asc <= -15641) return 'L';
        if ($asc >= -15640 && $asc <= -15166) return 'M';
        if ($asc >= -15165 && $asc <= -14923) return 'N';
        if ($asc >= -14922 && $asc <= -14915) return 'O';
        if ($asc >= -14914 && $asc <= -14631) return 'P';
        if ($asc >= -14630 && $asc <= -14150) return 'Q';
        if ($asc >= -14149 && $asc <= -14091) return 'R';
        if ($asc >= -14090 && $asc <= -13319) return 'S';
        if ($asc >= -13318 && $asc <= -12839) return 'T';
        if ($asc >= -12838 && $asc <= -12557) return 'W';
        if ($asc >= -12556 && $asc <= -11848) return 'X';
        if ($asc >= -11847 && $asc <= -11056) return 'Y';
        if ($asc >= -11055 && $asc <= -10247) return 'Z';
        return null;
    }
}
/**
 * @Notes: 二维数组分组
 * @param $arr
 * @param $key
 * @return array
 * @author: Forska
 * @email: 736523388@qq.com
 * @DateTime: 2018/11/9 17:59
 */
function group_same_key($arr,$key){
    $new_arr = array();
    foreach ($arr as $k => $v) {
        $new_arr[$v[$key]][] = $v;
    }
    return $new_arr;
}
