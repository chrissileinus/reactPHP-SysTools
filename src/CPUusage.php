<?php
/*
 * Created on Fri Feb 25 2022
 *
 * Copyright (c) 2022 Christian Backus (Chrissileinus)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Chrissileinus\React\SysTools;

class CPUusage
{
  private static $last;
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
    self::$last = procTime::realRunning();
    self::$interval = $interval ?: 1;

    if (defined("sysToolsAveragePoints")) self::$averagePoints = defined("sysToolsAveragePoints");

    self::$content = [
      'time' => microtime(true),
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

    $current = procTime::realRunning();
    $temp = array_combine(array_keys($current), array_map(function ($x, $y) {
      return max($x - $y, 0);
    }, $current, self::$last));

    self::$content['time']   = $temp['time'];
    self::$content['user']   = $temp['user'] / $temp['time'] * 100;
    self::$content['system'] = $temp['system'] / $temp['time'] * 100;
    self::$content['sum']    = self::$content['user'] + self::$content['system'];
    self::$last = $current;

    self::$counter++;

    foreach (self::$averagePoints as $minutes) {
      $intervals = min(($minutes * 60 / self::$interval), self::$counter);
      self::$content['average'][$minutes] = ((self::$content['average'][$minutes] * ($intervals - 1)) + self::$content['sum']) / $intervals;
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
