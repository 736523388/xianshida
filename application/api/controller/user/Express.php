<?php
namespace app\api\controller\user;
use app\api\controller\BasicUserApi;
use controller\BasicApi;

const EBusinessID = '1405023';
const AppKey = '31d2b497-9c42-457a-8692-d87a46ab4764';
//const ReqURL = 'http://testapi.kdniao.cc:8081/api/dist';
const ReqURL = 'http://api.kdniao.cc/Ebusiness/EbusinessOrderHandle.aspx';
/**
 * Class Express
 * @package app\api\controller\user
 */
class Express extends BasicApi
{
    public function test(){
        $requestData="{'OrderCode': 'SF201608081055208281',".
            "'ShipperCode':'SF',".
            "'LogisticCode':'3100707578976',".
            "'PayType':1,".
            "'ExpType':1,".
            "'IsNotice':0,".
            "'Cost':1.0,".
            "'OtherCost':1.0,".
            "'Sender':".
            "{".
            "'Company':'LV','Name':'Taylor','Mobile':'15018442396','ProvinceName':'上海','CityName':'上海','ExpAreaName':'青浦区','Address':'明珠路73号'},".
            "'Receiver':".
            "{".
            "'Company':'GCCUI','Name':'Yann','Mobile':'15018442396','ProvinceName':'北京','CityName':'北京','ExpAreaName':'朝阳区','Address':'三里屯街道雅秀大厦'},".
            "'Commodity':".
            "[{".
            "'GoodsName':'鞋子','Goodsquantity':1,'GoodsWeight':1.0}],".
            "'Weight':1.0,".
            "'Quantity':1,".
            "'Volume':0.0,".
            "'Remark':'小心轻放'}";


        $datas = array(
            'EBusinessID' => EBusinessID,
            'RequestType' => '1008',
            'RequestData' => urlencode($requestData) ,
            'DataType' => '2',
        );
        $datas['DataSign'] = $this->encrypt($requestData, AppKey);
        $result = $this->sendPost(ReqURL, $datas);
        echo $result;exit();
        $result = json_decode($result,true);
        dump($result);
    }
    public function getOrderTracesByJson(){
        $requestData= "{'OrderCode':'','ShipperCode':'YTO','LogisticCode':'12345678'}";

        $datas = array(
            'EBusinessID' => EBusinessID,
            'RequestType' => '1002',
            'RequestData' => urlencode($requestData) ,
            'DataType' => '2',
        );
        $datas['DataSign'] = $this->encrypt($requestData, AppKey);
        $result = $this->sendPost(ReqURL, $datas);
        $result = json_decode($result,true);
        dump($result);
    }
    /**
     *  post提交数据
     * @param  string $url 请求Url
     * @param  array $datas 提交的数据
     * @return url响应返回的html
     */
    public function sendPost($url, $datas) {
        $temps = array();
        foreach ($datas as $key => $value) {
            $temps[] = sprintf('%s=%s', $key, $value);
        }
        $post_data = implode('&', $temps);
        $url_info = parse_url($url);
        if(empty($url_info['port']))
        {
            $url_info['port']=80;
        }
        $httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
        $httpheader.= "Host:" . $url_info['host'] . "\r\n";
        $httpheader.= "Content-Type:application/x-www-form-urlencoded\r\n";
        $httpheader.= "Content-Length:" . strlen($post_data) . "\r\n";
        $httpheader.= "Connection:close\r\n\r\n";
        $httpheader.= $post_data;
        $fd = fsockopen($url_info['host'], $url_info['port']);
        fwrite($fd, $httpheader);
        $gets = "";
        $headerFlag = true;
        while (!feof($fd)) {
            if (($header = @fgets($fd)) && ($header == "\r\n" || $header == "\n")) {
                break;
            }
        }
        while (!feof($fd)) {
            $gets.= fread($fd, 128);
        }
        fclose($fd);

        return $gets;
    }
    /**
     * 电商Sign签名生成
     * @param data 内容
     * @param appkey Appkey
     * @return DataSign签名
     */
    public function encrypt($data, $appkey) {
        return urlencode(base64_encode(md5($data.$appkey)));
    }
}