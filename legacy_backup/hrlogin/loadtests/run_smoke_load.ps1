param(
    [Parameter(Mandatory = $true)]
    [string]$BaseUrl,
    [Parameter(Mandatory = $true)]
    [string]$SessionCookie,
    [int]$Concurrent = 50,
    [int]$Requests = 200,
    [string]$Latitude = "12.9716",
    [string]$Longitude = "77.5946"
)

$BaseUrl = $BaseUrl.TrimEnd('/')
$uri = "$BaseUrl/punch_action.php"
$body = "action=punch_in&latitude=$Latitude&longitude=$Longitude&format=json"
$headers = @{
    "Content-Type"     = "application/x-www-form-urlencoded"
    "X-Requested-With" = "XMLHttpRequest"
    "Cookie"           = $SessionCookie
}

Write-Host "Smoke load: $Requests requests, concurrency $Concurrent -> $uri"

$success = 0
$failed = 0
$latencies = New-Object System.Collections.Generic.List[int]
$lock = New-Object object

$jobs = 1..$Requests | ForEach-Object {
    $idx = $_
    Start-Job -ScriptBlock {
        param($Uri, $Body, $Headers)
        $sw = [System.Diagnostics.Stopwatch]::StartNew()
        try {
            $resp = Invoke-WebRequest -Uri $Uri -Method POST -Body $Body -Headers $Headers -UseBasicParsing -TimeoutSec 30
            $sw.Stop()
            $json = $resp.Content | ConvertFrom-Json
            [PSCustomObject]@{
                Ok       = ($resp.StatusCode -eq 200 -and $json.success -eq $true)
                Ms       = [int]$sw.ElapsedMilliseconds
                Status   = $resp.StatusCode
                Error    = $null
            }
        } catch {
            $sw.Stop()
            [PSCustomObject]@{
                Ok     = $false
                Ms     = [int]$sw.ElapsedMilliseconds
                Status = 0
                Error  = $_.Exception.Message
            }
        }
    } -ArgumentList $uri, $body, $headers
}

while (($jobs | Where-Object { $_.State -eq 'Running' }).Count -gt $Concurrent) {
    Start-Sleep -Milliseconds 200
}

$results = $jobs | Wait-Job | Receive-Job
$jobs | Remove-Job

foreach ($r in $results) {
    if ($r.Ok) { $success++ } else { $failed++ }
    $latencies.Add($r.Ms)
}

$sorted = $latencies | Sort-Object
$p95Index = [Math]::Max(0, [int][Math]::Ceiling($sorted.Count * 0.95) - 1)
$p95 = if ($sorted.Count -gt 0) { $sorted[$p95Index] } else { 0 }

Write-Host ""
Write-Host "Results"
Write-Host "-------"
Write-Host "Success: $success"
Write-Host "Failed:  $failed"
Write-Host "p95 ms:  $p95"
Write-Host "Fail rate: $([Math]::Round(($failed / [Math]::Max(1, $Requests)) * 100, 2))%"

if ($failed -gt 0) {
    exit 1
}
