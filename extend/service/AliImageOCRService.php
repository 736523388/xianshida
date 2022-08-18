<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/12/25
 * Time: 9:43
 */

namespace service;

/**
 * 阿里图像OCR身份证识别
 * Class AliImageOCRService
 * @package service
 */
class AliImageOCRService
{
    private static $AppKey='25461602';
    private static $AppSecret='89a56ff558b1908757b1ab63e8ea9888';
    private static $AppCode='3b92d3c21d05469db62715c64cc49e0c';
    private static $url='https://ocridcard.market.alicloudapi.com/idimages';

    /**
     * 身份证识别
     * @param string $image 图片 URL/BASE64
     * @param string $idCardSide
     * @return mixed
     */
    static function query($image='',$idCardSide='front'){
        $headers = [];
        array_push($headers, "Authorization:APPCODE " . self::$AppCode);
        $bodys = 'image='.$image.'&idCardSide='.$idCardSide; //图片 + 正反面参数 默认正面，背面请传back
        $ret=HttpService::post(self::$url,$bodys,['header'=>$headers]);
        return json_decode($ret,true);
    }
}