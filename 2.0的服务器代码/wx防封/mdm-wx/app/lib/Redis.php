<?php


namespace app\lib;

use think\facade\Env;

class Redis
{
    protected $options = [
        'host' => '127.0.0.1',
        'port' => '6379',
        'password' => 'UEOytMV8VEqZD0yP',
        'select' => 1,
        'timeout' => 0,
        'expire' => 0,
        'persistent' => false,
        'prefix' => '',
    ];


    protected $redis;

    public function __construct($options = [])
    {

        if (!extension_loaded('redis')) {
            throw new \BadFunctionCallException('not support: redis');
        }
//        $this->options=[
//            'host' => Env::get("redis.host","127.0.0.1"),
//            'port' => Env::get("redis.port","6379"),
//            'password' => Env::get("redis.pw",''),
//            'select' => Env::get("redis.select","1"),
//            'timeout' => 0,
//            'expire' => 0,
//            'persistent' => false,
//            'prefix' => '',
//        ];
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }
        $this->redis = new \Redis();
        if ($this->options['persistent']) {
            $this->redis->pconnect($this->options['host'], $this->options['port'], $this->options['timeout'], 'persistent_id_' . $this->options['select']);

        } else {
            $this->redis->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
        }
        if ('' != $this->options['password']) {
            $this->redis->auth($this->options['password']);
        }
        if (0 != $this->options['select']) {
            $this->redis->select($this->options['select']);
        }
    }

    /**
     * 清空整个redis服务器
     */
    public function clearAll()
    {
        $this->redis->flushAll();
    }

    /**
     * 删除集合
     * @param string $key
     * @return int
     */
    public function delete(string $key)
    {
        $result = $this->redis->del($key);
        return $result;
    }

    /**
     * 返回句柄
     * @return \Redis
     */
    public function handle()
    {
        return $this->redis;
    }

    /**
     * 检查给定的KEY是否存在
     * @param string $key
     * @return bool
     */
    public function exists(string $key)
    {
        return $this->redis->exists($key);
    }

    /**
     * 设置过期时间
     * @param string $key
     * @param int $timeout
     * @return bool
     */
    public function expire(string $key, int $timeout)
    {
        return $this->redis->expire($key, $timeout);
    }

    /**
     * 查找所有符合给定模式 pattern 的 key
     * @param string $pattern
     * @return array
     */
    public function keys(string $pattern)
    {
        return $this->redis->keys($pattern);
    }

    /**
     * 将key移动到指定的库
     * @param string $key
     * @param int $select
     * @return bool
     */
    public function move(string $key, int $select)
    {
        return $this->redis->move($key, $select);
    }

    /**
     * 设置缓存
     * @param $key
     * @param $val
     * @param int $time
     * @param array $config
     */
    public static function set($key, $val, $time = 180, $config = [])
    {
        $self = new self($config);
        $self->handle()->set($key, $val, $time);
        $self->handle()->close();
    }

    /**
     * 删除
     * @param $key
     * @param array $config
     */
    public static function del($key, $config = [])
    {
        $self = new self($config);
        $self->handle()->del($key);
        $self->handle()->close();
    }

    /**
     * 获取缓存
     * @param $key
     * @param array $config
     */
    public static function get($key, $config = [])
    {
        $self = new self($config);
        $result = $self->handle()->get($key);
        $self->handle()->close();
        return $result;
    }


}
