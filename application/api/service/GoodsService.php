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

namespace app\api\service;

use service\FileService;
use service\ToolsService;
use service\WechatService;
use think\Db;
use think\Image;

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
     * @param int $page 页码
     * @param int $pagesize 显示条数
     * @param string $sort 排序
     * @param string $keyword 关键字 用于搜索
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/6 17:49
     */
    public static function buildGoodsList(&$goodsList)
    {
        // 无商品列表时
        if (empty($goodsList)) {
            return ['code' => 1, 'msg' => 'success', 'data' => []];
        }
        //商品类型处理
        $typeWhere = ['status' => '1', 'is_deleted' => '0'];
        $typeField = 'id,type_title,image as type_image';
        $typeList = Db::name('StoreGoodsType')->where($typeWhere)->order('sort asc,id desc')->column($typeField);
        foreach ($typeList as &$type) {
            $type['type_image'] = $type['type_image'];
        }

        // 商品数据组装
        foreach ($goodsList as $key => $vo) {
            // 商品类型处理
            isset($vo['type_id']) ? ($goodsList[$key]['goods_type'] = isset($typeList[$vo['type_id']]) ? $typeList[$vo['type_id']] : []) : '';
            if ($goodsList[$key]['goods_spec'] === 'default:default') {
                $goodsList[$key]['goods_spec_alias'] = '默认规格';
            } else {
                $goodsList[$key]['goods_spec_alias'] = str_replace([':', ','], [': ', ', '], $goodsList[$key]['goods_spec']);
            }
            //数据字段修改
            $goods_image = explode('|', !empty($vo['goods_image']) ? $vo['goods_image'] : '');
            $goodsList[$key]['goods_image'] = $goods_image;
            $goodsList[$key]['goods_logo'] = $goodsList[$key]['goods_logo'];
        }
        return ['code' => 1, 'msg' => 'success', 'data' => $goodsList];
    }

    /**
     * @Notes: 商品详情处理
     * @param array $goodsdetail
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/7 14:42
     */
    public static function buildGoodsDetail(&$goodsdetail = [])
    {
        // 无商品列表时
        if (empty($goodsdetail)) {
            return ['code' => 1, 'msg' => 'success', 'data' => []];
        }
        $goodsdetail['specs'] = json_decode($goodsdetail['specs'], 1);
        foreach ($goodsdetail['specs'] as $key => $value) {
            $dd = [];
            foreach ($value['list'] as $item) {
                $dd[] = [
                    'spec_title' => $item['name'],
                    'is_show' => $item['show'],
                    'is_seleted' => false,
                    'is_elective' => true
                ];
            }
            $goodsdetail['specs'][$key]['value'] = $dd;
            unset($goodsdetail['specs'][$key]['list']);
        }
        $goodsdetail['spec_list'] = $goodsdetail['specs'];
        // 商品分类处理
        $cateField = 'id,pid,cate_title,cate_desc';
        $cateWhere = ['status' => '1', 'is_deleted' => '0'];
        $cateList = Db::name('StoreGoodsCate')->where($cateWhere)->order('sort asc,id desc')->column($cateField);
        // 商品品牌处理
        $brandWhere = ['status' => '1', 'is_deleted' => '0', 'id' => $goodsdetail['brand_id']];
        $brandField = 'id,brand_logo,brand_cover,brand_title,brand_desc,brand_detail';
        $brandList = Db::name('StoreGoodsBrand')->where($brandWhere)->order('sort asc,id desc')->column($brandField);
        foreach ($brandList as &$brand) {
            $brand['brand_logo'] = sysconf('applet_url') . $brand['brand_logo'];
            $brand['brand_cover'] = sysconf('applet_url') . $brand['brand_cover'];
        }
        // 商品标签处理
        $tagsWhere = [['status', '=', '1'], ['is_deleted', '=', '0']];
        $tagsField = 'id,tags_title,image as tags_image';
        $tagsList = Db::name('StoreGoodsTags')->where($tagsWhere)->order('sort asc,id desc')->field($tagsField)->select();
        foreach ($tagsList as $key => $value) {
            $tagsList[$key]['tags_image'] = sysconf('applet_url') . $value['tags_image'];
        }

        //商品类型处理
        $typeWhere = ['status' => '1', 'is_deleted' => '0', 'id' => $goodsdetail['type_id']];
        $typeField = 'id,type_title,image as type_image';
        $typeList = Db::name('StoreGoodsType')->where($typeWhere)->order('sort asc,id desc')->column($typeField);
        foreach ($typeList as &$type) {
            $type['type_image'] = sysconf('applet_url') . $type['type_image'];
        }
        // 读取商品详情列表
        $specWhere = [['status', '=', '1'], ['is_deleted', '=', '0'], ['goods_id', '=', $goodsdetail['id']]];
        $specField = 'id,goods_id,goods_spec,goods_number,huaxian_price,market_price,selling_price,goods_stock,goods_sale';
        $specList = Db::name('StoreGoodsList')->where($specWhere)->column($specField);
        foreach ($specList as $key => $spec) {
            $specList[$key]['goods_spec_alias_arr'] = self::getSpecAlias($spec['goods_spec']);
            if ($spec['goods_spec'] === 'default:default') {
                $specList[$key]['goods_spec_alias'] = '默认规格';
            } else {
                $specList[$key]['goods_spec_alias'] = str_replace(['::', ';;'], [' ', ', '], $spec['goods_spec']);
            }
        }
        // 商品品牌处理
        $goodsdetail['goods_brand'] = isset($brandList[$goodsdetail['brand_id']]) ? $brandList[$goodsdetail['brand_id']] : [];
        // 商品类型处理
        $goodsdetail['goods_type'] = isset($typeList[$goodsdetail['type_id']]) ? $typeList[$goodsdetail['type_id']] : [];
        // 商品分类关联
        $goodsdetail['cate'] = [];
        if (isset($cateList[$goodsdetail['cate_id']])) {
            $goodsdetail['cate'][] = ($tcate = $cateList[$goodsdetail['cate_id']]);
            while (isset($tcate['pid']) && $tcate['pid'] > 0 && isset($cateList[$tcate['pid']])) {
                $goodsdetail['cate'][] = ($tcate = $cateList[$tcate['pid']]);
            }
            $goodsdetail['cate'] = array_reverse($goodsdetail['cate']);
        }
        //商品标签关联
        $goodsdetail['tags'] = [];
        $tags_id = explode(',', $goodsdetail['tags_id']);

        foreach ($tagsList as $tags) {
            if (in_array($tags['id'], $tags_id)) {
                $goodsdetail['tags'][] = $tags;
            }
        }
        // 商品详细列表关联
        $goodsdetail['spec'] = [];
        foreach ($specList as $spec) {
            if ($goodsdetail['id'] === $spec['goods_id']) {
                $goodsdetail['spec'][] = $spec;
            }
        }
        //数据字段修改
        //数据字段修改
        $goods_image = explode('|', !empty($goodsdetail['goods_image']) ? $goodsdetail['goods_image'] : '');
        foreach ($goods_image as $k => $v) {
            $goods_image[$k] = sysconf('applet_url') . $v;
        }
        $goodsdetail['goods_image'] = $goods_image;
        $goodsdetail['goods_logo'] = sysconf('applet_url') . $goodsdetail['goods_logo'];

        if (!empty($goodsdetail['spec_id'])) {
            $goods_spec = Db::name('StoreGoodsSpec')->where('id', $goodsdetail['spec_id'])->find();
            $spec_list = json_decode(isset($goods_spec['spec_param']) ? $goods_spec['spec_param'] : '', true);
            foreach ($spec_list as $key => $item) {
                $spec_list[$key]['value'] = !empty(explode(';;', $item['value'])) ? explode(';;', $item['value']) : [];
            }
            $goodsdetail['spec_list'] = array_filter($spec_list);
        }
        return ['code' => 1, 'msg' => 'success', 'data' => $goodsdetail];
    }

    /**
     * 根据产品信息和用户ID创建产品分享图片
     * @param $goods 商品明细或ID
     * @param null $uid 用户ID
     * @param $goods_obj 商品控制器对象 这个可以只传两个价格和标签名
     * @return array|bool|int|string
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author jungshen
     */
    static function createGoodsShareImg($goods, $uid = null, $goods_obj)
    {
        if (!$uid || $uid == '') return false;
        if (is_numeric($goods)) {
            $goods = (array)Db::name('store_goods')->where(['id' => $goods, 'is_deleted' => '0', 'status' => '1'])->find();
            self::buildGoodsDetail($goods);
        }
        //二维码命名规则  商品名称+图片路径+标签数组数据+描述+两个价格+类型名+价格标签名+用户ID+商品ID hash值
        $tags = '';
        foreach ($goods['tags'] as $k => $v) {
            $tags .= $v['tags_image'] . $v['tags_title'];
        }
        //处理商品名称和描述
        $goods['goods_title'] = ToolsService::break_string(mb_substr($goods['goods_title'], 0, 29), 10) . (strlen($goods['goods_title']) > 29 ? '...' : '');//文字换行
        $goods['goods_desc'] = ToolsService::break_string(mb_substr($goods['goods_desc'], 0, 49), 20) . (strlen($goods['goods_title']) > 49 ? '...' : '');//文字换行
        $qr_code_name = md5(
            $goods['goods_title'] .
            $goods['goods_logo'] .
            $tags .
            $goods['goods_desc'] .
            $goods['spec'][0][$goods_obj->price_field] .
            $goods['spec'][0][$goods_obj->hide_price_field] .
            $goods['goods_type']['type_title'] .
            $uid .
            $goods['id'] .
            $goods_obj->hide_price_txt
        );//二维码名称

        $qr_code_path = './static/upload/share_img/' . $goods['id'] . '/';
        if (!is_dir($qr_code_path)) {
            mkdir($qr_code_path, 0755, true);
        }
        $qr_code_path_name = $qr_code_path . $qr_code_name . '.png';//分享海报图片
        //判断这个文件是否存在
        if (!file_exists($qr_code_path_name)) {
            $goods_img_name = md5($goods['goods_logo']);//产品图片名称

            $dir = './static/upload/share_img/goods/' . $goods['id'] . '/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            //商品图片
            $goods_img = $dir . $goods_img_name . ToolsService::getUrlSuffix($goods['goods_logo']);
            if (!file_exists($goods_img)) {
                file_put_contents($goods_img, file_get_contents($goods['goods_logo']));
                Image::open($goods_img)->thumb(450, 450)->save($goods_img);
            }
            //二维码
            $qr_code_img = self::createQrCode($goods['id'], $uid);
            $share_img = Image::open('./static/upload/share_img/share_bg.png');
            $font = './static/upload/share_img/msyh.ttf';//字体路径

            $share_img
                ->water($goods_img, [84, 25])//商品图片
                //->water('./static/upload/share_img/logo.png',[55,55])//LOGO
                ->water($qr_code_img, [400, 670])//二维码
                ->text($goods['goods_title'], $font, 18, '#000000', [40, 670], 0, 0)//待优化商品名称
                ->text($goods['goods_desc'], $font, 12, '#666666', [40, 670 + 110], 0, 0)//待优化商品描述
                ->text($goods['goods_desc'], $font, 12, '#666666', [40, 670 + 110], 0, 0)//待优化商品描述
                ->text('¥' . $goods['spec'][0][$goods_obj->price_field], $font, 18, '#f32c6e', [40, 670 + 110 + 100], 0, 0)//红色价格
                ->text('¥' . $goods['spec'][0][$goods_obj->hide_price_field], $font, 16, '#666666', [40 + 100 + 50, 670 + 110 + 100], 0, 0)//灰色价格
                //价格标签
                ->water('./static/upload/share_img/price_bg.png', [40 + 100, 670 + 110 + 100 - 10])//价格标签
                ->text($goods_obj->hide_price_txt, $font, 8, '#ffffff', [40 + 100 + 5, 670 + 110 + 100 + 5 - 10], 0, 0)//价格标签文字
                //分类标签
                ->water('./static/upload/share_img/tag_bg.png', [40 + 100 + 50 + 100, 670 + 110 + 100 - 10])//价格标签
                ->text($goods['goods_type']['type_title'], $font, 14, '#000000', [40 + 100 + 50 + 100 + 7, 670 + 110 + 100 + 7 - 10], 0, 0)//价格标签文字
            ;
            //处理标签
            foreach ($goods['tags'] as $k => $v) {
                //图标本地路径
                $dir = './static/upload/share_img/icon/';

                $v['icon'] = $dir . $v['id'] . ToolsService::getUrlSuffix($v['tags_image']);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                if (!file_exists($v['icon'])) {
                    file_put_contents($v['icon'], file_get_contents($v['tags_image']));
                    //Image::open($v['icon'])->thumb(50,50)->save($v['icon']);
                    self::pngthumb($v['icon'], $v['icon'], 50, 50);
                }
                $share_img->water($v['icon'], [28 + 185 * $k, 530]);//标签图标
                $share_img->text($v['tags_title'], $font, 20, '#FFFFFF', [28 + 185 * $k + 50, 540]);//标签文字
            }
            $share_img->save($qr_code_path_name);
        }

        //最终的海报图片
        // $qr_code = sysconf('applet_url').trim($qr_code_path_name,'.');
        $qr_code = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . trim($qr_code_path_name, '.');

        return $qr_code;
    }

    /*
     *$sourePic:原图路径
    * $smallFileName:小图名称
    * $width:小图宽
    * $heigh:小图高
    * 转载注明 www.chhua.com*/
    static function pngthumb($sourePic, $smallFileName, $width, $heigh)
    {
        $image = imagecreatefrompng($sourePic);//PNG
        imagesavealpha($image, true);//这里很重要 意思是不要丢了$sourePic图像的透明色;
        $BigWidth = imagesx($image);//大图宽度
        $BigHeigh = imagesy($image);//大图高度
        $thumb = imagecreatetruecolor($width, $heigh);
        imagealphablending($thumb, false);//这里很重要,意思是不合并颜色,直接用$img图像颜色替换,包括透明色;
        imagesavealpha($thumb, true);//这里很重要,意思是不要丢了$thumb图像的透明色;
        if (imagecopyresampled($thumb, $image, 0, 0, 0, 0, $width, $heigh, $BigWidth, $BigHeigh)) {
            imagepng($thumb, $smallFileName);
        }
        return $smallFileName;//返回小图路径 转载注明 www.chhua.com
    }

    /**
     * 根据用户ID生成商品推广二维码
     * @param $goods_id
     * @param $uid
     * @return string
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \WeChat\Exceptions\LocalCacheException
     * @author jungshen
     */
    private static function createQrCode($goods_id, $uid)
    {
        $dir = './static/upload/share_img/' . $goods_id . '/';
        $file = $uid . '.jpg';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!file_exists($dir . $file)) {
            $sence = $uid . '_' . $goods_id;
            $page = 'pages/index/productContent/productContent';
            $res = WechatService::WeMiniQrcode()->createMiniScene($sence, $page);
            file_put_contents($dir . $file, $res);
            //最小只能生成280的尺寸 处理尺寸
            Image::open($dir . $file)->thumb(180, 180)->save($dir . $file);
        }
        return $dir . $file;
    }

    public static function getSpecAlias($goods_spec, $return_type = 1)
    {
        $goods_spec_arr =[];
        foreach (explode(';;', $goods_spec) as $item) {
            $goods_spec_arr[] = explode('::', $item)[1];
        }
        if($return_type === 2){
            return join(' ', $goods_spec_arr);
        }
        return $goods_spec_arr;
    }

}