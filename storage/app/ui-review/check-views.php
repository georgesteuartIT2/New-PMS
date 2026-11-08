<?php
require dirname(__DIR__,3).'/vendor/autoload.php';
$app=require dirname(__DIR__,3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$compiler=$app->make('blade.compiler');
$count=0;
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file){
 if(!$file->isFile()||!str_ends_with($file->getFilename(),'.blade.php'))continue;
 token_get_all($compiler->compileString(file_get_contents($file->getPathname())),TOKEN_PARSE);$count++;
}
echo "PASS: ".$count." Blade templates compile and pass PHP parsing.\n";