<?php
namespace app\index\im;

use think\Config;

/** 
 * @Author: whero 
 * @Date: 2018-01-22 20:12:35 
 * @Desc: redis操作类
 */
class Redis
{
    /**
     * @var array 缓存的实例
     */
    public static $instance;
    /**
     * @var object 操作句柄
     */
    public static $handler;

    /**
     * redis连接
     * @param array $options 缓存参数
     * @access public
     */
    public static function connect($option = [])
    {
        $options = [
            'host'       => '127.0.0.1',
            'port'       => 6379,
            'password'   => '',
            'select'     => 0,
            'timeout'    => 0,
            'expire'     => 0,
            'persistent' => false,
            'prefix'     => '',
        ];
        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
        if (!empty($option)) {
            $options = array_merge($options, $option);
        }
        if (is_null(self::$instance)) {
            $redis = new \Redis;
            if ($options['persistent']) {
                $redis->pconnect($options['host'], $options['port'], $options['timeout'], 'persistent_id_' . $options['select']);
            } else {
                $redis->connect($options['host'], $options['port'], $options['timeout']);
            }
            if ('' != $options['password']) {
                $redis->auth($options['password']);
            }
            if (0 != $options['select']) {
                $redis->select($options['select']);
            }
            self::$instance = $redis;
            return $redis;
        }
        return self::$instance;
    }
    /**
     * 自动初始化缓存
     * @access public
     * @param  array $options 配置数组
     * @return Redis
     */
    public static function init(array $options = [])
    {
        if (is_null(self::$handler)) {
            if (empty($options) && 'complex' == Config::get('cache.type')) {
                $default = Config::get('cache.default');
                // 获取默认缓存配置，并连接
                $options = Config::get('cache.' . $default['type']) ?: $default;
            } elseif (empty($options)) {
                $options = Config::get('cache');
            }
            self::$handler = self::connect($options);
        }
        return self::$handler;
    }

}
