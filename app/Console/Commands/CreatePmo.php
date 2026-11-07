<?php
namespace App\Console\Commands;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class CreatePmo extends Command {
 protected $signature='pmo:create {email?}';
 protected $description='Create the initial PMO administrator and one-time temporary password';
 public function handle():int {
  $email=strtolower(trim($this->argument('email') ?: $this->ask('PMO email')));
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)){$this->error('Invalid email.');return self::FAILURE;}
  if(User::where('email',$email)->exists()){$this->error('This account already exists.');return self::FAILURE;}
  $name=$this->ask('PMO name','PMO Administrator');
  $password=Str::random(22);
  User::create(['name'=>$name,'email'=>$email,'password'=>Hash::make($password),'system_role'=>'pmo','account_status'=>'active','must_change_password'=>true]);
  $this->info('PMO created. This password is displayed ONLY here; store it securely.');
  $this->line('Temporary password: '.$password);
  return self::SUCCESS;
 }
}
