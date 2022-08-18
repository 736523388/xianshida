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

namespace app\xueao\controller;

use controller\XueaoAdmin;
use think\Db;

/**
 * 商品评价
 * Class GoodsComment
 * @package app\xueao\controller
 */
class GoodsComment extends XueaoAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'StoreGoodsComment';

    /**
     * 列表
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '评价管理';
        $get = $this->request->get();
        $db = Db::name($this->table)->where(['is_deleted' => '0']);
        if (isset($get['create_at']) && $get['create_at'] !== '') {
            list($start, $end) = explode(' - ', $get['create_at']);
            $db->whereBetween('create_at', ["{$start} 00:00:00", "{$end} 23:59:59"]);
        }
        return parent::_list($db->order('id desc'));
    }


}
