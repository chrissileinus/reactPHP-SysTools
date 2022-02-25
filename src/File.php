<?php
/*
 * Created on Fri Feb 25 2022
 *
 * Copyright (c) 2022 Christian Backus (Chrissileinus)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Chrissileinus\React\SysTools;

class File
{
  static function getContent(string $path): \React\Promise\PromiseInterface
  {
    return \React\Promise\Stream\buffer((new \React\Stream\ReadableResourceStream(fopen($path, 'r'))));
  }
}
