<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Http\Controllers\Api\CollectionController;
use Illuminate\Http\Request;
use App\Models\User;

$user = User::first();
$request = Request::create('/', 'GET');
$request->setUserResolver(fn() => $user);
$controller = app(CollectionController::class);
$resp = $controller->ownedCards($request);
$payload = $resp->getContent();
echo 'owned bytes='.strlen($payload)."\n";
?>
