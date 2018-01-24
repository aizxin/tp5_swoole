<?php
namespace app\index\controller;
use app\index\im\Redis;

class Index
{
    public function index()
    {
        $redis = Redis::init();
        // $redis->flushall();
        var_dump($redis->lgetrange("cwlivemessage111ddva",0,20));
        var_dump($redis->keys("*"));
    }
}
