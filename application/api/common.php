<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/7/27
 * Time: 11:16
 */
use think\Db;

/**
 * 得到当前登录信息
 * @param $token 登录Token
 * @param null $filed 获取字段
 * @return array|bool|mixed|null|PDOStatement|string|\think\Model
 */
function get_login_info($token , $filed = null){
    try{
        if(!$token) return false;
        //解密Token
        $token_data = (array)\Firebase\JWT\JWT::decode($token,config('jwt_key'),["HS256"]);
        if($token_data['exp'] < time()){
            return false;
        }
        $db = Db::table('store_member')->where('id',$token_data['uid']);
        if($filed){
            $usermember = $db->field($filed)->find();
            if(strstr($filed, ',')){
                return $usermember;
            }else{
                return $usermember[$filed];
            }
        }else{
            $usermember = $db->find();
            return $usermember;
        }
    }catch (Exception $e){
        return false;
    }
}
/**
 * 生成订单编号
 * @author jungshen
 * @return string
 */
function create_order_sn($len=16,$type='order_sn'){
    $order_sn=\service\DataService::createSequence($len,$type);
    return $order_sn;
}