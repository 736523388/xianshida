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

namespace app\xueao\controller;

use controller\XueaoAdmin;
use service\DataService;
use service\ToolsService;
use think\Db;

/**
 * Class City
 * @package app\jujin\controller
 */
class City extends XueaoAdmin
{

    /**
     * 定义当前操作表名
     * @var string
     */
    public $table = 'SystemCity';

    /**
     * @return array|string
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index()
    {
        $this->title = '城市列表';
        $get = $this->request->get();
        $db = Db::name($this->table);
        if (isset($get['pid']) && is_numeric($get['pid'])) {
            $db->where('pid', $get['pid']);
        }else{
            $db->where('pid', 0);
        }
        return parent::_list($db->order('sort,id'));
    }

    protected function _form_filter(&$vo,$data)
    {
        if($this->request->isGet()){
            if($this->request->get('id')){
                $this->title='编辑1';
                $this->assign('pid',$vo['pid']);
            }else{
                $this->title='添加';
                $this->assign('pid',$this->request->get('pid'));
            }
        }
    }
    /**
     * 添加/编辑成功回跳处理
     * @param bool $result
     */
    protected function _form_result($result)
    {
    }
}
