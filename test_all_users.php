<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
use App\Http\Controllers\Api\CollectionController;
use Illuminate\Http\Request;
use App\Models\User;

$controller = app(CollectionController::class);
$failed = [];
foreach (User::all() as $user) {
    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn() => $user);
    try {
        $resp = $controller->index($request);
        $payload = $resp->getContent();
        json_decode($payload);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $failed[] = [$user->id, 'json_error', json_last_error_msg()];
        }
    } catch (Throwable $e) {
        $failed[] = [$user->id, 'exception', $e->getMessage()];
    }
}
if (empty($failed)) {
    echo "all users ok\n";
} else {
    print_r($failed);
}
?>
