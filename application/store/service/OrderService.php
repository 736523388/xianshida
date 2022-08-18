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

namespace app\store\service;

use service\DataService;
use service\ToolsService;
use think\Db;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * 商城订单服务
 * Class OrderService
 * @package app\store
 */
class OrderService
{
    /**
     * 商城创建订单
     * @param int $mid 会员ID
     * @param string $params 商品参数规格 (商品ID@商品规格@购买数量;商品ID@商品规格@购买数量)
     * @param int $addressId 地址记录ID
     * @param int $expressId 快递记录ID
     * @param string $orderDesc 订单描述
     * @param integer $orderType 订单类型
     * @param string $from 订单来源
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function create($mid, $params, $addressId, $expressId, $orderDesc = '', $orderType = 1, $from = 'wechat')
    {
        // 会员数据获取与检验
        if (!($member = Db::name('StoreMember')->where(['id' => $mid])->find())) {
            return ['code' => 0, 'msg' => '会员数据处理异常，请刷新重试！'];
        }
        // 订单数据生成
        list($order_no, $orderList) = [DataService::createSequence(10, 'ORDER'), []];
        $order = ['mid' => $mid, 'order_no' => $order_no, 'real_price' => 0, 'goods_price' => 0, 'desc' => $orderDesc, 'type' => $orderType, 'from' => $from];
        foreach (explode(';', trim($params, ',;@')) as $param) {
            list($goods_id, $goods_spec, $number) = explode('@', "{$param}@@");
            $item = ['mid' => $mid, 'type' => $orderType, 'order_no' => $order_no, 'goods_id' => $goods_id, 'goods_spec' => $goods_spec, 'goods_number' => $number];
            $goodsResult = self::buildOrderData($item, $order, $orderList, 'selling_price');
            if (empty($goodsResult['code'])) {
                return $goodsResult;
            }
        }
        // 生成快递信息
        $expressResult = self::buildExpressData($order, $addressId, $expressId);
        if (empty($expressResult['code'])) {
            return $expressResult;
        }
        try {
            // 写入订单信息
            Db::transaction(function () use ($order, $orderList, $expressResult) {
                Db::name('StoreOrder')->insert($order); // 主订单信息
                Db::name('StoreOrderGoods')->insertAll($orderList); // 订单关联的商品信息
                Db::name('storeOrderExpress')->insert($expressResult['data']); // 快递信息
            });
            // 同步商品库存列表
            foreach (array_unique(array_column($orderList, 'goods_id')) as $stock_goods_id) {
                GoodsService::syncGoodsStock($stock_goods_id);
            }
        } catch (\Exception $e) {
            return ['code' => 0, 'msg' => '商城订单创建失败，请稍候再试！' . $e->getLine() . $e->getFile() . $e->getMessage()];
        }
        return ['code' => 1, 'msg' => '商城订单创建成功！', 'order_no' => $order_no];
    }

    /**
     * 生成订单快递数据
     * @param array $order 订单主表记录
     * @param int $address_id 会员地址ID
     * @param int $express_id 快递信息ID
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function buildExpressData(&$order, $address_id, $express_id)
    {
        // 收货地址处理
        $addressWhere = ['mid' => $order['mid'], 'id' => $address_id, 'status' => '1', 'is_deleted' => '0'];
        $addressField = 'username express_username,phone express_phone,province express_province,city express_city,area express_area,address express_address';
        if (!($address = Db::name('StoreMemberAddress')->field($addressField)->where($addressWhere)->find())) {
            return ['code' => 0, 'msg' => '收货地址数据异常！'];
        }
        // 物流信息查询
        $expressField = 'express_title,express_code';
        $expressWhere = ['id' => $express_id, 'status' => '1', 'is_deleted' => '0'];
        if (!($express = Db::name('StoreExpress')->field($expressField)->where($expressWhere)->find())) {
            return ['code' => 0, 'msg' => '快递公司数据异常！'];
        }
        // @todo 运费计算处理
        // $order['freight_price'] = '0.00';
        // $order['real_price'] += floatval($order['freight_price']);
        $extend = ['mid' => $order['mid'], 'order_no' => $order['order_no'], 'type' => $order['type']];
        return ['code' => 1, 'data' => array_merge($address, $express, $extend), 'msg' => '生成快递信息成功！'];
    }

    /**
     * 订单数据生成
     * @param array $item 订单单项参数
     * (mid,type,order_no,goods_id,goods_spec,goods_number)
     * @param array $order 订单主表
     * @param array $orderList 订单详细表
     * @param string $price_field 实际计算单价字段
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private static function buildOrderData($item, &$order, &$orderList, $price_field = 'selling_price')
    {
        list($mid, $type, $order_no, $goods_id, $goods_spec, $number) = [
            $item['mid'], $item['type'], $item['order_no'], $item['goods_id'], $item['goods_spec'], $item['goods_number'],
        ];
        // 商品主体信息
        $goodsField = 'goods_title,goods_logo,goods_image';
        $goodsWhere = ['id' => $goods_id, 'status' => '1', 'is_deleted' => '0'];
        if (!($goods = Db::name('StoreGoods')->field($goodsField)->where($goodsWhere)->find())) {
            return ['code' => 0, 'msg' => "无效的商品信息！", 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
        }
        // 商品规格信息
        $specField = 'goods_id,goods_spec,market_price,selling_price,goods_stock,goods_sale';
        $specWhere = ['status' => '1', 'is_deleted' => '0', 'goods_id' => $goods_id, 'goods_spec' => $goods_spec];
        if (!($goodsSpec = Db::name('StoreGoodsList')->field($specField)->where($specWhere)->find())) {
            return ['code' => 0, 'msg' => '无效的商品规格信息！', 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
        }
        // 商品库存检查
        if ($goodsSpec['goods_stock'] - $goodsSpec['goods_sale'] < $number) {
            return ['code' => 0, 'msg' => '商品库存不足，请更换其它商品！', 'data' => "{$goods_id}, {$goods_spec}, {$number}"];
        }
        // 订单价格处理
        $goodsSpec['price_field'] = $price_field;
        $orderList[] = array_merge($goods, $goodsSpec, ['mid' => $mid, 'number' => $number, 'order_no' => $order_no, 'type' => $type]);
        $order['goods_price'] += floatval($goodsSpec[$price_field]) * $number;
        $order['real_price'] += floatval($goodsSpec[$price_field]) * $number;
        return ['code' => 1, 'msg' => '商品添加到订单成功！'];
    }

    /**
     * 订单主表数据处理
     * @param array $list
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function buildOrderList(&$list)
    {
        $mids = array_unique(array_column($list, 'mid'));
        $orderNos = array_unique(array_column($list, 'order_no'));
        $memberList = Db::name("StoreMember")->whereIn('id', $mids)->select();
        $goodsList = Db::name('StoreOrderGoods')->whereIn('order_no', $orderNos)->select();
        $expressList = Db::name('StoreOrderExpress')->whereIn('order_no', $orderNos)->select();
        foreach ($list as $key => $vo) {
            list($list[$key]['member'], $list[$key]['goods'], $list[$key]['express']) = [[], [], []];
            foreach ($memberList as $member) {
                $member['nickname'] = ToolsService::emojiDecode($member['nickname']);
                ($vo['mid'] === $member['id']) && $list[$key]['member'] = $member;
            }
            foreach ($expressList as $express) {
                ($vo['order_no'] === $express['order_no']) && $list[$key]['express'] = $express;
            }
            foreach ($goodsList as $goods) {
                if ($goods['goods_spec'] === 'default:default') {
                    $goods['goods_spec_alias'] = '默认规格';
                } else {
                    $goods['goods_spec_alias'] = str_replace([':', ','], ['：', '，'], $goods['goods_spec']);
                }
                ($vo['order_no'] === $goods['order_no']) && $list[$key]['goods'][] = $goods;
            }
        }
        return $list;
    }

    /**
     * @author jungshen
     * 订单导出excel表
     * $data：要导出excel表的数据，接受一个二维数组
     * $name：excel表的表名
     * $head：excel表的表头，接受一个一维数组
     * $key：$data中对应表头的键的数组，接受一个一维数组
     * 备注：此函数缺点是，表头（对应列数）不能超过26；
     *循环不够灵活，一个单元格中不方便存放两个数据库字段的值
     */
    public function export($name='unname', $data=[], $head=[], $keys=[])
    {
        $count = count($head);  //计算表头数量

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getStyle('A:Q')->applyFromArray(['alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ]]);
        $sheet->getStyle('A1:P2')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(50);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(10);
        $sheet->getColumnDimension('K')->setWidth(10);
        $sheet->getColumnDimension('L')->setWidth(50);
        $sheet->getColumnDimension('M')->setWidth(15);
        $sheet->getColumnDimension('N')->setWidth(25);
        $sheet->getColumnDimension('O')->setWidth(25);
        $sheet->getColumnDimension('P')->setWidth(25);
        $sheet->getColumnDimension('Q')->setWidth(25);
        for ($i = 65; $i < $count + 65; $i++) {     //数字转字母从65开始，循环设置表头：
            if(($i-65)>5&&($i-65)<11){
                //商品信息
                if(($i-65)==6){
                    $sheet->mergeCells(chr($i).'1:'.chr($i+4).'1');
                    $sheet->setCellValue(strtoupper(chr($i)) . '1', $head[$i - 65]);
                }
                $goods_info=['名称','金额','数量','进货价','仓库'];
                $sheet->setCellValue(strtoupper(chr($i)) . '2', $goods_info[$i-65-6]);

            }else{
                $sheet->mergeCells(chr($i).'1:'.chr($i).'2');
                $sheet->setCellValue(strtoupper(chr($i)) . '1', $head[$i - 65]);
            }
        }
        /*--------------开始从数据库提取信息插入Excel表中------------------*/
        $current_data_row=3;
        foreach ($data as $key => $item) {
            //循环设置单元格：
            $item['order_goods_info']=explode('_||_',$item['order_goods_info']);
            $goods_num=count($item['order_goods_info']);
            for ($i = 65; $i < $count + 65; $i++) {     //数字转字母从65开始：
                if(($i-65)>5&&($i-65)<11){
                    //商品信息
                    //循环产品信息
                    if(($i-65)==6){
                        foreach ($item['order_goods_info'] as $k=>$v){
                            //每一件产品信息
                            $v=explode('_|_',$v);
                            //名称 金额  数量  进货价 仓库
                            $sheet->setCellValue(strtoupper(chr($i)) . ($current_data_row+$k), $v[0]);
                            $sheet->setCellValue(strtoupper(chr($i+1)) . ($current_data_row+$k), $v[1]);
                            $sheet->setCellValue(strtoupper(chr($i+2)) . ($current_data_row+$k), $v[2]);
                            //这两条预留 进货价 仓库
                            $sheet->setCellValue(strtoupper(chr($i+3)) . ($current_data_row+$k), $v[3]);
                            $sheet->setCellValue(strtoupper(chr($i+4)) . ($current_data_row+$k), $v[4]);
                        }
                    }
                }else{
                    //有几个商品就合并几列
                    if($goods_num>1){
                        $sheet->mergeCells(chr($i).$current_data_row.':'.chr($i).($current_data_row+$goods_num-1));
                    }
                    if(($i-65)==15){
                        //门店
                        $sheet->setCellValue(
                            strtoupper(chr($i)) . ($current_data_row),
                            $item[$keys[$i - 65]]>0?getmodel($item[$keys[$i - 65]],'store','title'):'');
                    }elseif(($i-65)==16){
                        //发货方式
                        $way_text[0]='未发货';
                        $way_text[1]='快递';
                        $way_text[2]='上门取货';
                        $sheet->setCellValue(
                            strtoupper(chr($i)) . ($current_data_row),
                            $way_text[$item[$keys[$i - 65]]]);
                    }else{
                        $sheet->setCellValue(
                            strtoupper(chr($i)) . ($current_data_row),
                            $keys[$i - 65]!='status'?$item[$keys[$i - 65]]:ORDER_STATUS_ARR[$item[$keys[$i - 65]]]);
                    }
                }
            }
            $current_data_row+=$goods_num;//记录当前行

        }
        //设置行高
        for ($i=3;$i<$current_data_row;$i++){
            $sheet->getRowDimension($i)->setRowHeight('20');
        }

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $name . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');

        //删除清空：
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        exit;
    }

}