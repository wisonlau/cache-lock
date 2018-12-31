<?php
// +----------------------------------------------------------------------
// | cache lock [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018-2018 http://wisonlau.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: wisonlau <122022066@qq.com>
// +----------------------------------------------------------------------
namespace Cache\Until\Lock;

class MemcachedAdapter implements CacheInterface
{
	/**
	 * 加锁时间
	 * @var
	 */
	private $lockTime;

	/**
	 * 锁名称
	 * @var string
	 */
	public $name;

	/**
	 * 是否已加锁
	 * @var bool
	 */
	protected $isLocked = false;

	/**
	 * 操作对象
	 * @var \Redis
	 */
	public $handler;

	/**
	 * 参数
	 * @var array
	 */
	public $params;

	/**
	 * 等待锁超时时间，单位：毫秒，0为不限制
	 * @var int
	 */
	public $waitTimeout;

	/**
	 * 获得锁每次尝试间隔，单位：毫秒
	 * @var int
	 */
	public $waitSleepTime;

	/**
	 * 锁超时时间，单位：秒
	 * @var int
	 */
	public $lockExpire;

	/**
	 * 构造方法
	 * @param string $name 锁名称
	 * @param array $params 连接参数
	 * @param integer $waitTimeout 获得锁等待超时时间，单位：毫秒，0为不限制
	 * @param integer $waitSleepTime 获得锁每次尝试间隔，单位：毫秒
	 * @param integer $lockExpire 锁超时时间，单位：秒
	 */
	public function __construct($name, $params = array(), $waitTimeout = 0, $waitSleepTime = 1, $lockExpire = 0)
	{
		if ( ! class_exists('\Memcached'))
		{
			echo '未找到 Memcached 扩展' . PHP_EOL;
			exit();
		}
		$this->name = $name;
		$this->waitTimeout = $waitTimeout;
		$this->waitSleepTime = $waitSleepTime;
		$this->lockExpire = $lockExpire;
		if ($params instanceof \Memcached)
		{
			$this->handler = $params;
		}
		else
		{
			$host = isset($params['host']) ? $params['host'] : '127.0.0.1';
			$port = isset($params['port']) ? $params['port'] : 11211;
			$this->handler = new \Memcached;
			$this->handler->addServer($host, $port);
			$this->handler->setOption(\Memcached::OPT_BINARY_PROTOCOL, false);
			if ( ! empty($params['options']))
			{
				$this->handler->setOptions($params['options']);
			}
			if ( ! $this->handler->getStats())
			{
				echo 'Memcache连接失败' . PHP_EOL;
				exit();
			}
			// 密码验证
			if (isset($params['username'], $params['password']) && ! $this->handler->setSaslAuthData($params['username'], $params['password']))
			{
				echo 'Memcache用户名密码验证失败' . PHP_EOL;
				exit();
			}
		}
	}

	/**
	 * 加锁(非阻塞)
	 * @return bool
	 */
	public function lock()
	{
		if ($this->isLocked)
		{
			echo '已经加锁' . PHP_EOL;
			exit();
		}

		$lock = $this->handler->get($this->name);
		if ($lock)
		{
			$this->isLocked = true;
			echo '已经加锁' . PHP_EOL;
			exit();
		}
		$this->lockTime = time();
		$this->isLocked = true;
		if ($this->lockExpire)
		{
			$status = $this->handler->add($this->name, $this->lockTime, $this->lockExpire);
		}
		else
		{
			$status = $this->handler->add($this->name, $this->lockTime);
		}

		return $status;
	}

	/**
	 * 加锁(阻塞)
	 * @return bool
	 */
	public function lockLock()
	{
		$time = microtime(true);
		$sleepTime = $this->waitSleepTime * 1000;
		$waitTimeout = $this->waitTimeout / 1000;
		while (true)
		{
			$lock = $this->handler->get($this->name);
			if ( ! $lock)
			{
				$this->isLocked = true;
				$this->lockTime = time();
				if ($this->lockExpire)
				{
					$status = $this->handler->add($this->name, $this->lockTime, $this->lockExpire);
				}
				else
				{
					$status = $this->handler->add($this->name, $this->lockTime);
				}
				return $status;
			}

			if (0 === $this->waitTimeout || microtime(true) - $time < $waitTimeout)
			{
				usleep($sleepTime);
			}
			else
			{
				break;
			}
		}

		return false;
	}

	/**
	 * 释放锁
	 * @return bool
	 */
	public function unlock()
	{
		$lock = $this->handler->get($this->name);
		if ($lock)
		{
			$this->isLocked = false;
			return $this->handler->delete($this->name);
		}

		return true;
	}

	/**
	 * 获取加锁时间
	 * @return mixed
	 */
	public function getLockTime()
	{
		if ($this->lockTime)
		{
			return $this->lockTime;
		}
		else
		{
			return $this->handler->get($this->name);
		}
	}

	/**
	 * 设置加锁值
	 * @param $value
	 * @return mixed
	 */
	public function setLockValue($value)
	{
		$this->lockTime = $value;
		return $this->lockTime;
	}

	/**
	 * 是否已加锁
	 * @return boolean
	 */
	public function isLocked()
	{
		return $this->isLocked;
	}

	/**
	 * 关闭锁对象
	 * @return bool
	 */
	public function closeHandler()
	{
		if ($this->isLocked)
		{
			$result = $this->unlock();
			$this->isLocked = false;
		}
		else
		{
			$result = true;
		}

		if (null !== $this->handler)
		{
			$result = $this->handler->quit();
			$this->handler = null;
			return $result;
		}

		return true;
	}

	public function __destruct()
	{
		$this->closeHandler();
	}
}
