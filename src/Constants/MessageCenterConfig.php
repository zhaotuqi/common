<?php
/**
 * 消息中心配置
 * User: wangyu
 * Date: 2019/8/20
 * Time: 15:03
 */

namespace App\Constants;


class MessageCenterConfig
{

    /**
     * url消息类型
     *
     * @var integer
     */
    const MSG_URL_TYPE = 1;


    /**
     * 富文本消息类型
     *
     * @var integer
     */
    const MSG_RICH_TEXT_TYPE = 2;


    /**
     * 公告提醒
     *
     * @var integer
     */
    const EVENT_PUBLIC_NOTICE = 100;


    /**
     * 自定义任务提醒
     *
     * @var integer
     */
    const EVENT_CUSTOM_TASK_NOTICE = 101;


    /**
     * 线索分配事件
     *
     * @var integer
     */
    const EVENT_CLUE_ASSIGN_EVENT = 200;


    /**
     * 线索重复报名事件
     *
     * @var integer
     */
    const EVENT_CLUE_REPEAT_SIGNUP_EVNET = 201;


    /**
     * 线索转移事件
     *
     * @var integer
     */
    const EVENT_CLUE_TRANSFER_EVNET = 202;


    /**
     * 测评课排课成功
     *
     * @var integer
     */
    const EVENT_COURSE_TEST_SCHEDULE_SUCCESS = 300;


    /**
     * 测评课排课失败
     *
     * @var integer
     */
    const EVENT_COURSE_TEST_SCHEDULE_FAIL = 301;


    /**
     * 课前提醒
     *
     * @var integer
     */
    const EVENT_COURSE_PRE_CLASS_REMINDER = 302;


    /**
     * 课后提醒
     *
     * @var integer
     */
    const EVENT_COURSE_AFTER_CLASS_REMINDER = 303;


    /**
     * 学管线索详情宏定义
     *
     * @var string
     */
    const MACRO_XG_CLUE_DETAIL = '__XG_CLUE_DETAIL__';


    /**
     * 学管线索列表宏定义
     *
     * @var string
     */
    const MACRO_XG_CLUE_LIST = '__XG_CLUE_LIST__';

    /**
     * 学管学员列表宏定义
     *
     * @var string
     */
    const MACRO_XG_JOIN_CLUE_LIST = '__XG_JOIN_CLUE_LIST__';

    /**
     * CC线索详情宏定义
     *
     * @var string
     */
    const MACRO_CC_CLUE_DETAIL = '__CC_CLUE_DETAIL__';


    /**
     * CC线索列表宏定义
     *
     * @var string
     */
    const MACRO_CC_CLUE_LIST = '__CC_CLUE_LIST__';

}