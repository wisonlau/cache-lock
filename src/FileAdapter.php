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

class FileAdapter implements CacheInterface
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
	 * 锁文件路径
	 * @var string
	 */
	public $filePath;
	private $fp;

	/**
	 * 构造方法
	 * @param string $name 锁名称
	 * @param string $filePath 锁文件路径
	 */
	public function __construct($name, $filePath = null)
	{
		$this->name = $name;
		if (null === $filePath)
		{
			$this->filePath = sys_get_temp_dir();
		}
		else if (\is_resource($filePath))
		{
			$this->fp = $filePath;
		}
		else
		{
			$this->filePath = $filePath;
		}

		if (null === $this->fp)
		{
			$this->fp = fopen($this->filePath . '/' . $this->name . '.lock', 'w+');
		}

		if (false === $this->fp)
		{
			echo '加锁文件打开失败' . PHP_EOL;
			exit();
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

		$lock = flock($this->fp, LOCK_EX | LOCK_NB);
		$this->isLocked = true;
		if ($lock)
		{
			$this->lockTime = time();
			fwrite($this->fp, $this->lockTime);
		}
		else
		{
			echo '已经加锁' . PHP_EOL;
			exit();
		}

		return $lock;
	}

	/**
	 * 加锁(阻塞)
	 * @return bool
	 */
	public function lockLock()
	{
		$lock = flock($this->fp, LOCK_EX);
		if ($lock)
		{
			$this->isLocked = true;
			$this->lockTime = time();
			fwrite($this->fp, $this->lockTime);
		}

		return $lock;
	}

	/**
	 * 释放锁
	 * @return bool
	 */
	public function unlock()
	{
		$this->isLocked = false;
		return flock($this->fp, LOCK_UN);
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
		else if ( ! $this->lockTime && $this->filePath)
		{
			return fread($this->fp, filesize($this->filePath . '/' . $this->name . '.lock'));
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

		if (null !== $this->fp)
		{
			$result = fclose($this->fp);
			$this->fp = null;
			return $result;
		}

		return $result;
	}

	public function __destruct()
	{
		$this->closeHandler();
	}
}