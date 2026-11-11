<?php
namespace App\Services;
use App\Models\ApplicationSetting;
use Illuminate\Support\Facades\{Schema,Mail};
class EmailConfigurationService {
 public function saved():?array {
  if(!Schema::hasTable('application_settings'))return null;
  return ApplicationSetting::find('email')?->value;
 }
 public function current():array {
  return $this->saved() ?? ['mailer'=>config('mail.default'),'host'=>config('mail.mailers.smtp.host'),'port'=>config('mail.mailers.smtp.port'),'username'=>config('mail.mailers.smtp.username'),'password'=>config('mail.mailers.smtp.password'),'scheme'=>config('mail.mailers.smtp.scheme')==='smtps'?'smtps':'smtp','from_address'=>config('mail.from.address'),'from_name'=>config('mail.from.name')];
 }
 public function apply():void {
  $data=$this->saved();if(!$data)return;
  config(['mail.default'=>$data['mailer'],'mail.from.address'=>$data['from_address'],'mail.from.name'=>$data['from_name'],'mail.mailers.smtp.host'=>$data['host'],'mail.mailers.smtp.port'=>(int)$data['port'],'mail.mailers.smtp.username'=>$data['username']??null,'mail.mailers.smtp.password'=>$data['password']??null,'mail.mailers.smtp.scheme'=>$data['scheme'],'mail.mailers.smtp.url'=>null,'mail.mailers.smtp.auto_tls'=>true,'mail.mailers.smtp.require_tls'=>$data['scheme']==='smtp','mail.mailers.smtp.timeout'=>15]);
  Mail::purge('smtp');Mail::purge('log');
 }
 public function save(array $data):void {
  $data+=['host'=>null,'username'=>null,'password'=>null];
  $password=$this->current()['password']??null;
  if($data['password']===null || $data['password']==='')$data['password']=$password;
  if(!empty($data['clear_password']))$data['password']=null;
  unset($data['clear_password']);ApplicationSetting::updateOrCreate(['key'=>'email'],['value'=>$data]);$this->apply();
 }
}
