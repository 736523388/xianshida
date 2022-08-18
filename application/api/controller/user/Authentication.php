<?php
namespace app\api\controller\user;

use app\api\controller\BasicUserApi;
use service\AliImageOCRService;
use service\AliYingYeService;
use think\Db;
use think\exception\HttpResponseException;

class Authentication extends BasicUserApi
{
    public $table = 'StoreMemberAuthentication';

    /**
     * @Notes: 我的实名认证列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/13 10:13
     */
    public function index(){
        $list = (array)Db::name($this->table)->where(['is_deleted'=>'0','status'=>'1','mid'=>UID])->order('create_at desc')->select();
        foreach ($list as $key => $value) {
            $list[$key]['image_front'] = sysconf('applet_url') .$list[$key]['image_front'];
            $list[$key]['image_other'] = sysconf('applet_url') .$list[$key]['image_other'];
        }
        $this->success('success',$list);
    }

    /**
     * @Notes: 添加实名认证
     * @return \think\Response
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/13 10:21
     */
    public function create(){
        try{
          
            $data = [
                'username' => $this->request->param('username'),
                'id_card' => $this->request->param('id_card'),
                'image_front' => str_replace('/static/upload/', sysconf('applet_url').'/static/upload/', $this->request->param('image_front')),
                'image_other' => str_replace('/static/upload/', sysconf('applet_url').'/static/upload/', $this->request->param('image_other')),
                'mid' => UID
            ];
            $validate = Validate('Authentication');
            $res = $validate->check($data);
            if(true !== $res) {
                $this->error($validate->getError());
            }
            Db::name($this->table)->insert($data);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('添加实名认证失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('添加实名认证成功');
    }

    /**
     * @Notes: 删除实名认证
     * @return \think\Response
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/13 10:33
     */
    public function delete(){
        try{
            $map = [
                'id' => $this->request->param('id'),
                'mid' => UID
            ];
            Db::name($this->table)->where($map)->setField('is_deleted','1');
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('删除实名认证失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('删除实名认证成功');
    }
    /**
     * @Notes: 修改实名认证
     * @return \think\Response
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/13 10:21
     */
    public function edit(){
        try{
            $data = [
                'username' => $this->request->param('username'),
                'id_card' => $this->request->param('id_card'),
                'image_front' => str_replace('/static/upload/', sysconf('applet_url').'/static/upload/', $this->request->param('image_front')),
                'image_other' => str_replace('/static/upload/', sysconf('applet_url').'/static/upload/', $this->request->param('image_other')),
                'id' => $this->request->param('id'),
                'mid' => UID
            ];
            $validate = Validate('Authentication');
            $res = $validate->check($data);
            if(true !== $res) {
                $this->error($validate->getError());
            }
            Db::name($this->table)->update($data);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('修改实名认证失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('修改实名认证成功');
    }

    /**
     * 身份证图片验证
     */
    public function imgOcr(){
        $post=$this->request->only(['image','idCardSide'],'post');
        $ret=AliImageOCRService::query($post['image'],$post['idCardSide']);
        if($ret['code']==1){
            $this->success($ret['msg'],$ret['result']);
        }else{
            $this->error($ret['msg'].',请上传清晰的身份证照');
        }
    }

    /**
     * 申请成为批发商
     * @return \think\Response
     */
    public function app_wholesaler(){
        try{
            $post=$this->request->only(['img','title','name','address','validtime','id_num','credit_code'],'post');
            $post['mid']=UID;
            $post['addtime']=time();
            $validate = Validate('Wholesaler');
            if(false === $validate->check($post)) {
                $this->error($validate->getError());
            }

            Db::name('wholesaler')->insert($post);

            $member_level=Db::name('store_member_level')
                ->where('is_deleted',0)
                ->where('status',1)
                ->findOrEmpty();
            if($member_level){
                //用户升级成为批发商
                Db::name('store_member')
                    ->where('id',UID)
                    ->setField('level',$member_level['id']);
            }
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('申请失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('申请成功');
    }

    /**
     * 营业执照验证
     */
    public function yingye(){
        $image=$this->request->post('image');
        $ret=AliYingYeService::query($image);
        if(!isset($ret['error_code'])){
            $this->success('success',$ret['words_result']);
        }else{
            $this->error($ret['error_msg'].',请上传清晰的营业执照');
        }
    }

    /**
     * 获取批发商营业执照信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function wholesaler_info()
    {
        $info=Db::name('wholesaler')
            ->where('mid',UID)
            ->findOrEmpty();
        $this->success('success',$info);
    }


}