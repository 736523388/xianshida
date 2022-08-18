<?php
namespace app\api\controller\base;
use app\api\controller\BasicUserApi;
use service\FileService;

class File extends BasicUserApi
{
    /**
     * @Notes: 上传文件
     * @throws \OSS\Core\OssException
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * @author: Forska
     * @email: 736523388@qq.com
     * @DateTime: 2018/11/14 19:44
     */
    public function upload(){
        $file = $this->request->file('file');
        $names = str_split(md5(date('YmHis').rand(100,999)), 16);
        $ext = strtolower(pathinfo($file->getInfo('name'), 4));
        $ext = $ext ? $ext : 'tmp';
        $filename = "{$names[0]}/{$names[1]}.{$ext}";
        // 文件上传处理
        if (($info = $file->move("static/upload/{$names[0]}", "{$names[1]}.{$ext}", true))) {
            if (($site_url = FileService::getFileUrl($filename, 'local'))) {
                $this->success('文件上传成功',sysconf('applet_url').$site_url);
            }
        }
        $this->error('文件上传失败');
    }
}