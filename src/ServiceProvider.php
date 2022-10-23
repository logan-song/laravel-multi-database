<?php

namespace LoganSong\LaravelMultiDatabase;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
  /**
   * Register services.
   *
   * @return void
   */
  public function register()
  {
    $this->app->bind('DB', function () {
      return new DB(new Request);
    });
    $this->app->bind('Model', function () {
      return new Model(new Request);
    });
  }

  /**
   * Bootstrap services.
   *
   * @return void
   */
  public function boot()
  {
    //
  }
}
