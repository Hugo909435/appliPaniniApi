<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();
use App\Http\Controllers\Api\CollectionController;
use Illuminate\Http\Request;
use App\Models\User;

$user = User::first();
$request = Request::create('/', 'GET');
$request->setUserResolver(fn() => $user);
$controller = app(CollectionController::class);
$response = $controller->index($request);
$data = json_decode($response->getContent(), true);
foreach(($data['all_cards'] ?? []) as $card){
  if(($card['name'] ?? '') === 'Hugo'){
    echo json_encode($card, JSON_PRETTY_PRINT), "\n";
  }
}
?>
