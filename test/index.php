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

require_once '../src/CacheInterface.php';
require_once '../src/FileAdapter.php';
require_once '../src/RedisAdapter.php';
require_once '../src/MemcachedAdapter.php';

// file
// $lock = new \Cache\Until\Lock\FileAdapter('lock' );
// 加锁(非阻塞)
// $lock->lock();
// 加锁(阻塞)
// $lock->lockLock();
// var_dump($lock->getLockTime());
// try{
// 	while (1)
// 	{
// 		echo 'lock'.PHP_EOL;
// 		sleep(3);
// 	}
// }
//  catch (Exception $e)
// {
// 	$lock->unlock();
// }

// redis
// $params = array(
// 	'host'		=>	'127.0.0.1',
// 	'port'		=>	6379,
// 	'timeout'	=>	0,
// 	'pconnect'	=>	false,
// );
// $lock = new \Cache\Until\Lock\RedisAdapter('lock', $params);
// 加锁(非阻塞)
// $lock->lock();
// $lock->lockLock();
// while (1)
// {
// 	echo 'lock'.PHP_EOL;
// 	sleep(3);
// }

// memcached
$params = array(
	'host'		=>	'127.0.0.1',
	'port'		=>	11211,
	'timeout'	=>	0,
	'pconnect'	=>	false,
);
$lock = new \Cache\Until\Lock\MemcachedAdapter('lock', $params);
// $lock->lock();
$lock->lockLock();
while (1)
{
	echo 'lock'.PHP_EOL;
	sleep(3);
}

$lock->unlock();
