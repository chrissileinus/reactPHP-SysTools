<?php
/*
 * Created on Fri Nov 26 2021
 *
 * Copyright (c) 2021 Christian Backus (Chrissileinus)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Chrissileinus\React\SysTools;

class CPUinfo
{
  private static array $info = [];
  private static array $statold = [];
  private static float $interval = 1;
  private static array $keyReplacement = [
    'cpu MHz' => "frequency"
  ];

  private static \React\EventLoop\TimerInterface $timer;

  /**
   * init and start the periodic collecting of the data
   *
   * @param  int  $interval
   * @return void
   */
  public static function init(float $interval = 1)
  {
    self::$interval = $interval;

    self::collect();

    if (isset(self::$timer)) \React\EventLoop\Loop::cancelTimer(self::$timer);

    self::$timer = \React\EventLoop\Loop::addPeriodicTimer(self::$interval, function () {
      self::collect();
    });
  }

  private static function collect()
  {
    File::getContent("/proc/cpuinfo")->then(function ($content) {
      $id = 0;
      foreach (explode("\n", $content) as $entry) {
        if (preg_match('/(.+) *:(.*)/', $entry, $matches)) {
          $name = trim($matches[1]);
          if (isset(self::$keyReplacement[$name])) $name = self::$keyReplacement[$name];
          self::$info[$id][$name] = trim($matches[2]);

          if (is_numeric(self::$info[$id][$name]))
            self::$info[$id][$name] = self::$info[$id][$name] + 0;

          if (self::$info[$id][$name] == "")
            self::$info[$id][$name] = null;
        } else {
          $id++;
        }
      }
    });

    File::getContent("/proc/stat")->then(function (string $content) {
      foreach (explode("\n", $content) as $entry) {
        if (preg_match('/cpu(?<core>\d+) (?<user>\d+) (?<nice>\d+) (?<system>\d+) (?<idle>\d+)/', $entry, $matches)) {
          $new = array_filter($matches, function ($key) {
            return is_string($key);
          }, ARRAY_FILTER_USE_KEY);

          if (isset(self::$statold[$new['core']])) {
            extract(array_combine(array_keys($new), array_map(function ($x, $y) {
              return $x - $y;
            }, $new, self::$statold[$new['core']])));
          } else {
            extract($new);
          }
          self::$statold[$new['core']] = $new;

          $cpuTime = $user + $nice + $system + $idle;
          self::$info[$new['core']]['usage'] = 100 - ($idle * 100 / $cpuTime);
        }
      }
    });
  }

  /**
   * get info of each core
   *
   * @param  [type] ...$filter the entries
   * @return array
   */
  public static function get(...$filter): array
  {
    if ($filter) {
      $return = [];
      foreach (self::$info as $id => $entry) {
        $return[$id] = array_filter($entry, function ($key) use ($filter) {
          if (is_array($filter)) return in_array($key, $filter);
        }, ARRAY_FILTER_USE_KEY);
      }
      return $return;
    }
    return self::$info;
  }

  /**
   * a summary info of all cores
   *
   * @param        ...$filter the entries
   * @return array
   */
  public static function sum(...$filter): array
  {
    $return = [];

    $toAvg = [
      'frequency', 'usage'
    ];

    foreach (self::$info as $id => $entry) {
      foreach ($entry as $name => $value) {
        if ($filter && !in_array($name, $filter)) continue;

        if (isset($return[$name]) && !in_array($name, $toAvg)) continue;

        if (isset($return[$name]) && is_numeric($value))
          $return[$name] += $value;

        if (!isset($return[$name]))
          $return[$name] = $value;
      }
    }
    foreach ($return as $name => $value) {
      if (is_numeric($value) && in_array($name, $toAvg))
        $return[$name] = $value / count(self::$info);
    }
    return $return;
  }
}
