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
class AliYingYeService
{
    /*private static $AppKey='25461602';
    private static $AppSecret='89a56ff558b1908757b1ab63e8ea9888';*/
    private static $AppCode='1a84aab75d55445fb66ffe863a5aa017';
    private static $url='https://yingye.market.alicloudapi.com/do';

    /**
     * 身份证识别
     * @param string $image 图片 URL/BASE64
     * @return mixed
     */
    static function query($image=''){
        $headers = [];
        array_push($headers, "Authorization:APPCODE " . self::$AppCode);
        array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
        $bodys = 'image='.$image; //图片
        $ret=HttpService::post(self::$url,$bodys,['header'=>$headers]);
        return json_decode($ret,true);
    }
}