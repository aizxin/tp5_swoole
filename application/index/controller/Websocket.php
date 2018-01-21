<?php
namespace app\index\controller;
use app\index\im\Server;

class Websocket extends Server
{
    // 监听所有地址
    protected $host = '0.0.0.0';
    // 监听 9501 端口
    protected $port = 9501;          
    // 指定 socket 的websocket
    protected $serverType = 'socket';
    // 配置项
    protected $option = [
        'reactor_num' => 2,
        // 工作进程
	    'worker_num' => 2,
        // 守护进程化
        'daemonize'  => true,
        // 监听队列的长度
        'backlog'    => 128,
        // 异步任务
        'task_worker_num'  => 8,
        // 防止 PHP 内存溢出
        'task_max_request' => 0,
        'dispatch_mode' => 2,
		'debug_mode' => 1,
    ];
    // 指定 接听方法
    protected $onFunction = ['Task','Finish','WorkerStart'];
    // redis
    protected $redis = null;
    /** 
     * @Author: whero 
     * @Date: 2018-01-20 19:22:43 
     * @Desc:  用来创建redis长连接
     */
    public function onWorkerStart(\swoole_websocket_server $server, $worker_id)
    {
        if ($this->redis == null) {
            $redis = new \Redis();
            $redis->pconnect("127.0.0.1", 6379);
            // $redis->auth();
            // $redis->select(3);
            $server->redis = $redis;
        }
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:16:24 
     * @Desc:  连接并握手回调函数
     */    
    public function onOpen(\swoole_websocket_server $server, $request)
    {      
        // 聊天室添加成员
        $server->redis->sadd('cwlive'.$request->get['room_id'],$request->fd);
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:22:24 
     * @Desc: 服务器收到来自客户端的信息回调函数 
     */    
    public function onMessage(\swoole_websocket_server $server, $frame)
    {
        $data = json_decode($frame->data,true);
        // 将客户端的socket id 再私聊用
        $data['user']['fd'] = $frame->fd;
        // 投递给 异步task完成
        $server->task($data);
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:24:25 
     * @Desc:  退出回调函数
     */    
    public function onClose(\swoole_websocket_server $server, $fd)
    {
        if($server->redis->exists('cwlive'.$fd)){
            $user = json_decode($server->redis->get('cwlive'.$fd),true);
            // 删除房间 成员
            $server->redis->srem('cwlive'.$user['room_id'],$fd);
            // 发送给房间的所有人
            foreach ($server->redis->smembers('cwlive'.$user['room_id']) as $roomfd) {
                $server->push($roomfd,$this->jsonData($this->pushMessageData($server,['type'=>'logout','user'=>$user])));
            }
        }
        // 删除成员
        $server->redis->delete('cwlive'.$fd);
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-18 20:37:51 
     * @Desc: 异步任务
     */    
    public function onTask(\swoole_websocket_server $server, $task_id, $worker_id, $data){
        
        // 发送给房间的所有人
        foreach ($server->redis->smembers('cwlive'.$data['user']['room_id']) as $roomfd) {
            // 自己除外
            if($roomfd == $data['user']['fd'] && trim($data['type']) == "message"){
                continue;
            }
            $server->push($roomfd,$this->jsonData($this->pushMessageData($server,$data)));
        }
        // 通知异步任务回调函数
        $server->finish($data);
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-18 20:38:38 
     * @Desc:  异步任务回调函数
     */    
    public function onFinish(\swoole_websocket_server $server, $task_id, $data ){
        if(!$server->redis->exists('cwlive'.$data['user']['fd'])){
            $server->redis->set('cwlive'.$data['user']['fd'],json_encode($data['user']));   
        }
        if(trim($data['type']) == "message"){
            $data['datatime'] = time();
            $server->redis->lpush('cwlivemessage'.$data['user']['room_id'],json_encode($data));            
        }
	}
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:11:18 
     * @Desc:  认证
     */    
    private function auth()
    {
        
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-20 19:26:59 
     * @Desc:  数据返回
     */    
    private function jsonData($data = array(),$code = true)
    {
        return json_encode([
            "code"=>$code,
            'data'=>$data,
            "message"=>$code?'ok':'on'
        ]);
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:15:41 
     * @Desc: 发送消息数据
     */    
    private function pushMessageData($server,$data)
    {
        // switch( trim($data['type']) ){
		// 	case 'login':
		// 		break;
		// 	case 'message':
        //         break;
        //  default:
        //      break;
        // }
        $count = $server->redis->ssize('cwlive'.trim($data["user"]['room_id']));
        trim($data['type']) == "login" and $count += 1;
        trim($data['type']) == "logout" and $data["message"] =  $data['user']['username'].'退出房间';
        $dataData = array(
            'user'=>$data["user"],
            'datetime' => date('Y-m-d H:i:s'),
            "message"=>$data["message"],
            'count'=>$count
        );
        return $dataData;
    }
}
