<?php
/*
 * Created on Fri Nov 26 2021
 *
 * Copyright (c) 2021 Christian Backus (Chrissileinus)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Chrissileinus\React\SysTools;

class CPUload
{
  private static $current = 0;
  private static $average = [];

  private static $ticks = 1;
  private static $interval = 1;
  private static $counter = 0;
  private static $pid;

  private static $lastUsedTime = 0;
  private static $lastExecTime = 0;

  public static function init(int $interval = 1)
  {
    self::$ticks = intval(exec('getconf CLK_TCK'));
    self::$pid = posix_getpid();
    self::$interval = $interval;

    \React\EventLoop\Loop::addPeriodicTimer(self::$interval, function () {
      self::collect();
    });
  }

  /**
   * Return the current CPU usage from this process of the last second
   *
   * @return float
   */
  public static function current(): float
  {
    return self::$current;
  }

  /**
   * Return the average CPU usage from this process of the last `$minutes`
   *
   * @param integer $minutes
   * @return float
   */
  public static function average(int $minutes = 0): float
  {
    return isset(self::$average[$minutes]) ? self::$average[$minutes] : self::$current;
  }

  private static function collect(): void
  {
    File::getContent("/proc/uptime")->then(function (string $uptimeContents) {
      if (preg_match(
        '/(?<upTime>\d+\.\d+) \d+\.\d+/',
        $uptimeContents,
        $matches
      )) {
        extract($matches);
        File::getContent("/proc/" . self::$pid . "/stat")->then(function (string $statContents) use ($upTime, $uptimeContents) {
          if (preg_match(
            '/(?: *\S+){13} (?<utime>\d+) (?<stime>\d+) (?<cutime>\d+) (?<cstime>\d+) (?<priority>\d+) (?<nice>\d+) (?<num_threads>\d+) \d+ (?<starttime>\d+) (?<vsize>\d+)/',
            $statContents,
            $matches
          )) {
            extract($matches);

            self::$counter++;

            self::setValues(
              ($utime + $stime) / self::$ticks,
              $upTime - $starttime / self::$ticks
            );
          }
        });
      }
    });
  }

  private static function setValues(int|float $procUsedTime, int|float $procExecTime)
  {
    self::$current = 100 * ($procUsedTime - self::$lastUsedTime) / ($procExecTime - self::$lastExecTime);
    self::$average[0] = 100 * $procUsedTime / $procExecTime;

    self::$lastUsedTime = $procUsedTime;
    self::$lastExecTime = $procExecTime;

    foreach (range(1, 15) as $time) {
      $intervals = min(($time * 60 / self::$interval), self::$counter);
      if (!array_key_exists($time, self::$average)) self::$average[$time] = self::$current;
      self::$average[$time] = ((self::$average[$time] * ($intervals - 1)) + self::$current) / $intervals;
    }
  }
}
