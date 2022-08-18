<?php
/**
 * Created by PhpStorm.
 * User: jungshen
 * Date: 2018/12/11
 * Time: 14:18
 */

return [
    /**
     * 退货收货信息
     */
    'back_order_info'=>[
        'name'=>'jungshen',
        'phone'=>'023-68575858',
        'province'=>'重庆市',
        'city'=>'重庆市',
        'district'=>'九龙坡区',
        'address'=>'退货详细地址'
    ],
    /**
     * 退货原因
     */
    'back_order_reason'=>[
        '我不想买了',
        '拍错了重新拍',
        '我有更好的选择了',
    ],
    /**
     * 订单自动完成
     */
    'order_finish'=>[
        'is_open'=> true,
        'wait_day'=>7
    ],
    'order_receive_day'=>1,//订单自动收货天数 0为不自动收货
    'cancel_order_second'=>24*60*60,//自动取消订单时间 0为不自动取消 当前24小时
    'bargain_word'=>[
        'less'=>[
            'price'=>1,
            'data'=>[
                '啊,砍价姿势有点歪',
                '看来老夫宝刀已老',
                '我还是回家再练练刀法吧',
                '如果能重来,我要砍得嗨',
                '不好意思,下次多用点力'
            ]
        ],
        'middle'=>[
            'price'=>10,
            'data'=>[
                '感情厚,砍到够',
                '看来我宝刀未老'
            ]
        ],
        'much'=>[
            'data'=>[
                '看我青龙偃月刀',
                '我已使出洪荒之力'
            ]
        ],
        'self'=>[
            '来一起砍价0元拿'
        ]
    ],

];