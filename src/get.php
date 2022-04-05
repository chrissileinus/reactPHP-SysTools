<?php
/*
 * Created on Fri Feb 25 2022
 *
 * Copyright (c) 2022 Christian Backus (Chrissileinus)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Chrissileinus\React\SysTools;

class get
{
  static function rusage(): float
  {
    $ru = getrusage();
    return ($ru["ru_utime.tv_sec"] + $ru["ru_utime.tv_usec"] / 1000000);
  }
}
