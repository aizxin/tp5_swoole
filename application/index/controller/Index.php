<?php
namespace app\index\controller;
use app\index\im\Server;

class Index extends Server
{
    // 监听所有地址
    protected $host = '0.0.0.0';
    // 监听 9501 端口
    protected $port = 9501;
    // 指定运行模式为多进程
    // protected $mode = SWOOLE_PROCESS;
    // 指定 socket 的类型为 ipv4 的 tcp socket
    // protected $sockType = SWOOLE_SOCK_TCP;
    // 指定 socket 的websocket
    protected $serverType = 'socket';
    // 配置项
    protected $option = [
        /** 
         *  设置启动的worker进程数
         *  业务代码是全异步非阻塞的，这里设置为CPU的1-4倍最合理
         *  业务代码为同步阻塞，需要根据请求响应时间和系统负载来调整
         */
        // 'worker_num' => 4,
        // 守护进程化
        'daemonize'  => true,
        // 监听队列的长度
        'backlog'    => 128,
        // 
        'task_worker_num'  => 8,
        'dispatch_mode' => 2,
        'debug_mode' => 1
    ];
    // 指定 接听方法
    protected $onFunction = ['Task','Finish'];
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:16:24 
     * @Desc:  连接并握手回调函数
     */    
    public function onOpen(\swoole_websocket_server $server, $request)
    {
        // $server->tick(1000, function() use ($server, $request) {
        //     \think\Log::info("server#{$server->worker_pid}: handshake success with fd#{$request->fd}\n");
        // });
        echo "server#{$server->worker_pid}: handshake success with fd#{$request->fd}\n";
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:22:24 
     * @Desc: 服务器收到来自客户端的信息回调函数 
     */    
    public function onMessage(\swoole_websocket_server $server, $frame)
    {
        $server->push($frame->fd, "this is server");
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:24:25 
     * @Desc:  退出回调函数
     */    
    public function onClose(\swoole_websocket_server $server, $fd)
    {
        \think\Log::info("server#{$server->worker_pid}: handshake success with fd#{$fd}\n");
        echo "client {$fd} closed\n";
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-18 20:37:51 
     * @Desc: 异步任务
     */    
    public function onTask($server, $task_id, $from_id, $data){

    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-18 20:38:38 
     * @Desc:  
     */    
    public function onFinish($server, $task_id, $data ){

	}
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:11:18 
     * @Desc:  认证
     */    
    private function auth($request)
    {
        
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:15:41 
     * @Desc: 发送消息 
     */    
    private function pushMessage(\swoole_websocket_server $server, $message, $messageType, $frameId)
    {
        
    }
}
