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
  private static array $content = [];
  private static float $interval = 1;
  private static array $keyReplacement = [
    'cpu MHz' => "frequency"
  ];

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
  }

  private static function collect()
  {
    static $lastCollect = 0;
    $now = microtime(true);
    if ($lastCollect > $now - self::$interval) return;
    $lastCollect = $now;

    if ($content = file("/proc/cpuinfo")) {
      $id = 0;
      foreach ($content as $entry) {
        if (preg_match('/(.+) *:(.*)/', $entry, $matches)) {
          $name = trim($matches[1]);
          if (isset(self::$keyReplacement[$name])) $name = self::$keyReplacement[$name];
          self::$content[$id][$name] = trim($matches[2]);

          if (is_numeric(self::$content[$id][$name]))
            self::$content[$id][$name] = self::$content[$id][$name] + 0;

          if (self::$content[$id][$name] == "")
            self::$content[$id][$name] = null;
        } else {
          $id++;
        }
      }
    }
  }

  /**
   * get info of each core
   *
   * @param  [type] ...$filter the entries
   * @return array
   */
  public static function get(...$filter): array
  {
    self::collect();
    if ($filter) {
      $return = [];
      foreach (self::$content as $id => $entry) {
        $return[$id] = array_filter($entry, function ($key) use ($filter) {
          if (is_array($filter)) return in_array($key, $filter);
        }, ARRAY_FILTER_USE_KEY);
      }
      return $return;
    }
    return self::$content;
  }

  /**
   * a summary info of all cores
   *
   * @param        ...$filter the entries
   * @return array
   */
  public static function sum(...$filter): array
  {
    self::collect();
    $return = [];

    $toAvg = [
      'frequency'
    ];

    foreach (self::$content as $id => $entry) {
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
        $return[$name] = $value / count(self::$content);
    }
    return $return;
  }
}
