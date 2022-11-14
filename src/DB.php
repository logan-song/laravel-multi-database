<?php

namespace LoganSong\LaravelMultiDatabase;
use Illuminate\Support\Facades\DB as BaseDb;

class DB extends BaseDb
{
  public static function enableQueryLog(): void
  {
    foreach (array_keys(config('database.connections')) as $database) {
      parent::connection($database)->enableQueryLog();
    }
  }

  public static function getQueryLog()
  {
    $result = [];
    foreach (array_keys(config('database.connections')) as $database) {
      if (!empty($queryLog = parent::connection($database)->getQueryLog())) {
        $result[$database] = $queryLog;
      }
    }
    return $result;
  }

  public static function beginTransaction(): void
  {
    foreach (array_keys(config('database.connections')) as $database) {
      if (parent::connection($database)->transactionLevel() === 0) {
        parent::connection($database)->beginTransaction();
      }
    }
  }

  public static function commit(): void
  {
    foreach (array_keys(config('database.connections')) as $database) {
      parent::connection($database)->commit();
    }
  }

  public static function rollBack($toLevel = null): void
  {
    foreach (array_keys(config('database.connections')) as $database) {
      parent::connection($database)->rollBack($toLevel);
    }
  }
}