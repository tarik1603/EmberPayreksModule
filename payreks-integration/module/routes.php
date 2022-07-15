<?php

use \App\Middleware\AuthMiddleware;
use \App\Middleware\StorePurchaseMiddleware;

$app->post('/store/payreks/session', 'PayreksController:session')->add(new StorePurchaseMiddleware($container))->add(new AuthMiddleware($container));
$app->post('/store/payreks/order', 'PayreksController:order')->add(new StorePurchaseMiddleware($container))->add(new AuthMiddleware($container));
$app->post('/store/payreks/callback', 'PayreksController:callback')->setName('store_payreks_callback');
$app->post('/store/payreks/success', 'PayreksController:success')->setName('store_payreks_success');
$app->post('/store/payreks/fail', 'PayreksController:fail')->setName('store_payreks_fail');
$app->post('/store/payreks/webhook', 'PayreksController:webhook')->setName('store_payreks_webhook');
