<?php
/**
 * Created by PhpStorm.
 * 用户基类控制器
 * User: jungshen
 * Date: 2018/7/26
 * Time: 10:51
 */
namespace app\api\controller;


use app\api\service\MemberService;
use controller\BasicApi;
use think\Db;
use think\exception\HttpResponseException;

class BasicUserApi extends BasicApi
{
    /**
     * BasicUserApi constructor.
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function __construct()
    {
        parent::__construct();
        $token = request()->header('token', input('token', ''));
        $user = get_login_info($token);
        empty($user) && $this->error('未登陆或登陆已过期');
        if($user['status'] == 0){
            $this->error('用户已禁用！');
        }
        $is_insider = false;

        if($user['level'] !== 0/* && $user['level_duration']>time()*/){
            try {
                $level = Db::table('store_member_level')->where(['is_deleted'=>'0','id'=>$user['level']])->find();
                if(empty($level)){
                    Db::table('store_member')->where(['id'=>$user['id']])->update(['level'=>0,'level_duration'=>0]);
                    $user['level'] = 0;
                }else{
                    $is_insider = true;
                }
            } catch (HttpResponseException $exception) {
                return $exception->getResponse();
            } catch (\Exception $e) {
                $this->error('登陆失败，请重试！' . $e->getMessage());
            }
        }
        define('UID',$user['id']);
        define('IS_INSIDER',$is_insider);
        define('USER_LEVEL',$user['level']);
    }
}