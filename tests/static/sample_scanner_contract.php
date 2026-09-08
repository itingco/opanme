<?php
$root=dirname(__DIR__,2);
$files=[
 "$root/app/Http/Controllers/Gerai/SamplingController.php"=>['lookup','confirm','close'],
 "$root/app/Services/SampleCheckingService.php"=>['Crypt::encryptString','RESULT_MATCH','physical_qty'],
 "$root/resources/views/gerai/scan.blade.php"=>['Stok Cocok','Tidak Cocok','Lokasi / Rak'],
 "$root/resources/js/sampling.js"=>['AudioContext','physical_qty','scan-lookup'],
 "$root/routes/web.php"=>["role:GERAI",'gerai.sampling'],
];
foreach($files as $f=>$needles){$c=@file_get_contents($f)?:'';foreach($needles as $n)if(!str_contains($c,$n)){fwrite(STDERR,"FAIL $f missing $n\n");exit(1);}}
echo "PASS sample_scanner_contract\n";
