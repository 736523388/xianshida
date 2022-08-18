<?php
namespace app\api\controller\base;

use app\api\service\MemberService;
use controller\BasicApi;
use think\Db;
/**
 * Class ExpressTrace
 * @package app\api\controller\base
 */
class ExpressTrace extends BasicApi
{
    /**
     * @Notes: 接收快递鸟推送轨迹
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/26 17:34
     */
    public function receiveExpress()
    {
        date_default_timezone_set('prc');
        $data = file_get_contents('php://input');
        $msg  = explode("&", $data);
        foreach ($msg as $k => $v) {
            $p = explode("=", $v);
            if ($p[0] == "RequestData") {
                $result[$p[0]] = json_decode(urldecode($p[1]), true);
            } else {
                $result[$p[0]] = urldecode($p[1]);
            }
        }
        if(!empty($result['RequestData'])){
            //取出运单信息
            foreach ($result['RequestData']["Data"] as $expressInfo) {
                //快递公司编码
                $shipperCode = $expressInfo["ShipperCode"];
                //运单号
                $logisticCode = $expressInfo["LogisticCode"];
                //状态: 0.暂无轨迹, 2.在途, 3.已签收, 4.问题件
                $state = $expressInfo["State"];
                //轨迹明细
                $traces = $expressInfo["Traces"];
                $traces_data = [];
                foreach ($traces as $item) {
                    //时间
                    //$acceptTime = $item["AcceptTime"];
                    //内容
                    //$acceptStation = $item["AcceptStation"];
                    $traces_data[] = [
                        'AcceptTime' => $item["AcceptTime"],
                        'AcceptStation' => $item["AcceptStation"],
                    ];
                }
                $data = [
                    'logistic_code' => $shipperCode,
                    'shipper_code' => $logisticCode,
                    'traces' => json_encode($traces_data),
                    'status' => $state
                ];
                Db::table('store_express_trace')->insert($data);
            }
            $returnData = [
                'EBusinessID' => $result['RequestData']['EBusinessID'],
                'UpdateTime' => $result['RequestData']['PushTime'],
                'Success' => true
            ];


            //日志记录
            file_put_contents('ExpressTrace.txt', "----------------------------" .
                date('Y-m-d h:i:s', time()) .
                "----------------------------" .
                PHP_EOL."接收内容: " . json_encode($result) .
                PHP_EOL."返回结果: " . json_encode($returnData) . PHP_EOL . PHP_EOL, FILE_APPEND);
            return json($returnData);
        }else{
            //返回失败结果
            $returnContent = '{"EBusinessID": '.sysconf('ebusiness_id').'," UpdateTime": "'.date('Y-m-d h:i:s',time()).'"," Success": false," Reason":"缺少RequestData参数"}';
            echo $returnContent;

            //记录日志
            file_put_contents('ExpressTrace.txt', "----------------------------".date('Y-m-d h:i:s',time())."----------------------------".PHP_EOL."接收内容: None".PHP_EOL."返回结果: ".$returnContent.PHP_EOL, FILE_APPEND);
        }
    }
    public function test(){

    }
}
