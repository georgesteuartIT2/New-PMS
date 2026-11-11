<?php
return ['default'=>env('MAIL_MAILER','log'),'mailers'=>[
 'smtp'=>['transport'=>'smtp','scheme'=>env('MAIL_SCHEME'),'url'=>env('MAIL_URL'),'host'=>env('MAIL_HOST','127.0.0.1'),'port'=>env('MAIL_PORT',2525),'username'=>env('MAIL_USERNAME'),'password'=>env('MAIL_PASSWORD'),'auto_tls'=>true,'require_tls'=>env('MAIL_ENCRYPTION')==='tls','timeout'=>15,'local_domain'=>env('MAIL_EHLO_DOMAIN')],
 'log'=>['transport'=>'log','channel'=>env('MAIL_LOG_CHANNEL')],
 ],'from'=>['address'=>env('MAIL_FROM_ADDRESS','projectflow@example.com'),'name'=>env('MAIL_FROM_NAME','GS ProjectFlow')]];
