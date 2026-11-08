<?php
require dirname(__DIR__,3).'/vendor/autoload.php';
$app=require dirname(__DIR__,3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$response=$kernel->handle(Illuminate\Http\Request::create('http://127.0.0.1:8000/login'));
if($response->getStatusCode()!==200)throw new RuntimeException('Login failed to render');
$html=$response->getContent();
$html=preg_replace('#(href|src)="http://127.0.0.1:8000/(css|js)/#','$1="file:///'.str_replace('\\','/',dirname(__DIR__,3)).'/public/$2/',$html);
file_put_contents(__DIR__.'/guest-login.html',$html);
echo "200 /login\n";