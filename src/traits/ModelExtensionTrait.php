<?php

namespace LoganSong\LaravelMultiDatabase\traits;

use Closure;
use ReflectionFunction;
use ReflectionClass;
use SplFileObject;
use Str;

trait ModelExtensionTrait
{
  public static function getTableName()
  {
    $obj = with(new static);
    $databaseTable = explode('.', $obj->getTable());
    $table = last($databaseTable);
    return $obj->getConnection()->getName().'.'.$table;
  }

  public function getTableColumns()
  {
    $databaseTable = explode('.', $this->getTable());
    $table = last($databaseTable);

    return $this->getConnection()->getSchemaBuilder()->getColumnListing($table);
  }

  public function scopeExclude($query, $columns)
  {
    return $query->select(array_diff(array_diff($this->getTableColumns(), $this->getHidden()),
      (array) $columns));
  }

  public function scopeWithWhereHas($query, $relationships, $conditions)
  {
    if (Str::contains($relationships, ':')) {
      $relationshipData = explode(':', $relationships);
      $selectData = explode(',', $relationshipData[1]);

      array_walk($selectData, function (&$select) {
        $select = sprintf("'%s'", $select);
      });

      $selectQuery = sprintf('$query->select(%s);', implode(',', $selectData));
      $conditions = $this->injectClosure($conditions, $selectQuery);
    }

    return $query->with(Str::before($relationships, ':'), $conditions)
      ->whereHas(Str::before($relationships, ':'), $conditions);
  }

  public function scopeWithHas($query, $relationships)
  {
    return $query->with($relationships)
      ->has(Str::before($relationships, ':'));
  }

  public function scopeWhereEmpty($query, $column)
  {
    return $query->where($column, '')
      ->whereNull($column);
  }

  public function scopeWhereNotEmpty($query, $column)
  {
    return $query->where($column, '<>', '')
      ->whereNotNull($column);
  }

  public function scopeUpdateOrCreateEx($query, $_conditions, $_data): ?int
  {
    $tableName = last(explode('.', $this->getTable()));

    // 2022-05-11 코드원복. insertGetId시 array인 필드에서 오류발생
    $ret = $query->where($_conditions)->first();

    try {
      if (function_exists('__setSchedulerLog')) {
        __setSchedulerLog($tableName, 'scheduler', $_conditions, $_data, true);
      }

      $type = 'insert';
      if (is_null($ret)) {
        $model = new $this;
      } else {
        $model = $ret;
        $type = 'update';
      }

      foreach (array_merge($_conditions, $_data) as $key => $val) {
        $model->{$key} = $val;
      }
      $model->save();
      $id = $model->id;
      if (function_exists('__setSchedulerLog')) {
        __setSchedulerLog($tableName, 'scheduler', $type, 'return:' . $id, false);
      }
    } catch (\Throwable $th) {
      if (function_exists('__setSchedulerLog')) {
        __setSchedulerLog($tableName, 'scheduler', 'error', $th->getMessage(), false);
      }
      throw $th;
    }

    return $id;
  }

  public function injectClosure(Closure $closure, $injectCode)
  {
    $code = 'function (';
    $r = new ReflectionFunction($closure);
    $params = array();
    foreach ($r->getParameters() as $p) {
      $s = '';
      $className = $p->getType() && !$p->getType()->isBuiltin()
        ? new ReflectionClass($p->getType()->getName())
        : null;
      if ($p->getType() && $p->getType()->getName() === 'array') {
        $s .= 'array ';
      } else if (!is_null($className)) {
        $s .= $className . ' ';
      }
      if ($p->isPassedByReference()) {
        $s .= '&';
      }
      $s .= '$' . $p->name;
      if ($p->isOptional()) {
        $s .= ' = ' . var_export($p->getDefaultValue(), TRUE);
      }
      $params[] = $s;
    }
    $code .= implode(', ', $params);
    $code .= '){' . PHP_EOL;
    $lines = file($r->getFileName());
    for ($l = $r->getStartLine(); $l < $r->getEndLine(); $l++) {
      $code .= $lines[$l];
    }

    $start = strpos($code, '{') + 1;
    $end = strrpos($code, '}');
    return $this->create_function(implode(', ', $params), substr($code, $start, $end - $start) . $injectCode);
  }

  public function create_function($arg, $body)
  {
    static $cache = array();
    static $max_cache_size = 64;
    static $sorter;

    if ($sorter === NULL) {
      $sorter = function ($a, $b) {
        if ($a->hits == $b->hits) {
          return 0;
        }

        return ($a->hits < $b->hits) ? 1 : -1;
      };
    }

    $crc = crc32($arg . "\\x00" . $body);

    if (isset($cache[$crc])) {
      ++$cache[$crc][1];
      return $cache[$crc][0];
    }

    if (sizeof($cache) >= $max_cache_size) {
      uasort($cache, $sorter);
      array_pop($cache);
    }

    $cache[$crc] = array($cb = eval('return function(' . $arg . '){' . $body . '};'), 0);
    return $cb;
  }
}
