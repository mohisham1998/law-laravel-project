# Run PHPUnit tests and capture all output to logs
# Usage: .\scripts\run-tests-with-logging.ps1
# Output: storage/logs/test-results.log

$projectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$logDir = Join-Path $projectRoot "storage\logs"
$logFile = Join-Path $logDir "test-results.log"

# Ensure log directory exists
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

# Header
$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
$header = @"
================================================================================
TEST RUN - $timestamp
================================================================================

"@

# Run tests and capture output
Push-Location $projectRoot
try {
    $output = php artisan test 2>&1
    $exitCode = $LASTEXITCODE
} finally {
    Pop-Location
}

# Build log content
$summary = @"

--------------------------------------------------------------------------------
SUMMARY - Exit Code: $exitCode | Completed: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
--------------------------------------------------------------------------------
"@

$fullLog = $header + $output + $summary

# Write to log file
$fullLog | Out-File -FilePath $logFile -Encoding utf8

# Also write to console
Write-Host $output
Write-Host "`n[LOG] Full output written to: $logFile" -ForegroundColor Cyan

exit $exitCode
