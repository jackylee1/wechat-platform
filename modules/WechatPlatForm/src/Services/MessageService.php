<?php

/*
 * This file is part of ibrand/wechat-platform.
 *
 * (c) iBrand <https://www.ibrand.cc>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace iBrand\Wechat\Platform\Services;

use EasyWeChat\Kernel\Messages\Card;
use EasyWeChat\Kernel\Messages\Image;
use EasyWeChat\Kernel\Messages\News;
use EasyWeChat\Kernel\Messages\NewsItem;
use EasyWeChat\Kernel\Messages\Text;
use EasyWeChat\Kernel\Messages\Video;
use EasyWeChat\Kernel\Messages\Voice;
use iBrand\Wechat\Platform\Repositories\AuthorizerRepository;

/**
 * 公众平台推送
 * Class MessageService.
 */
class MessageService
{
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
    const PATCH = 'PATCH';
    const DELETE = 'DELETE';

    protected $authorizerRepository;

    protected $platformService;

    public function __construct(
        AuthorizerRepository $authorizerRepository,

        PlatformService $platformService
    ) {
        $this->authorizerRepository = $authorizerRepository;

        $this->platformService = $platformService;
    }

    /**
     * 公众平台事件处理.
     *
     * @param $appid
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \EasyWeChat\Kernel\Exceptions\BadRequestException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function accountEventProcess($appid)
    {
        //授权
        $server = $this->platformService->authorizeAPI($appid);
        //获取回调url
        $url = $this->authorizerRepository->getCallBackUrl($appid);

        $server->server->push(function ($message) use ($appid, $url) {
            \Log::info($message);

            //event事件
            if ('event' == $message['MsgType']) {
                switch ($message['Event']) {
                    //关注事件
                    case 'subscribe':
                        $key = isset($message['EventKey']) ? $message['EventKey'] : '';
                        $ticket = isset($message['Ticket']) ? $message['Ticket'] : '';
                        $params = [
                            'app_id' => $appid,
                            'openid' => $message['FromUserName'],
                            'event_type' => 'subscribe',
                            'key' => $key,
                            'ticket' => $ticket,
                        ];

                        return $this->callBackEvent($url, $params);

                    //取消关注事件
                    case 'unsubscribe':
                        $params = [
                            'app_id' => $appid,
                            'openid' => $message['FromUserName'],
                            'event_type' => 'unsubscribe',
                        ];

                        return $this->callBackEvent($url, $params);

                    //领取会员卡
                    case 'user_get_card':
                        $params = [
                            'app_id' => $appid,
                            'open_id' => $message['FromUserName'],
                            'event_type' => 'user_get_card',
                            'card_id' => $message['CardId'],
                            'code' => $message['UserCardCode'],
                        ];

                        return $this->callBackEvent($url, $params);
                    //删除会员卡
                    case 'user_del_card':
                        $params = [
                            'app_id' => $appid,
                            'open_id' => $message['FromUserName'],
                            'event_type' => 'user_del_card',
                            'card_id' => $message['CardId'],
                            'code' => $message['UserCardCod'],
                        ];

                        return $this->callBackEvent($url, $params);

                    //激活会员卡
                    case 'user_consume_card':
                        $params = [
                            'app_id' => $appid,
                            'open_id' => $message['FromUserName'],
                            'event_type' => 'user_consume_card',
                            'card_id' => $message['CardId'],
                            'code' => $message['UserCardCode'],
                        ];

                        return $this->callBackEvent($url, $params);

                    //查看会员卡
                    case 'user_view_card':
                        $params = [
                            'app_id' => $appid,
                            'open_id' => $message['FromUserName'],
                            'event_type' => 'user_view_card',
                            'card_id' => $message['CardId'],
                            'code' => $message['UserCardCode'],
                        ];

                        return $this->callBackEvent($url, $params);

                    //二维码扫码
                    case 'SCAN':
                        $params = [
                            'app_id' => $appid,
                            'openid' => $message['FromUserName'],
                            'event_type' => 'SCAN',
                            'key' => $message['EventKey'],
                            'ticket' => $message['Ticket'],
                        ];

                        return $this->callBackEvent($url, $params);

                    //点击事件
                    case 'CLICK':
                        $params = [
                            'app_id' => $appid,
                            'open_id' => $message['FromUserName'],
                            'event_type' => 'CLICK',
                            'key' => $message['EventKey'],
                        ];

                        return $this->callBackEvent($url, $params);

                    //全网发布测试：事件
                    case 'LOCATION':
                        return 'LOCATIONfrom_callback';
                }
            }

            //text文本
            if ('text' == $message['MsgType']) {
                //全网发布测试：文本消息
                if ('TESTCOMPONENT_MSG_TYPE_TEXT' == $message['Content']) {
                    return 'TESTCOMPONENT_MSG_TYPE_TEXT_callback';
                }

                $params = [
                    'app_id' => $appid,
                    'open_id' => $message['FromUserName'],
                    'type' => $message['MsgType'],
                    'content' => $message['Content'],
                ];

                $data = $this->BackCurl($url.'/wechat_call_back/message', $method = self::GET, $params);

                return $this->BackMessage($data);
            }

            return '';
        });

        return $server->server->serve();
    }

    /**
     * curl.
     *
     * @param $url
     * @param string $method
     * @param array  $params
     * @param array  $request_header
     *
     * @return mixed
     */
    public function BackCurl($url, $method = self::GET, $params = [], $request_header = [])
    {
        $request_header = ['Content-Type' => 'application/x-www-form-urlencoded'];
        if (self::GET === $method || self::DELETE === $method) {
            $url .= (stripos($url, '?') ? '&' : '?').http_build_query($params);
            $params = [];
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_header);
        if (self::POST === $method) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        $output = curl_exec($ch);
        curl_close($ch);

        return json_decode($output, true);
    }

    /**
     * 消息回复.
     *
     * @param $data
     *
     * @return string
     *
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     */
    public function BackMessage($data)
    {
        if (count($data) > 0) {
            foreach ($data as $k => $item) {
                if (!empty($item) and isset($item['type'])) {
                    //授权
                    $server = $this->platformService->authorizeAPI($item['app_id']);

                    $message = [];

                    switch ($item['type']) {
                        case 'text':
                            $message = new Text($item['content']);
                            break;
                        case 'image':
                            $message = new Image($item['media_id']);
                            break;
                        case 'voice':
                            $message = new Voice($item['media_id']);
                            break;
                        case 'video':
                            $message = new Video($item['media_id'],
                                [
                                    'title' => $item['title'],
                                    'description' => $item['description'],
                                ]);
                            break;
                        case 'article':

                            if (isset($item['article']) and count($item['article']) > 0) {
                                foreach ($item['article'] as $ak => $article_item) {
                                    $items[] =
                                        new NewsItem([
                                            'title' => $article_item['title'],
                                            'description' => $article_item['description'],
                                            'url' => $article_item['url'],
                                            'image' => $article_item['image'],
                                        ]);
                                }
                            }

                            return new News($items);
                            break;

                        case 'card':
                            $message = new Card($item['card_id']);
                            break;
                        // 其它消息
                        default:
                            return '';
                            break;
                    }

                    $server->customer_service->message($message)->to($item['open_id'])->send();
                }
            }
        }

        return '';
    }

    /**
     * 事件处理.
     *
     * @param $url
     * @param $data
     *
     * @return string
     *
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \EasyWeChat\Kernel\Exceptions\RuntimeException
     */
    public function callBackEvent($url, $data)
    {
        $data = $this->BackCurl($url.'/wechat_call_back/event', $method = self::GET, $data);
        \Log::info($data);

        return $this->BackMessage($data);
    }
}