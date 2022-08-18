<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/10/24
 * Time: 13:46
 */

namespace app\api\controller\base;

use controller\BasicApi;
use think\Db;
use think\Exception;

class City extends BasicApi
{
    /**
     * @author jungshen
     * 获取城市列表
     * @param pid 上级id default:0
     * @return \think\response\Json
     */
    public function lists(){
        try{
            $pid=input('pid',0);
            //获取城市列表
            $list=Db::name('system_city')->where('pid',$pid)->where('status',1)->order('sort')->field('id,name')->select();
            return json(['msg'=>'success','data'=>$list],200);
        }catch (Exception $e){
            return json(['msg'=>$e->getMessage()],500);
        }
    }


    /**
     * 根据城市名称获取城市ID
     * @author jungshen
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_city_id(){
        $city=$this->request->get('city','重庆');
        $level=$this->request->get('level','1');
        if($level==1){
            $city_id=Db::name('system_city')->where('pid',0)->where('name',$city)->value('id');
            return json(['msg'=>'success','data'=>['province_id'=>$city_id]]);
        }elseif ($level==2){
            $city=Db::name('system_city')->where('pid','<>',0)->where('name',$city)->field('id,pid')->find();
            return json(['msg'=>'success',
                'data'=>['province_id'=>$city['pid'],
                         'city_id'=>$city['id']
                        ]
                ]);
        }

    }
}