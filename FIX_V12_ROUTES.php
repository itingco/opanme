<?php
/** Recovery for V12 missing checker.camera-manual.store route. Run beside artisan. */
$root=__DIR__;
if(!is_file($root.'/artisan')) {fwrite(STDERR,"ERROR: extract package to Laravel root (beside artisan).\n");exit(1);}
$files = [
 'app/Http/Controllers/Checker/CameraManualController.php',
 'app/Http/Controllers/Checker/CameraAiReadController.php',
 'config/opanme_camera_ai.php',
 'database/migrations/2026_11_16_235900_create_opname_manual_scan_photos_table.php',
 'public/assets/js/checker-camera-v12.js',
];
foreach($files as $f){
  $source=$root.'/V12_RECOVERY_FILES/'.$f;
  $target=$root.'/'.$f;
  if(!is_file($target)) {
     if(!is_file($source)){fwrite(STDERR,"ERROR: missing payload $source\n");exit(1);}
     if(!is_dir(dirname($target))) mkdir(dirname($target),0775,true);
     if(!copy($source,$target)){fwrite(STDERR,"ERROR: cannot copy $f\n");exit(1);}
     echo "RESTORED $f\n";
  } else echo "EXISTS $f\n";
}
function addAfter(string $file,string $routeName,string $anchor,string $code): void {
    $old=file_get_contents($file);
    if($old===false){throw new RuntimeException("Cannot read $file");}
    if(preg_match('/->name\([\'\"]'.preg_quote($routeName,'/').'[\'\"]\)/',$old)) {echo "EXISTS $routeName\n";return;}
    $at=strpos($old,$anchor);
    if($at===false){throw new RuntimeException("Cannot locate insertion anchor for $routeName in $file; route not changed.");}
    $pos=$at+strlen($anchor);
    $updated=substr($old,0,$pos)."\n".$code.substr($old,$pos);
    $backup=$file.'.before-fix-v12-routes';
    if(!is_file($backup) && !copy($file,$backup)) throw new RuntimeException("Cannot backup $file");
    if(file_put_contents($file,$updated)===false) throw new RuntimeException("Cannot save $file");
    echo "ADDED $routeName\n";
}
try {
  $web=$root.'/routes/web.php';
  $warehouse=$root.'/routes/warehouse_sampling.php';
  if(!is_file($web)||!is_file($warehouse)) throw new RuntimeException('routes files not found');
  // Insert inside the existing checker role/middleware group.
  $anchorWeb="        )->name('scan.non-system');";
  addAfter($web,'camera-manual.store',$anchorWeb,
      "\n        Route::post('/sessions/{session}/manual-photo', [\\App\\Http\\Controllers\\Checker\\CameraManualController::class, 'store'])->name('camera-manual.store');");
  addAfter($web,'camera-ai.read',"->name('camera-manual.store');",
      "\n        Route::post('/sessions/{session}/camera-ai', [\\App\\Http\\Controllers\\Checker\\CameraAiReadController::class, '__invoke'])->name('camera-ai.read');");
  // Warehouse legacy uses the same controller but a separate route group.
  $anchorWarehouse="->name('scan.non-system');";
  addAfter($warehouse,'camera-manual.store',$anchorWarehouse,
      "\n    Route::post('/sessions/{session}/manual-photo', [\\App\\Http\\Controllers\\Checker\\CameraManualController::class, 'store'])->name('camera-manual.store');");
  addAfter($warehouse,'camera-ai.read',"->name('camera-manual.store');",
      "\n    Route::post('/sessions/{session}/camera-ai', [\\App\\Http\\Controllers\\Checker\\CameraAiReadController::class, '__invoke'])->name('camera-ai.read');");
  echo "Routes installed. Next: php artisan optimize:clear && php artisan route:list --name=camera\n";
} catch(Throwable $e){fwrite(STDERR,"ERROR: ".$e->getMessage()."\n");exit(1);}
