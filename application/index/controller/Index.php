<?php
namespace app\index\controller;
use app\index\im\Redis;

class Index
{
    public function index()
    {
        $redis = Redis::init();
        // $redis->flushall();
        $redis->sadd("room_id",1);
        var_dump($redis->keys("*"));
    }
}
