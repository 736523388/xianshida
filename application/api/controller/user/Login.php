<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/10/30
 * Time: 11:37
 */

namespace app\api\controller\user;


use controller\BasicApi;
use Firebase\JWT\JWT;
use service\WechatService;
use think\Db;

class Login extends BasicApi
{
    /**
     * @var int token过期时间 单位秒
     */
    public $exp = 2*60*60;
    /**
     * 微信自动登录
     * @return \think\response\Json
     * @throws \WeChat\Exceptions\InvalidDecryptException
     * @throws \WeChat\Exceptions\InvalidResponseException
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    function wx_login(){
        $code = $this->request->param('code');
        $parent_id = $this->request->param('parent_id/d',0);
        $encryptedData = $this->request->param('encryptedData');
        $iv = $this->request->param('iv');
        $data = WechatService::WeMiniCrypt()->userInfo($code, $iv, $encryptedData);
        if (!$data) {
            $this->error('解密数据失败');
        }
        $openid = $data['openId'];
        $user = Db::table('store_member')->where('openid',$openid)->find();
        $udata['unionid'] = isset($data['unionId']) ? $data['unionId'] : '';
        $udata['nickname'] = $data['nickName'];
        $udata['headimg'] = $data['avatarUrl'];
        $udata['sex'] = $data['gender']==1 ? '男' : '女';
        $is_insider = false;
        if(!empty($user['id'])){
            $udata['id'] = $user['id'];
            /*if($user['level'] != '0'){
                $user_level = Db::table('store_member_level')->where(['is_deleted'=>'0'])->where('id',$user['level'])->field('id')->find();
                if(empty($user_level)){
                    $udata['level'] = 0;
                    $udata['level_duration'] = 0;
                }else{
                    $is_insider = true;
                }
            }*/
            Db::table('store_member')->update($udata);
        }else{
            $udata['parent_id'] = $parent_id;
            $udata['openid'] = $openid;
            $udata['id'] = Db::table('store_member')->insertGetId($udata);
        }
        $token_data = array(
            "iss" => "xhzsm",
            "uid" => $udata['id'],
            "exp"=>time() + $this->exp
        );
        $token = JWT::encode($token_data,config('jwt_key'));
        $return_data['token'] = $token;
        $return_data['exp'] = $this->exp;
        $return_data['is_insider'] = $is_insider;
        $this->success('登录成功',$return_data);
    }
}