<?php
/**
 * Apply from the same folder as artisan. Creates backup of routes/web.php.
 * Adds a deliberately explicit route include and validates the expected names.
 */
$root = __DIR__;
if (!is_file($root.'/artisan') || !is_file($root.'/routes/web.php')) {
    fwrite(STDERR, "ERROR: Extract ZIP into the Laravel project root next to artisan.\n");
    exit(1);
}
$payload = [
    'routes/checker_camera_v13.php',
    'app/Http/Controllers/Checker/CameraManualController.php',
    'app/Http/Controllers/Checker/CameraAiReadController.php',
    'config/opanme_camera_ai.php',
    'database/migrations/2026_11_16_235900_create_opname_manual_scan_photos_table.php',
];
foreach ($payload as $relative) {
    $src = $root.'/V13_FILES/'.$relative;
    $target = $root.'/'.$relative;
    if (!is_file($src)) {
        fwrite(STDERR, "ERROR missing patch file: $relative\n"); exit(1);
    }
    if (!is_file($target)) {
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true)) {
            fwrite(STDERR, "ERROR cannot create directory: ".dirname($target)."\n"); exit(1);
        }
        if (!copy($src,$target)) {fwrite(STDERR,"ERROR cannot copy $relative\n");exit(1);}
        echo "ADDED $relative\n";
    } elseif ($relative === 'routes/checker_camera_v13.php') {
        if (sha1_file($src) !== sha1_file($target)) {
            $backup=$target.'.before-v13';
            if (!is_file($backup) && !copy($target,$backup)) {fwrite(STDERR,"ERROR backup $relative\n");exit(1);}
            if (!copy($src,$target)) {fwrite(STDERR,"ERROR update $relative\n");exit(1);}
            echo "UPDATED $relative\n";
        }
    } else {
        echo "KEEP existing $relative\n";
    }
}
$web=$root.'/routes/web.php';
$contents=file_get_contents($web);
$line="require __DIR__.'/checker_camera_v13.php';";
if (!str_contains($contents,$line)) {
    $backup=$web.'.before-v13';
    if (!is_file($backup) && !copy($web,$backup)) {fwrite(STDERR,"ERROR backing up routes/web.php\n");exit(1);}
    // Laravel route files generally omit the PHP closing tag. Keep include at end in either case.
    $trim=rtrim($contents);
    if (str_ends_with($trim, '?>')) $trim=substr($trim,0,-2);
    if (file_put_contents($web, $trim."\n\n// Checker camera routes — V13 repair\n".$line."\n")===false) {
        fwrite(STDERR,"ERROR writing routes/web.php\n");exit(1);
    }
    echo "UPDATED routes/web.php\n";
} else {echo "ALREADY INCLUDED checker_camera_v13.php\n";}

// Validate route names in the actual Laravel runtime, after clearing stale route cache.
chdir($root);
$php=escapeshellarg(PHP_BINARY);
passthru($php.' artisan route:clear 2>&1', $clearExit);
if ($clearExit !== 0) {fwrite(STDERR,"ERROR route cache clearing failed.\n");exit(1);}
exec($php.' artisan route:list --json 2>&1', $output, $listExit);
if ($listExit !== 0) {
    fwrite(STDERR,"ERROR Laravel route:list failed:\n".implode("\n",$output)."\n");exit(1);
}
$routes=json_decode(implode("\n",$output),true);
if (!is_array($routes)) {
    fwrite(STDERR,"ERROR unexpected route:list output:\n".implode("\n",$output)."\n");exit(1);
}
$names=array_column($routes,'name');
$needed=['checker.camera-manual.store','checker.camera-ai.read','warehouse.legacy.camera-manual.store','warehouse.legacy.camera-ai.read'];
$missing=array_values(array_diff($needed,$names));
if ($missing) {
    fwrite(STDERR,"ERROR still missing routes: ".implode(', ',$missing)."\n");exit(1);
}
echo "SUCCESS: all four camera routes registered in this Laravel project.\n";
echo "Next: php artisan migrate && php artisan optimize:clear\n";
