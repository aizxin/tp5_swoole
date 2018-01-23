<?php
namespace app\index\controller;
use app\index\im\Redis;

class Index
{
    public function index()
    {
        $redis = Redis::init();
        
        var_dump($redis->keys("*"));
    }
}
