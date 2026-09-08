<?php
$root=dirname(__DIR__,2);
$files=[
 "$root/app/Http/Controllers/Admin/SamplingReportController.php"=>['exportPdf','date_from','date_to'],
 "$root/app/Services/SampleReportService.php"=>['coverage','SampleCoverageCalculator','Smallest On Hand'],
 "$root/app/Services/SampleReportPdfService.php"=>['application/pdf','buildPdf'],
 "$root/resources/views/admin/sampling/index.blade.php"=>['Coverage','Export PDF','Tanggal Dari'],
];
foreach($files as $f=>$needles){$c=@file_get_contents($f)?:'';foreach($needles as $n)if(!str_contains($c,$n)){fwrite(STDERR,"FAIL $f missing $n\n");exit(1);}}
echo "PASS sample_report_contract\n";
