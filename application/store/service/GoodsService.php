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

namespace app\store\service;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\Db;

/**
 * 商品数据服务支持
 * Class ProductService
 * @package app\goods\service
 */
class GoodsService
{

    /**
     * @Notes: 主商品表数据处理
     * @param $goodsList
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/23 10:42
     */
    public static function buildGoodsList(&$goodsList)
    {
        // 商品分类处理
        $cateField = 'id,pid,cate_title,cate_desc';
        $cateWhere = ['status' => '1', 'is_deleted' => '0'];
        $cateList = Db::name('StoreGoodsCate')->where($cateWhere)->order('sort asc,id desc')->column($cateField);
        // 商品品牌处理
        $brandWhere = ['status' => '1', 'is_deleted' => '0'];
        $brandField = 'id,brand_logo,brand_cover,brand_title,brand_desc,brand_detail';
        $brandList = Db::name('StoreGoodsBrand')->where($brandWhere)->order('sort asc,id desc')->column($brandField);
        // 商品标签处理
        $tagsWhere = ['status' => '1', 'is_deleted' => '0'];
        $tagsField = 'id,tags_title,image as tags_image';
        $tagsList = Db::name('StoreGoodsTags')->where($tagsWhere)->order('sort asc,id desc')->column($tagsField);
        //商品类型处理
        $typeWhere = ['status' => '1', 'is_deleted' => '0'];
        $typeField = 'id,type_title,image as type_image';
        $typeList = Db::name('StoreGoodsType')->where($typeWhere)->order('sort asc,id desc')->column($typeField);
        // 无商品列表时
        if (empty($goodsList)) {
            return ['list' => $goodsList, 'cate' => $cateList, 'brand' => $brandList, 'tags' => $tagsList, 'type' => $typeList];
        }
        // 读取商品详情列表
        $specWhere = [['status', 'eq', '1'], ['is_deleted', 'eq', '0'], ['goods_id', 'in', array_column($goodsList, 'id')]];
        $specField = 'id,goods_id,goods_spec,goods_number,huaxian_price,market_price,selling_price,goods_stock,goods_sale';
        $specList = Db::name('StoreGoodsList')->where($specWhere)->column($specField);
        foreach ($specList as $key => $spec) {
            foreach ($goodsList as $goods) {
                if ($goods['id'] === $spec['goods_id']) {
                    $specList[$key]['goods_title'] = $goods['goods_title'];
                }
            }
            if ($spec['goods_spec'] === 'default:default') {
                $specList[$key]['goods_spec_alias'] = '默认规格';
            } else {
                $specList[$key]['goods_spec_alias'] = str_replace(['::', ';;'], [' ', ', '], $spec['goods_spec']);
            }
        }
        // 商品数据组装
        foreach ($goodsList as $key => $vo) {
            // 商品内容处理
            $goodsList[$key]['goods_content'] = $vo['goods_content'];
            // 商品品牌处理
            $goodsList[$key]['brand'] = isset($brandList[$vo['brand_id']]) ? $brandList[$vo['brand_id']] : [];
            // 商品类型处理
            $goodsList[$key]['type'] = isset($typeList[$vo['type_id']]) ? $typeList[$vo['type_id']] : [];
            // 商品分类关联
            $goodsList[$key]['cate'] = [];
            if (isset($cateList[$vo['cate_id']])) {
                $goodsList[$key]['cate'][] = ($tcate = $cateList[$vo['cate_id']]);
                while (isset($tcate['pid']) && $tcate['pid'] > 0 && isset($cateList[$tcate['pid']])) {
                    $goodsList[$key]['cate'][] = ($tcate = $cateList[$tcate['pid']]);
                }
                $goodsList[$key]['cate'] = array_reverse($goodsList[$key]['cate']);
            }
            //商品标签关联
            $goodsList[$key]['tags'] = [];
            $tags_id = explode(',',$vo['tags_id']);
            foreach ( $tagsList as $tags) {
                if(in_array($tags['id'],$tags_id)){
                    $goodsList[$key]['tags'][] = $tags;
                }
            }
            // 商品详细列表关联
            $goodsList[$key]['spec'] = [];
            foreach ($specList as $spec) {
                if ($vo['id'] === $spec['goods_id']) {
                    $goodsList[$key]['spec'][] = $spec;
                }
            }
            //数据字段修改
            $goodsList[$key]['goods_image'] = explode('|',!empty($vo['goods_image']) ? $vo['goods_image'] : '');
            if(!empty($vo['spec_id'])){
                $goods_spec = Db::name('StoreGoodsSpec')->where('id',$vo['spec_id'])->find();
                $goodsList[$key]['spec_list'] = json_decode(isset($goods_spec['spec_param']) ? $goods_spec['spec_param'] : '',true);
            }else{
                $goodsList[$key]['spec_list'] = [];
            }

        }
        return ['list' => $goodsList, 'cate' => $cateList, 'brand' => $brandList, 'tags' => $tagsList , 'type' => $typeList];
    }

    /**
     * 同步更新商品库存及售出
     * @param int $goods_id 商品ID
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public static function syncGoodsStock($goods_id)
    {
        // 检查商品是否需要更新库存
        $map = ['id' => $goods_id, 'is_deleted' => '0'];
        if (!($goods = Db::name('StoreGoods')->where($map)->find())) {
            return ['code' => 0, 'msg' => '指定商品信息无法同步库存！'];
        }
        // 统计入库信息
        $stockField = 'goods_id,goods_spec,ifnull(sum(goods_stock), 0) goods_stock';
        $stockWhere = ['status' => '1', 'is_deleted' => '0', 'goods_id' => $goods_id];
        $stockList = (array)Db::name('StoreGoodsStock')->field($stockField)->where($stockWhere)->group('goods_id,goods_spec')->select();
        // 统计销售信息
        $saleField = 'goods_id,goods_spec,ifnull(sum(number), 0) goods_sale';
        $saleWhere = ['status' => '1', 'is_deleted' => '0', 'goods_id' => $goods_id];
        $saleList = (array)Db::name('StoreOrderGoods')->field($saleField)->where($saleWhere)->group('goods_id,goods_spec')->select();
        // 库存置零
        list($where, $total_sale, $total_stock) = [['goods_id' => $goods_id], 0, 0];
        Db::name('StoreGoodsList')->where($where)->update(['goods_stock' => 0, 'goods_sale' => 0]);
        // 更新商品库存
        foreach ($stockList as $stock) {
            $total_stock += intval($stock['goods_stock']);
            $where = ['goods_id' => $goods_id, 'goods_spec' => $stock['goods_spec']];
            Db::name('StoreGoodsList')->where($where)->update(['goods_stock' => $stock['goods_stock']]);
        }
        // 更新商品销量
        foreach ($saleList as $sale) {
            $total_sale += intval($sale['goods_sale']);
            $where = ['goods_id' => $goods_id, 'goods_spec' => $sale['goods_spec']];
            Db::name('StoreGoodsList')->where($where)->update(['goods_sale' => $sale['goods_sale']]);
        }
        Db::name('StoreGoods')->where($map)->update(['package_stock' => $total_stock,'package_sale' => $total_sale]);
        return ['code' => 1, 'msg' => '同步商品库存成功！'];
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
        $sheet->getStyle('A:P')->applyFromArray(['alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ]]);
        $sheet->getStyle('A1:P2')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(50);
        $sheet->getColumnDimension('E')->setWidth(45);
        $sheet->getColumnDimension('F')->setWidth(35);
        $sheet->getColumnDimension('G')->setWidth(30);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(20);
        for ($i = 65; $i < $count + 65; $i++) {     //数字转字母从65开始，循环设置表头：
            if(($i-65)>3&&($i-65)<7){
                //商品信息
                if(($i-65)==4){
                    $sheet->mergeCells(chr($i).'1:'.chr($i+2).'1');
                    $sheet->setCellValue(strtoupper(chr($i)) . '1', $head[$i - 65]);
                }
                $goods_info=['规格名称','售价 ( 标价 ) ','库存 ( 剩余, 已售 )'];
                $sheet->setCellValue(strtoupper(chr($i)) . '2', $goods_info[$i-65-4]);

            }else{
                $sheet->mergeCells(chr($i).'1:'.chr($i).'2');
                $sheet->setCellValue(strtoupper(chr($i)) . '1', $head[$i - 65]);
            }
        }
        /*--------------开始从数据库提取信息插入Excel表中------------------*/
        $current_data_row=3;
        foreach ($data as $key => $item) {
            //循环设置单元格：

            $goods_num=count($item['spec']);
            for ($i = 65; $i < $count + 65; $i++) {     //数字转字母从65开始：
                if (($i-65)>3&&($i-65)<7){
                    //循环产品信息
                    if(($i-65)==4){
                        foreach ($item['spec'] as $k=>$v){
                            //规格名称 售价 库存
                            $sheet->setCellValue(strtoupper(chr($i)) . ($current_data_row+$k), $v['goods_spec_alias']);
                            $sheet->setCellValue(strtoupper(chr($i+1)) . ($current_data_row+$k), '会员价'. $v['selling_price'].'( 普通价 '.$v['market_price'] .')');
                            $sheet->setCellValue(strtoupper(chr($i+2)) . ($current_data_row+$k), '存'. $v['goods_stock'].'( 剩 '.($v['goods_stock'] - $v['goods_sale']).',售'.$v['goods_sale'] .')');
                        }
                    }
                }
                else{
                    //有几个商品就合并几列
                    if($goods_num>1){
                        $sheet->mergeCells(chr($i).$current_data_row.':'.chr($i).($current_data_row+$goods_num-1));
                    }
                    $goods_status_arr = ['已下架','销售中'];
                    $sheet->setCellValue(
                        strtoupper(chr($i)) . ($current_data_row),
                        $keys[$i - 65]!='status'?$item[$keys[$i - 65]]:$goods_status_arr[$item[$keys[$i - 65]]]);
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