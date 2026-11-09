<?php
require dirname(__DIR__,3).'/vendor/autoload.php';$app=require dirname(__DIR__,3).'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$file=new Illuminate\Http\UploadedFile('C:/Users/nadumi/Downloads/GS-ProjectFlow-Software-Task-Plan-Filled.xlsx','tasks.xlsx',null,null,true);
$r=app(App\Services\TemplateSpreadsheetService::class)->parse($file,['estimated_duration'=>10,'duration_unit'=>'working_days','reference_start_date'=>'2026-11-09']);
echo json_encode(['tasks'=>$r['count'],'errors'=>$r['errors'],'first'=>array_intersect_key($r['tasks'][0],array_flip(['start_date','end_date','start_time','end_time']))],JSON_PRETTY_PRINT).PHP_EOL;if(count($r['errors'])||$r['count']!==10)exit(1);
