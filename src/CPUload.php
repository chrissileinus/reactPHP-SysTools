<?php
/*
 * Created on Fri Feb 25 2022
 *
 * Copyright (c) 2022 Christian Backus (Chrissileinus)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Chrissileinus\React\SysTools;

class CPUload
{
  private static array $last = [];
  private static array $content = [];

  private static float $interval = 1;
  private static int $counter = 0;

  private static array $averagePoints = [1, 5, 10, 15];

  /**
   * init and start the periodic collecting of the data
   *
   * @param  int      $interval
   * @param  int|null $pid
   * @return void
   */
  public static function init(float $interval = 1)
  {
    self::$interval = $interval ?: 1;

    if (defined("sysToolsAveragePoints")) self::$averagePoints = defined("sysToolsAveragePoints");

    self::$content = [
      'time' => 0,
      'sum' => 0,
      'user' => 0,
      'system' => 0,
      'average' => [],
    ];
    foreach (self::$averagePoints as $time) {
      self::$content['average'][$time] = 0;
    }

    self::collect();
  }

  private static function collect(): void
  {
    static $lastCollect = 0;
    $now = microtime(true);
    if ($lastCollect > $now - self::$interval) return;
    $lastCollect = $now;

    self::$counter++;

    if (preg_match('/cpu  (?<user>\d+) (?<nice>\d+) (?<system>\d+) (?<idle>\d+) (?<iowait>\d+)/', file("/proc/stat")[0], $matches)) {
      $new = array_filter($matches, function ($key) {
        return is_string($key);
      }, ARRAY_FILTER_USE_KEY);
      $new['time'] = microtime(true);

      if (isset(self::$last)) {
        extract(array_combine(array_keys($new), array_map(function ($x, $y) {
          return $x - $y;
        }, $new, self::$last)));
      } else {
        extract($new);
      }
      self::$last = $new;

      $cpuTime = $user + $nice + $system + $idle;
      self::$content['time'] = $time;
      self::$content['sum'] = 100 - ($idle * 100 / $cpuTime);
      self::$content['user'] = (($user + $nice) * 100 / $cpuTime);
      self::$content['system'] = ($system * 100 / $cpuTime);

      foreach (self::$averagePoints as $minutes) {
        $intervals = min(($minutes * 60 / self::$interval), self::$counter);
        self::$content['average'][$minutes] = ((self::$content['average'][$minutes] * ($intervals - 1)) + self::$content['sum']) / $intervals;
      }
    }
  }

  public static function get(...$filter): array
  {
    self::collect();

    if ($filter) {
      return array_filter(self::$content, function ($key) use ($filter) {
        if (is_array($filter)) return in_array($key, $filter);
      }, ARRAY_FILTER_USE_KEY);
    }
    return self::$content;
  }
}
