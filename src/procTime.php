<?php
/*
 * Created on Fri Feb 25 2022
 *
 * Copyright (c) 2022 Christian Backus (Chrissileinus)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Chrissileinus\React\SysTools;

class procTime
{
  private static $first = null;

  static function running()
  {
    if (!self::$first) self::$first = microtime(true);
    return microtime(true) - self::$first;
  }

  static function realRunning(int $mode = 0): array
  {

    $ru = getrusage($mode);
    return [
      'time'   => self::running(),
      'user'   => ($ru["ru_utime.tv_sec"] + $ru["ru_utime.tv_usec"] / 1000000),
      'system' => ($ru["ru_stime.tv_sec"] + $ru["ru_stime.tv_usec"] / 1000000),
    ];
  }
}
