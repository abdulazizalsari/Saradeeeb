param([switch]$Repair)
$root=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$backup=Join-Path $root ('storage\encoding-backups\'+(Get-Date -Format 'yyyyMMdd-HHmmss'))
$cp=[Text.Encoding]::GetEncoding(1256);$utf=[Text.Encoding]::UTF8
$files=Get-ChildItem (Join-Path $root 'app'),(Join-Path $root 'assets'),(Join-Path $root 'config'),(Join-Path $root 'database'),(Join-Path $root 'scripts'),(Join-Path $root 'tests') -Recurse -File -ErrorAction SilentlyContinue | Where-Object {$_.Extension -in '.php','.js','.json','.md','.txt','.css'}
$rx="\u0637\u00b7|\u0637\u00b8|\u0637\u00a7\u0638|\u0638\u201e|\u0638\u2026|\u00e2|\u00c3|\ufffd";$candidates=0;$changed=0
foreach($f in $files){$lines=Get-Content -LiteralPath $f.FullName -Encoding UTF8;$out=[Collections.Generic.List[string]]::new();$fileChanged=$false;$before=0;$after=0
 foreach($line in $lines){$a=([regex]::Matches($line,$rx)).Count;$before+=$a;if($a -gt 0){$candidate=$utf.GetString($cp.GetBytes($line));$b=([regex]::Matches($candidate,$rx)).Count;if($b -lt $a){$line=$candidate;$fileChanged=$true};$after+=$b}else{$after+=0};$out.Add($line)}
 if($fileChanged -and $after -lt $before){$candidates++;$rel=$f.FullName.Substring($root.Length+1);$mode=if($Repair){"REPAIR"}else{"CHECK"};Write-Output "$mode $rel markers $before->$after";if($Repair){$dest=Join-Path $backup $rel;New-Item -ItemType Directory -Force (Split-Path $dest) | Out-Null;Copy-Item $f.FullName $dest;[IO.File]::WriteAllText($f.FullName,($out -join [Environment]::NewLine),[Text.UTF8Encoding]::new($false));$changed++}}}
Write-Output "Files scanned: $($files.Count); candidates: $candidates; changed: $changed";if($Repair -and $changed){Write-Output "Backups: $backup"}
