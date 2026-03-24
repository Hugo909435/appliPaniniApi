<?php
$pdo=new PDO("mysql:host=127.0.0.1;dbname=booster_api","root","");
$tables=['user_cards','trades','trade_items','cards','users','packs'];
foreach($tables as $t){
  echo "\n== $t indexes ==\n";
  $stmt=$pdo->query("SHOW INDEX FROM `$t`");
  foreach($stmt as $row){
    echo $row['Key_name']." (".$row['Column_name'].")\n";
  }
}
