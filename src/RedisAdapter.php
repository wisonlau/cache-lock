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

class RedisAdapter implements CacheInterface
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
	 * Redis操作对象
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
	 * @param integer $lockExpire 锁超时时间，单位：秒 0为不限制
	 */
	public function __construct($name, $params = array(), $waitTimeout = 0, $waitSleepTime = 1, $lockExpire = 0)
	{
		if ( ! class_exists('\Redis'))
		{
			echo '未找到 Redis 扩展' . PHP_EOL;
			exit();
		}
		$this->name = $name;
		$this->waitTimeout = $waitTimeout;
		$this->waitSleepTime = $waitSleepTime;
		$this->lockExpire = $lockExpire;
		if ($params instanceof \Redis)
		{
			$this->handler = $params;
		}
		else
		{
			$host = isset($params['host']) ? $params['host'] : '127.0.0.1';
			$port = isset($params['port']) ? $params['port'] : 6379;
			$timeout = isset($params['timeout']) ? $params['timeout'] : 0;
			$pconnect = isset($params['pconnect']) ? $params['pconnect'] : false;
			$this->handler = new \Redis;
			if ($pconnect)
			{
				$result = $this->handler->pconnect($host, $port, $timeout);
			}
			else
			{
				$result = $this->handler->connect($host, $port, $timeout);
			}
			if ( ! $result)
			{
				echo 'Redis连接失败' . PHP_EOL;
				exit();
			}
			// 密码验证
			if (isset($params['password']) && ! $this->handler->auth($params['password']))
			{
				echo 'Redis密码验证失败' . PHP_EOL;
				exit();
			}
			// 选择库
			if (isset($params['select']))
			{
				$this->handler->select($params['select']);
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
		$status = $this->handler->setnx($this->name, $this->lockTime);
		if ($status && $this->lockExpire)
		{
			$this->isLocked = true;
			$this->handler->expire($this->name, $this->lockExpire);
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
				$this->lockTime = time();
				$status = $this->handler->setnx($this->name, $this->lockTime);
				if ($status && $this->lockExpire)
				{
					$this->isLocked = true;
					$this->handler->expire($this->name, $this->lockExpire);
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
	 * @param int $rob_total 库存
	 * @param string $data
	 * @return false|string
	 */
	public function shopLock($rob_total = 100, $data = '')
	{
		$this->handler->watch($this->name);
		$len = $this->handler->hlen($this->name);
		if ($len < $rob_total)
		{
			$this->handler->multi();
			$this->handler->hSet($this->name, $data, time());
			$rob_result = $this->handler->exec();
			if ($rob_result)
			{
				$this->isLocked = true;
				$result = $this->handler->hGetAll($this->name);
				return json_encode(array('ret' => 1, 'msg' => '', 'content' => $result));
			}
			else
			{
				return json_encode(array('ret' => 0, 'msg' => 'recover'));
			}
		}
		else
		{
			return json_encode(array('ret' => 0, 'msg' => 'empty'));
		}
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
			return $this->handler->del($this->name);
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
			$result = $this->handler->close();
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