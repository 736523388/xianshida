<?php
namespace app\xueao\validate;

use think\Validate;

class MemberInvitationAward extends Validate
{
    protected $rule = [
        'inviter_level'  =>  'require|unique:store_member_invitation_award,is_deleted&invitee_level',
        'invitee_level' =>  'require|unique:store_member_invitation_award,is_deleted&inviter_level',
        'percentage' => 'require'
    ];

    protected $message = [
        'inviter_level.require'  =>  '请选择邀请人等级',
        'inviter_level.unique'  =>  '规则重复',
        'invitee_level.require' =>  '请选择被邀请人等级',
        'invitee_level.unique'  =>  '规则重复',
        'percentage.require'  =>  '请输入返佣比例'
    ];
}