<?php
$pdo=new PDO("mysql:host=127.0.0.1;dbname=booster_api","root","");
foreach($pdo->query("select email,is_super_admin,status from users") as $r){echo $r['email']."\t".$r['is_super_admin']."\t".$r['status']."\n";}
