<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);


/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose
 */

//require_once __DIR__ . '/../../vendor/autoload.php';
use \GatewayWorker\Lib\Gateway;

class Events
{

    public static $db = null;
    public static $redis = null;


    public static function onWorkerStart($worker)
    {
        self::$db = new Workerman\MySQL\Connection('127.0.0.1', '3306', 'root', '', 'whychat');
        self::$redis = new Redis();
        self::$redis->connect('127.0.0.1', 6379);
        echo "sss";

    }

    /**
     * 有消息时
     * @param int $client_id
     * @param mixed $message
     */
    public static function onMessage($client_id, $message)
    {
        // debug

        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:" . json_encode($_SESSION) . " onMessage:" . $message . "\n";
//        echo "qqqqqq";

        // 客户端传递的是json数据
        $message_data = json_decode($message, true);
        if (!$message_data) {
            return;
        }
        //如果uid没有设置且type不为login则断开连接
        //要接收ype为非login必须是已经设置session['uid']的连接
        if (!isset($_SESSION['uid']) && $message_data['type'] != 'login') {
            return Gateway::closeClient($client_id);
        }
        // 根据类型执行不同的业务
        switch ($message_data['type']) {
            case 'login':
                // $message = {"type":"login","uid":"xxxxx"}
                if ($message_data['token'] != self::$redis->get($message_data['uid'])) {
                    return Gateway::closeClient($client_id);
                }
                $_SESSION['uid'] = $message_data['uid'];
                Gateway::bindUid($client_id, $message_data['uid']);

//            $data = array('type'=>'login,'uid'=)

                echo $_SESSION['uid'] . "bind success";
                return;
            case 'searchfri':
                $searchname = $message_data['searchname'];
                $from = $message_data['from'];

                $data = self::$db->select('*')->from('whyuser')->where('idWhyuser= :idWhyuser')->bindValues(array('idWhyuser' => $searchname))->row();
                if ($data) {
                    $data['issuccess'] = true;

                } else {
                    $data['issuccess'] = false;
                    $data['err'] = '无此用户，请输入正确的y号';
                }
                $data['type'] = 'searchuserinfo';

                Gateway::sendToUid($from, json_encode($data));

                return;

            /*
             *好友添加流程。添加好友的人给服务器发一条'addfri'消息。服务器通知对方有人请求添加为好友。对方返回一条'confirmfrid'消息,恢复拒绝还是同意好友。
             *同意则进行数据库操作。
             */
            //添加好友
            case 'addfri':

                $from = $message_data['fromid'];
                $to = $message_data['toid'];
                $verifycontent = $message_data['verifycontent'];


                $resdata['type'] = 'addfriresponse';

                //用户不存在或不在线
                if (!sizeof(Gateway::getClientIdByUid($to))) {


                    $resdata['issuccess'] = false;
                    $resdata['info'] = '用户不存在或不在线';

                    Gateway::sendToUid($from, json_encode($resdata));

                    return;

                }
                $resdata['issuccess'] = true;
                $resdata['info'] = '已经成功发送请求';
                Gateway::sendToUid($from, json_encode($resdata));

                $data['type'] = 'makefri';
                $data['verifycontent'] = $verifycontent;
                $data['from']=$from;
                Gateway::sendToUid($to, json_encode($data));

                return;

            case 'confirmfrid':

                $from = $message_data['fromid'];
                $to = $message_data['toid'];


                if ($message_data['confirm']) {
                    //同意，进行数据库操作

                    $insert_id = self::$db->insert('whyuser_has_whyuser;')->cols(array(
                        'Whos_id' => $from,
                        'Whos_Fri_id' => $to,
                        'notename' => $to))->query();

                    $insert_id = self::$db->insert('whyuser_has_whyuser;')->cols(array(
                        'Whos_id' => $to,
                        'Whos_Fri_id' => $from,
                        'notename' => $to))->query();

                    $frommessage_data = self::$db->select('*')->from('whyuser;')->where('idWhyUser=' . $from)->query();

                    $tomessage_data = self::$db->select('*')->from('whyuser;')->where('idWhyUser=' . $to)->query();

                    $frommessage_data['type'] = 'befrid';
                    $frommessage_data['frisid'] = $from;


                    $tomessage_data['type'] = 'befrid';
                    $tomessage_data['frisid'] = $to;

                    Gateway::sendToUid($to, json_encode($frommessage_data));
                    Gateway::sendToUid($from, json_encode($tomessage_data));

                } else {
                    //拒绝

                    $refuse_message = array('type' => 'refused',
                        'from' => $from
                    );
                    Gateway::sendToUid($to, json_encode($refuse_message));

                }

                return;

            case 'secrettalk':
                //单聊消息发送流程。客户端给服务器发一条'secrettalk'消息。

                $from = $message_data['fromid'];
                $to = $message_data['toid'];

                Gateway::sendToUid($to, json_encode($message_data));

                return;

            case 'grouptalk':
                //群聊聊消息发送流程。客户端给服务器发一条'grouptalk'消息。

                $from = $message_data['fromid'];
                $to = $message_data['toid'];

                Gateway::sendToGroup($to, json_encode($message_data));

                return;

            case 'buildgroup':
                //创建群聊流程。客户端给服务器发一条'buildgroup'消息。


                return;

            case 'talktogether':
                //拉好友进群聊流程。客户端给服务器发一条'talktogether'消息。要进的群聊，要拉的人。

                return;

            // 客户端回应服务端的心跳
            case 'pong':
                return;
        }
    }

    /**
     * 当客户端断开连接时
     * @param integer $client_id 客户端id
     */
    public static function onClose($client_id)
    {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";

        // 从房间的客户端列表中删除
        // if(isset($_SESSION['room_id']))
        // {
        //     $room_id = $_SESSION['room_id'];
        //     $new_message = array('type'=>'logout', 'from_client_id'=>$client_id, 'from_client_name'=>$_SESSION['client_name'], 'time'=>date('Y-m-d H:i:s'));
        //     Gateway::sendToGroup($room_id, json_encode($new_message));
        // }
    }

}
