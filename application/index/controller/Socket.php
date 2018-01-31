<?php
namespace app\index\controller;
use app\index\im\Server;
use app\index\im\Redis;
use app\index\im\Predis;
use think\Cache;

class Socket extends Server
{
    // 监听所有地址
    protected $host = '0.0.0.0';
    // 监听 9501 端口
    protected $port = 9501;          
    // 指定 socket 的websocket
    protected $serverType = 'socket';
    // 配置项
    protected $option = [
        'reactor_num' => 1,
        // 工作进程
        'worker_num' => 1,
        // 守护进程化
        'daemonize'  => false,
        // 监听队列的长度
        'backlog'    => 128,
        // 异步任务
        'task_worker_num'  => 2,
        // 防止 PHP 内存溢出
        'task_max_request' => 0,   
        'dispatch_mode' => 2,
        'debug_mode' => 1,
        'ssl_cert_file' => "/home/tp5_swoole/public/ssl/1_im.izxin.com_bundle.crt",
        'ssl_key_file'  => "/home/tp5_swoole/public/ssl/2_im.izxin.com.key",
    ];
    // 指定 接听方法
    protected $onFunction = ['Task','Finish','WorkerStart'];
    /** 
     * @Author: whero 
     * @Date: 2018-01-20 19:22:43 
     * @Desc:  用来创建redis长连接
     */
    public function onWorkerStart(\swoole_websocket_server $server, $worker_id)
    {
        $server->redis = Redis::init();
        $server->predis = Predis::init();
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:16:24 
     * @Desc:  连接并握手回调函数
     */    
    public function onOpen(\swoole_websocket_server $server, $request)
    {      
        // 聊天室添加成员
        // $server->redis->sadd('cwlive'.$request->get['room_id'],$request->fd);
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
        $data['fd'] = $frame->fd;
        // 聊天室添加成员
        !$server->redis->sIsMember($data['room_id'],$data['fd']) and $server->redis->sadd($data['room_id'],$data['fd']);
        // 投递给 异步task完成
        $server->task($data);
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-18 20:37:51 
     * @Desc: 异步任务
     */    
    public function onTask(\swoole_websocket_server $server, $task_id, $worker_id, $data){
        // 数据处理
        $sendData = $this->pushMessageData($server,$data);
        $jsonData = $this->jsonData($sendData);
        // 发送给房间的所有人
        foreach ($server->redis->smembers($data['room_id']) as $roomfd) {
            $server->exist($roomfd) and $server->push($roomfd,$jsonData) or $server->redis->srem($data['room_id'],$roomfd);
        }
        $data['data'] = $sendData;
        // 通知异步任务回调函数
        $server->finish($data);
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-18 20:38:38 
     * @Desc:  异步任务回调函数
     */    
    public function onFinish(\swoole_websocket_server $server, $task_id, $data ){
        // 存信息
        if(trim($data['type']) == "message"){
            $this->addMessage($server,$data);         
        }      
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-17 22:24:25 
     * @Desc:  退出回调函数
     */    
    public function onClose(\swoole_websocket_server $server, $fd)
    {
        // \think\Log::info("swoole_websocket_server".$fd);
        if($server->redis->exists('fd'.$fd)){
            $clinet = json_decode($server->redis->get('fd'.$fd),true);
            // 删除房间 成员
            $server->redis->srem($clinet['room_id'],$fd);
            // 发送给房间的所有人
            $this->pushMessage($server,['type'=>'logout','fd'=>$fd,'room_id'=>$clinet['room_id']]);
            // 删除成员
            $server->redis->delete('fd'.$fd);
        }
        $server->close($fd);
    }
    /**
     * [pushMessage 发送信息]
     * @Author   kong|<iwhero@yeah.com>
     * @DateTime 2018-01-26
     * @param    [type]                 $server [description]
     * @param    [type]                 $data   [description]
     * @return   [type]                         [description]
     */
    private function pushMessage($server,$data)
    {
        // 数据处理
        $sendData = $this->pushMessageData($server,$data);
        $jsonData = $this->jsonData($sendData);
        // 发送给房间的所有人
        foreach ($server->redis->smembers($data['room_id']) as $roomfd) {
             $server->exist($roomfd) and $server->push($roomfd,$jsonData) or $server->redis->srem($data['room_id'],$roomfd);
        }
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-24 20:49:32 
     * @Desc:  添加会员发送的信息
     */    
    private function addMessage($server,$data)
    {
        $clinet = json_decode($server->redis->get('fd'.$data['fd']),true);
        $userInfo = Cache::get('user'.$clinet['mobile']);
        $message = $data['data'];
        $message["id"] = isset($userInfo['id'])?$userInfo['id']:time();
        $server->predis->lpush('cwlive'.$data['room_id'],json_encode($message));
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
       if(trim($data['type']) == "login"){
            $res = $this->getUserInfo($server,$data['fd'],$data);
            $res["message"] = '欢迎'.$res['username'].'进入房间';
        }
        if(trim($data['type']) == "logout"){
            $res = $this->getUserInfo($server,$data['fd']);
            $res["message"] = $res['username'].'退出房间';
        }
        if(trim($data['type']) == "message"){
            $res = $this->getUserInfo($server,$data['fd']);  
            $res['message'] = htmlspecialchars($data['message']);   
        }
        $res['type'] = $data['type'];
        $res['datetime'] = date('Y-m-d H:i:s');
        return $res;
    }
    /** 
     * @Author: whero 
     * @Date: 2018-01-24 21:05:35 
     * @Desc:  获取用户信息
     */    
    private function getUserInfo($fd,$data=array())
    {
        $avatars = [
            'http://e.hiphotos.baidu.com/image/h%3D200/sign=08f4485d56df8db1a32e7b643922dddb/1ad5ad6eddc451dad55f452ebefd5266d116324d.jpg',
            'http://tva3.sinaimg.cn/crop.0.0.746.746.50/a157f83bjw8f5rr5twb5aj20kq0kqmy4.jpg',
            'http://www.ld12.com/upimg358/allimg/c150627/14353W345a130-Q2B.jpg',
            'http://www.qq1234.org/uploads/allimg/150121/3_150121144650_12.jpg',
            'http://tva1.sinaimg.cn/crop.4.4.201.201.50/9cae7fd3jw8f73p4sxfnnj205q05qweq.jpg',
            'http://tva1.sinaimg.cn/crop.0.0.749.749.50/ac593e95jw8f90ixlhjdtj20ku0kt0te.jpg',
            'http://tva4.sinaimg.cn/crop.0.0.674.674.50/66f802f9jw8ehttivp5uwj20iq0iqdh3.jpg',
            'http://tva4.sinaimg.cn/crop.0.0.1242.1242.50/6687272ejw8f90yx5n1wxj20yi0yigqp.jpg',
            'http://tva2.sinaimg.cn/crop.0.0.996.996.50/6c351711jw8f75bqc32hsj20ro0roac4.jpg',
            'http://tva2.sinaimg.cn/crop.0.0.180.180.50/6aba55c9jw1e8qgp5bmzyj2050050aa8.jpg'
        ];
        // 将会员的fd和room_id存入缓存  用来退出聊天时用
        if(!$server->redis->exists('fd'.$fd)){
            $avatars_pic = $avatars[array_rand($avatars)];
            $userInfo = Cache::get('user'.$data['mobile']);
            $res['username'] = isset($userInfo['name']) ? $userInfo['name'] : "匿名用户";
            $res['avatar'] = isset($userInfo['head_pic_path']) ? $userInfo['head_pic_path'] : $avatars[array_rand($avatars)]; 
            $server->redis->set('fd'.$fd,json_encode(['mobile'=>$data['mobile'],'fd'=>$fd,'room_id'=>$data['room_id'],'avatar'=>$avatars_pic,'username'=>$res['username']]));
        }
        if($server->redis->exists('fd'.$fd)){
            $clinet = json_decode($server->redis->get('fd'.$fd),true);
            $res['username'] = $clinet['username'];
            $res['avatar'] = $clinet['avatar']; 
        }
        return $res;
    }
}