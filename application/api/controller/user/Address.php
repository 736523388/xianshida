<?php
namespace app\api\controller\user;

use app\api\controller\BasicUserApi;
use think\Db;
use think\exception\HttpResponseException;

class Address extends BasicUserApi
{
    public $table = 'StoreMemberAddress';

    /**
     * @Notes: 我的收货地址列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/13 10:13
     */
    public function index(){
        $list = (array)Db::name($this->table)->where(['is_deleted'=>'0','status'=>'1','mid'=>UID])->order('create_at desc')->select();
        $this->success('success',$list);
    }

    /**
     * @Notes: 添加收货地址
     * @return \think\Response
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/13 10:21
     */
    public function create(){
        try{
            $data = [
                'username' => $this->request->param('username'),
                'phone' => $this->request->param('phone'),
                'province' => $this->request->param('province'),
                'city' => $this->request->param('city'),
                'area' => $this->request->param('area'),
                'address' => $this->request->param('address'),
                'mid' => UID
            ];
            $validate = Validate('Address');
            $res = $validate->check($data);
            if(true !== $res) {
                $this->error($validate->getError());
            }
            Db::name($this->table)->insert($data);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('添加收货地址失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('添加收货地址成功');
    }

    /**
     * @Notes: 删除地址
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
            $this->error('删除收货地址失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('删除收货地址成功');
    }

    /**
     * @Notes: 设置默认收货地址
     * @return \think\Response
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/13 10:36
     */
    public function todefault(){
        try{
            $map = [
                'id' => $this->request->param('id'),
                'mid' => UID
            ];
            Db::name($this->table)->where('mid',UID)->setField('is_default','0');
            Db::name($this->table)->where($map)->setField('is_default','1');
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('设为默认失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('设为默认成功');
    }

    /**
     * @Notes: 修改收货地址
     * @return \think\Response
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/12/3 17:41
     */
    public function edit(){
        try{
            $data = [
                'username' => $this->request->param('username'),
                'phone' => $this->request->param('phone'),
                'province' => $this->request->param('province'),
                'city' => $this->request->param('city'),
                'area' => $this->request->param('area'),
                'address' => $this->request->param('address'),
                'id' => $this->request->param('id'),
                'mid' => UID
            ];
            $validate = Validate('Address');
            $res = $validate->check($data);
            if(true !== $res) {
                $this->error($validate->getError());
            }
            Db::name($this->table)->update($data);
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (\Exception $e) {
            $this->error('修改收货地址失败，请稍候再试！' . $e->getMessage());
        }
        $this->success('修改收货地址成功');
    }
}