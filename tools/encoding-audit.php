<?php
declare(strict_types=1);

// Conservative UTF-8/Mojibake audit. Dry-run is the default; --repair only
// rewrites files where a Windows-1256 -> UTF-8 round-trip clearly reduces
// suspicious markers. Database content is intentionally never modified here.
$root=dirname(__DIR__); $repair=in_array('--repair',$argv,true); $backup=$root.'/storage/encoding-backups/'.date('Ymd-His');
$ext=['php','js','json','md','txt','css']; $marker='/ط·|ط¸|ط§ظ|ظ„|ظ…|â€|Ã|ï¿½/u'; $files=[];
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($it as $f){if(!$f->isFile()||str_contains($f->getPathname(),DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'encoding-backups'.DIRECTORY_SEPARATOR))continue;if(!in_array(strtolower($f->getExtension()),$ext,true))continue;$files[]=$f->getPathname();}
$suspicious=0;$changed=0;
foreach($files as $file){$raw=file_get_contents($file);if($raw===false||!mb_check_encoding($raw,'UTF-8'))continue;$before=preg_match_all($marker,$raw,$m)?:0;if($before===0)continue;$lines=preg_split('/(\r\n|\n|\r)/',$raw);$lineChanges=0;foreach($lines as &$line){$old=preg_match_all($marker,$line,$m)?:0;if($old===0)continue;$candidate=@iconv('CP1256','UTF-8//IGNORE',$line);$new=$candidate===false?$old:(preg_match_all($marker,$candidate,$m)?:0);if($candidate!==false&&$new<$old){$line=$candidate;$lineChanges++;}}unset($line);if(!$lineChanges)continue;$candidate=implode(PHP_EOL,$lines);$after=preg_match_all($marker,$candidate,$m)?:0;$suspicious++;echo ($repair?'REPAIR ':'CHECK '),str_replace($root.DIRECTORY_SEPARATOR,'',$file)," markers $before->$after lines=$lineChanges\n";if($repair){$relative=substr($file,strlen($root)+1);$dest=$backup.DIRECTORY_SEPARATOR.$relative;$dir=dirname($dest);if(!is_dir($dir))mkdir($dir,0777,true);copy($file,$dest);file_put_contents($file,$candidate);$changed++;}}
echo "Files scanned: ".count($files)."; candidates: $suspicious; changed: $changed\n";if($repair&&$changed)echo "Backups: $backup\n";
