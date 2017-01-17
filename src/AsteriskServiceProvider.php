<?php namespace Calverley\Asterisk;

use Illuminate\Support\ServiceProvider;

class AsteriskServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/config.php' => config_path('asterisk.php'),
        ]);
    }

    public function register()
    {
        $this->app->bind('asterisk', function($app)
        {
            return new Asterisk($app->config->get('asterisk', array()));
        });

        $this->app->bind('manager', function($app)
        {
            return new Manager($app->config->get('asterisk', array()));
        });

        $this->app->bind('ari', function($app)
        {
            return new Ari();
        });

        $this->app->booting(function()
        {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Asterisk', 'Calverley\Asterisk\Facades\Asterisk');
        });
    }

}