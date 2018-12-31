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

interface CacheInterface
{
	public function lock();

	public function unlock();

	public function lockLock();

	public function closeHandler();
}