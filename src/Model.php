<?php

namespace LoganSong\LaravelMultiDatabase;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model as BaseModel;
use LoganSong\LaravelMultiDatabase\traits\ModelExtensionTrait;

class Model extends BaseModel
{
  use ModelExtensionTrait;
  
  /**
   * 서로 다른 database안의 table들끼리 관계를 맺기 위해 DB 이름을 명시적으로 테이블명앞에 작성하기 위해 생성자 확장
   * 상황에 따라 updateOrCreate시 attribute와 value가 병합이 안되는 문제가 있음
   */

  public function __construct()
  {
    parent::__construct();

    $this->table = $this->getConnection()->getDatabaseName() . '.' . $this->getTable();
  }

  protected function serializeDate(DateTimeInterface $date)
  {
    return $date->format('Y-m-d H:i:s');
  }
}
