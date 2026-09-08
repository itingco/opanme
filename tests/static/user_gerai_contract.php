<?php
$root=dirname(__DIR__,2);
$u=@file_get_contents("$root/app/Models/User.php")?:'';
$c=@file_get_contents("$root/app/Http/Controllers/Admin/UserController.php")?:'';
$v=@file_get_contents("$root/resources/views/admin/users/index.blade.php")?:'';
foreach ([[$u,"ROLE_GERAI = 'GERAI'"],[$c,'erp_warehouse_id'],[$c,'ErpCatalogService'],[$v,'GERAI'],[$v,'erp_warehouse_id']] as [$txt,$n]) if(!str_contains($txt,$n)){fwrite(STDERR,"FAIL missing $n\n");exit(1);} echo "PASS user_gerai_contract\n";
