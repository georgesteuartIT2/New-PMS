<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Azure\Provider as AzureProvider;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  if(!app()->runningInConsole())app(\App\Services\EmailConfigurationService::class)->apply();
  \Illuminate\Support\Facades\Queue::before(fn()=>app(\App\Services\EmailConfigurationService::class)->apply());
  Event::listen(function (SocialiteWasCalled $event): void { $event->extendSocialite('azure', AzureProvider::class); });
 }
}
