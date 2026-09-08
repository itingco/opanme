<?php
$root = dirname(__DIR__, 2);
require "$root/app/Services/SampleCoverageCalculator.php";
$c = new App\Services\SampleCoverageCalculator();
$r = $c->calculate(['A','B','C','C'], ['B','C','X','C']);
if ($r !== ['target'=>3,'completed'=>2,'remaining'=>1,'percentage'=>66.67]) { fwrite(STDERR, 'FAIL coverage '.json_encode($r)."\n"); exit(1); }
echo "PASS sample_coverage_test\n";
