<?php
/*
 * Created on Fri Feb 25 2022
 *
 * Copyright (c) 2022 Christian Backus (Chrissileinus)
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Chrissileinus\React\SysTools;

class usageCPU
{
  public float $time = 0;
  public float $user = 0;
  public float $system = 0;

  function __construct(int $mode = 0)
  {
    static $first = true;
    $this->time = microtime(true);

    if ($first) {
      $this->user = 1;
      $this->system = 1;
      $first = false;
      return;
    }
    $ru = getrusage($mode);
    $this->user = ($ru["ru_utime.tv_sec"] + $ru["ru_utime.tv_usec"] / 1000000);
    $this->system = ($ru["ru_stime.tv_sec"] + $ru["ru_stime.tv_usec"] / 1000000);
  }

  static function compare(self &$lastValues, int $mode = 0)
  {
    $currentValues = new self($mode);
    $result = array_combine(array_keys((array) $currentValues), array_map(function ($x, $y) {
      return max($x - $y, 0);
    }, (array)$currentValues, (array)$lastValues));
    $result['user']   = $result['user'] / $result['time'] * 100;
    $result['system'] = $result['system'] / $result['time'] * 100;
    $result['sum']    = $result['user'] + $result['system'];
    $lastValues = $currentValues;
    return $result;
  }

  // Periodicly

  private static usageCPU $last;
  private static array $content = [];

  private static float $interval = 1;
  private static int $counter = 0;

  private static \React\EventLoop\TimerInterface $timer;

  /**
   * init and start the periodic collecting of the data
   *
   * @param  int      $interval
   * @param  int|null $pid
   * @return void
   */
  public static function init(float $interval = 1)
  {
    self::$last = new usageCPU();
    self::$interval = $interval ?: 1;

    self::$content = [
      'current' => [
        'time' => microtime(true),
        'user' => 0,
        'system' => 0,
        'sum' => 0,
      ],
      'average' => [0],
    ];
    foreach (range(1, 15) as $time) self::$content['average'][$time] = 0;

    self::collect();

    if (isset(self::$timer)) \React\EventLoop\Loop::cancelTimer(self::$timer);

    self::$timer = \React\EventLoop\Loop::addPeriodicTimer(self::$interval, function () {
      self::collect();
    });
  }

  private static function collect(): void
  {
    self::$content['current'] = self::compare(lastValues: self::$last);
    self::$content['average'][0] = self::$content['current']['sum'];
    self::$counter++;

    foreach (range(1, 15) as $time) {
      $intervals = min(($time * 60 / self::$interval), self::$counter);
      self::$content['average'][$time] = ((self::$content['average'][$time] * ($intervals - 1)) + self::$content['current']['sum']) / $intervals;
    }
  }

  public static function get(...$filter): array
  {
    if ($filter) {
      return array_filter(self::$content, function ($key) use ($filter) {
        if (is_array($filter)) return in_array($key, $filter);
      }, ARRAY_FILTER_USE_KEY);
    }
    return self::$content;
  }
}
