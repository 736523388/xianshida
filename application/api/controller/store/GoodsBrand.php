<?php
/**
 * Created by PhpStorm.
 * User: forska
 * Date: 2018/11/8
 * Time: 11:37
 */

namespace app\api\controller\store;

use controller\BasicApi;
use think\Db;
use Yurun\Util\Chinese;
use Yurun\Util\Chinese\Pinyin;

class GoodsBrand extends BasicApi
{
    public $page = 1;
    public $pagesize = 10;
    public $table = 'StoreGoodsBrand';
    public $homepage = 0;
    public $field = 'id,brand_logo,brand_cover,brand_title,brand_desc,is_homepage,sort,status,is_deleted,create_at';

    /**
     * @Notes: 获取商品品牌列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/9 15:24
     */
    public function index(){
        $pagesize = (int)$this->request->param('pagesize',$this->pagesize);
        $page = (int)$this->request->param('page',$this->page);
        $homepage = (int)$this->request->param('homepage',$this->homepage);
        $list = (array)Db::name($this->table)->where(['is_deleted' => '0','status' => '1','is_homepage' => $homepage])->order('sort asc,id desc')->field($this->field)->page($page,$pagesize)->select();
        foreach ($list as &$value) {
            $value['brand_logo'] = sysconf('applet_url').$value['brand_logo'];
            $value['brand_cover'] = sysconf('applet_url').$value['brand_cover'];
        }
        $this->success('success',$list);
    }

    /**
     * @Notes: 获取品牌详情
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/9 15:28
     */
    public function detail(){
        $id = $this->request->param('id');
        $detail = (array)Db::name($this->table)->where(['is_deleted' => '0','id' => $id])->field($this->field)->find();
        $detail['goods_number'] = Db::table('store_goods')->where('brand_id',$id)->where(['status' => '1','is_deleted' => '0'])->count();
        $detail['brand_logo'] = sysconf('applet_url').$detail['brand_logo'];
        $detail['brand_cover'] = sysconf('applet_url').$detail['brand_cover'];
        $this->success('success',$detail);
    }

    /**
     * @Notes:
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/15 18:14
     */
    public function lists(){
        $result = Db::table('store_goods_brand')->where(['status' => '1','is_deleted' => '0'])->order('create_at desc,id asc')->field('id,brand_title,brand_logo')->select();
        foreach ($result as &$item) {
            $item['brand_logo'] = sysconf('applet_url').$item['brand_logo'];
            $pinyinFirst = Chinese::toPinyin($item['brand_title'], Pinyin::CONVERT_MODE_PINYIN_FIRST);
            $item['zimu'] = strtoupper($pinyinFirst['pinyinFirst'][0][0]);
        }
        $result = group_same_key($result,'zimu');
        ksort($result);
        $this->success('success',$result);
    }
}