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
  private static $command = "";
  private static $interval = 1;
  private static $counter = 0;
  private static $pid;

  private static $lastUsedTime = 0;
  private static $lastExecTime = 0;

  public static function init(int $interval = 1)
  {
    self::$ticks = intval(exec('getconf CLK_TCK'));
    self::$command = "cat /proc/uptime; cat /proc/" . posix_getpid() . "/stat";
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
    File::getContent("/proc/uptime")->then(function (string $contents) {
      if (preg_match(
        '/(?<upTime>\d+\.\d+) \d+\.\d+/',
        $contents,
        $matches
      )) {
        extract($matches);
        File::getContent("/proc/" . self::$pid . "/stat")->then(function (string $contents) use ($upTime) {
          if (preg_match(
            '/(?: *\S+){13} (?<utime>\d+) (?<stime>\d+) (?<cutime>\d+) (?<cstime>\d+) (?<priority>\d+) (?<nice>\d+) (?<num_threads>\d+) \d+ (?<starttime>\d+) (?<vsize>\d+)/',
            $contents,
            $matches
          )) {
            extract($matches);

            \Chrissileinus\React\Log\Writer::debug(
              PHP_EOL . $contents,
              'CPUload'
            );
          }
        });
      }
    });
  }

  private static function runCommand()
  {
    \WyriHaximus\React\childProcessPromise(\React\EventLoop\Loop::get(), new \React\ChildProcess\Process(self::$command))->then(function ($result) {
      // \Chrissileinus\React\Log\Writer::debug(
      //   PHP_EOL . yaml_emit($result->getStdout()),
      //   'CPUload'
      // );
      if (preg_match(
        // '/(?<upTime>\d+\.\d+) \d+\.\d+\n(?<pid>\d+) (?<comm>\S+) (?<state>\w) (?<ppid>\d+) (?<pgrp>\d+) (?<session>\d+) (?<tty_nr>\d+) (?<tpgid>\d+) (?<flags>\d+) (?<minflt>\d+) (?<cminflt>\d+) (?<majflt>\d+) (?<cmajflt>\d+) (?<utime>\d+) (?<stime>\d+) (?<cutime>\d+) (?<cstime>\d+) (?<priority>\d+) (?<nice>\d+) (?<num_threads>\d+) (?<itrealvalue>\d+) (?<starttime>\d+) (?<vsize>\d+) (?<rss>\d+) (?<rsslim>\d+) (?<startcode>\d+) (?<endcode>\d+) (?<startstack>\d+) (?<kstkesp>\d+) (?<kstkeip>\d+) (?<signal>\d+) (?<blocked>\d+) (?<sigignore>\d+) (?<sigcatch>\d+) (?<wchan>\d+) (?<nswap>\d+) (?<cnswap>\d+) (?<exit_signal>\d+) (?<processor>\d+) (?<rt_priority>\d+)/',
        '/(?<upTime>\d+\.\d+) \d+\.\d+\n(?: *\S+){13} (?<utime>\d+) (?<stime>\d+) (?<cutime>\d+) (?<cstime>\d+) (?<priority>\d+) (?<nice>\d+) (?<num_threads>\d+) \d+ (?<starttime>\d+) (?<vsize>\d+)/',
        $result->getStdout(),
        $matches
      )) {
        self::$counter++;

        self::setValues(
          ($matches['utime'] + $matches['stime']) / self::$ticks,
          $matches['upTime'] - $matches['starttime'] / self::$ticks
        );
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
