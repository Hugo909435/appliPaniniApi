<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();
use App\Http\Controllers\Api\CollectionController;
use Illuminate\Http\Request;
use App\Models\User;

$user = User::first();
if(!$user){ echo "no user\n"; exit; }
$request = Request::create('/', 'GET');
$request->setUserResolver(fn() => $user);
$controller = app(CollectionController::class);
$response = $controller->index($request);
$payload = $response->getContent();
echo 'bytes='.strlen($payload)."\n";
// validate json
json_decode($payload);
if(json_last_error() !== JSON_ERROR_NONE){
  echo 'json_error='.json_last_error_msg()."\n";
  echo substr($payload,0,400)."...\n";
}
?>
