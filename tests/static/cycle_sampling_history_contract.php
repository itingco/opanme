<?php
$root=dirname(__DIR__,2);
$c=@file_get_contents("$root/patches/CycleController.sample-history.patch")?:'';
$v=@file_get_contents("$root/resources/views/admin/cycles/scan-detail.blade.php")?:'';
$sp=@file_get_contents("$root/patches/Summary.sampling-link.patch")?:'';
foreach([[$c,'sample_from'],[$c,'SampleCheck'],[$v,'Riwayat Sampling'],[$v,'sample_to'],[$sp,'Detail / Riwayat Sampling']] as [$txt,$n]) if(!str_contains($txt,$n)){fwrite(STDERR,"FAIL missing $n\n");exit(1);} echo "PASS cycle_sampling_history_contract\n";
