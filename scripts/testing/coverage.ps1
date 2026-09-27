param(
    [string]$Filter = "",
    [string]$PhpExe = ""
)

# Resolve repo root from this script's location (scripts/testing/ -> repo root).
$root = (Resolve-Path (Join-Path $PSScriptRoot "..\..")).Path

# Locate PHP: explicit param > PATH > known FlyEnv location.
if (-not $PhpExe) {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) {
        $PhpExe = $cmd.Source
    } elseif (Test-Path "D:\Tools\FlyEnv-Data\env\php\php.exe") {
        $PhpExe = "D:\Tools\FlyEnv-Data\env\php\php.exe"
    } else {
        Write-Error "PHP executable not found. Pass -PhpExe <path>."
        exit 1
    }
}

# xdebug scan dir: use the in-repo ini (points at the machine's xdebug dll).
$scanDir = Join-Path $PSScriptRoot "phpini"
$clover = Join-Path $root "coverage-clover.xml"

$env:PHP_INI_SCAN_DIR = $scanDir

$args = @("artisan", "test", "--coverage-clover", $clover)
if ($Filter) { $args += "--filter=$Filter" }

Push-Location $root
& $PhpExe @args | Select-Object -Last 6
Pop-Location

if (-not (Test-Path $clover)) { Write-Output "NO CLOVER (is xdebug coverage enabled?)"; exit }

[xml]$x = Get-Content $clover
$totalStat = 0; $totalCov = 0
$rows = @()
foreach ($f in $x.SelectNodes("//file")) {
    $m = $f.metrics
    $totalStat += [int]$m.statements
    $totalCov += [int]$m.coveredstatements
    $rel = $f.name -replace [regex]::Escape("$root\"), "" -replace "\\", "/"
    $pct = if ([int]$m.statements -gt 0) { [math]::Round(100 * [int]$m.coveredstatements / [int]$m.statements, 1) } else { 100 }
    $rows += [pscustomobject]@{ File = $rel; Stmts = [int]$m.statements; Cov = [int]$m.coveredstatements; Pct = $pct }
}

$overall = if ($totalStat -gt 0) { [math]::Round(100 * $totalCov / $totalStat, 1) } else { 0 }
Write-Output ""
Write-Output "==================== COVERAGE SUMMARY ===================="
Write-Output ("OVERALL: {0}%  ({1}/{2} statements)" -f $overall, $totalCov, $totalStat)
Write-Output "=========================================================="
$rows | Sort-Object Pct, File | Format-Table -AutoSize | Out-String -Width 200 | Write-Output
