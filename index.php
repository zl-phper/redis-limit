<?php
/**
 * Created by PhpStorm.  基于redis zset 结构实现（目前只是实现了限流） 只是适合不是很大频次的限流 比如 一分钟 10w 次 不适合用
 * User: zl
 * Date: 2019/4/10
 * Time: 9:45
 */

namespace App\Common;


class Limit
{
    /**
     * 缓存redis
     * @var resource
     */
    private $redis;

    /**
     *  锁定的key
     * @var array
     */
    private $lockKeyArr = [];

    /**
     * 初始化数据
     * Limit constructor.
     */
    public function __construct()
    {
        $this->shutdown();
        $this->initRedis();
    }

    /**
     *  限流
     * @link  https://juejin.im/book/5afc2e5f6fb9a07a9b362527/section/5b4477416fb9a04fa259c496
     * @param $key
     * @param $period
     * @param $maxCount
     * @return bool
     */
    public function isActionAllowed($key, $period, $maxCount)
    {
        $key = 'actionAllowed:' . $key;

        $msecTime = $this->getMsecTime();

        $this->redis->multi();
        $this->redis->zadd($key, $msecTime, $msecTime);
        $this->redis->zremrangebyscore($key, 0, $msecTime - $period * 1000);
        $this->redis->expire($key, $period + 1);
        $this->redis->exec();

        $count = $this->redis->zcard($key);

        return $count <= $maxCount;
    }

    /**
     * 初始化redis
     * @return void
     */
    public function initRedis()
    {
        $this->redis = \qredis::instance();
    }

    /**
     * 注册错误
     */
    private function shutdown()
    {
        register_shutdown_function(function () {
            $this->forcedShutdown();
        });
    }

    /**
     * 强制关闭 删除锁定key
     *
     */
    private function forcedShutdown()
    {
        if (isset($this->lockKeyArr)) {

            array_walk($this->lockKeyArr, function ($data) {
                $this->redis->del($data);
            });

            unset($this->locks);
        }
    }

    /**
     * 获取当前毫秒时间戳
     * @return float
     */
    public function getMsecTime()
    {
        list($msec, $sec) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    }

    /**
     * 解除一个key
     * @param $key
     */
    public function delKey($key)
    {
        $this->redis->del($key);
    }

}
