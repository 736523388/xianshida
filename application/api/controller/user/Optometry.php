<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2019/5/10
 * Time: 10:05
 */

namespace app\api\controller\user;


use app\api\controller\BasicUserApi;
use think\Db;

class Optometry extends BasicUserApi
{
    /**
     * 验光记录
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function lists(){
        $list=Db::name('optometry')
            ->where('mid',UID)
            ->where('status',1)
            ->field('id,yanguangshi,FROM_UNIXTIME(test_time, \'%Y/%m/%d\') test_time')
            ->select();
        $this->success('success',$list);
    }

    /**
     * 验光记录详情
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    function view(){
        $id=input('get.id');
        $optometry=Db::name('optometry')
            ->where('id',$id)
            ->where('mid',UID)
            ->find();
        if($optometry){
            $optometry['birthday']?$optometry['birthday']=date('Y-m-d',$optometry['birthday']):'';
            $optometry['test_time']?$optometry['test_time']=date('Y-m-d',$optometry['test_time']):'';
            $optometry['review_time']?$optometry['review_time']=date('Y-m-d',$optometry['review_time']):'';
            $optometry['yanguangchufang']=json_decode($optometry['yanguangchufang'],true);
            $optometry['peijingchufang']=json_decode($optometry['peijingchufang'],true);
        }
        $this->success('success',$optometry);
    }
}