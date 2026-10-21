[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string] $DumpPath,

    [Parameter(Mandatory = $true)]
    [string] $TargetDatabase,

    [string] $OriginalDatabase = 'barangay_donation_system',
    [string] $HostName = '127.0.0.1',
    [int] $Port = 3306,
    [string] $Username = 'root'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if ([string]::Equals($TargetDatabase, $OriginalDatabase, [StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing restore: target database '$TargetDatabase' is the original database."
}

$resolvedDump = (Resolve-Path -LiteralPath $DumpPath).Path
$sql = [IO.File]::ReadAllText($resolvedDump)
$unsafePatterns = @(
    @{ Pattern = '(?im)^\s*USE\s+'; Name = 'USE' },
    @{ Pattern = '(?im)^\s*CREATE\s+DATABASE\b'; Name = 'CREATE DATABASE' },
    @{ Pattern = '(?im)GTID_PURGED'; Name = 'GTID_PURGED' },
    @{ Pattern = '(?im)SET\s+@@GLOBAL\.GTID'; Name = 'GLOBAL GTID assignment' }
)

foreach ($unsafe in $unsafePatterns) {
    if ($sql -match $unsafe.Pattern) {
        throw "Refusing restore: dump contains unsafe directive $($unsafe.Name)."
    }
}

if ([string]::IsNullOrWhiteSpace($env:MYSQL_PWD)) {
    throw 'MYSQL_PWD must be supplied through the process environment; do not put passwords in this script.'
}

$mysql = Join-Path ${env:ProgramFiles} 'MySQL\MySQL Server 8.0\bin\mysql.exe'
if (-not (Test-Path -LiteralPath $mysql)) {
    throw "mysql client not found at $mysql"
}

$verify = & $mysql --batch --skip-column-names --host=$HostName --port=$Port --user=$Username --database=$TargetDatabase --execute='SELECT DATABASE()'
if ($LASTEXITCODE -ne 0 -or $verify.Trim() -ne $TargetDatabase) {
    throw "Refusing restore: connection target verification failed. Expected '$TargetDatabase', received '$($verify.Trim())'."
}

$arguments = "--host=$HostName --port=$Port --user=$Username --database=$TargetDatabase"
$startInfo = [Diagnostics.ProcessStartInfo]::new()
$startInfo.FileName = $mysql
$startInfo.Arguments = $arguments
$startInfo.UseShellExecute = $false
$startInfo.RedirectStandardInput = $true
$startInfo.RedirectStandardOutput = $true
$startInfo.RedirectStandardError = $true
$startInfo.Environment['MYSQL_PWD'] = $env:MYSQL_PWD
$process = [Diagnostics.Process]::new()
$process.StartInfo = $startInfo
[void] $process.Start()
$process.StandardInput.Write($sql)
$process.StandardInput.Close()
$stdout = $process.StandardOutput.ReadToEnd()
$stderr = $process.StandardError.ReadToEnd()
$process.WaitForExit()

if ($process.ExitCode -ne 0) {
    throw "MySQL restore failed for '$TargetDatabase': $stderr"
}

Write-Output "Validated and restored only into '$TargetDatabase'."
