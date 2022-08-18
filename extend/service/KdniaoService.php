<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/12/11
 * Time: 16:42
 */

namespace service;

/**
 * 快递鸟服务
 * @author jungshen
 * Class KdniaoService
 * @package service
 */
class KdniaoService
{
    static private $instance;//对象实例
    static public $EBusinessID;//编码
    static public $ApiKey;//秘钥
    private function __construct(){
        self::$EBusinessID=sysconf('ebusiness_id');
        self::$ApiKey=sysconf('ebusiness_key');
    }
    private function __clone(){}
    static public function getInstance(){
        if(!self::$instance instanceof  self){
            self::$instance=new KdniaoService();
        }
        return self::$instance;
    }

    /**
     * 查询订单物流轨迹
     * $requestData['OrderCode']='订单号'             option
     *             ['ShipperCode']='物流公司代码'     required
     *             ['LogisticCode']='运单号'          required
     * @param array $requestData
     * @return mixed
     */
    function getOrderTracesByJson(array $requestData):array{
        $reqUrl='http://api.kdniao.com/Ebusiness/EbusinessOrderHandle.aspx';
        $datas = array(
            'EBusinessID' => self::$EBusinessID,
            'RequestType' => '1002',
            'RequestData' => urlencode(json_encode($requestData)) ,
            'DataType' => '2',
        );
        $datas['DataSign'] = $this->encrypt(json_encode($requestData), self::$ApiKey);
        $result=HttpService::post($reqUrl, $datas);
        return json_decode($result,true);
    }
    /**
     * 电商Sign签名生成
     * @param data 内容
     * @param appkey Appkey
     * @return DataSign签名
     */
    private function encrypt($data, $appkey) {
        return urlencode(base64_encode(md5($data.$appkey)));
    }
}