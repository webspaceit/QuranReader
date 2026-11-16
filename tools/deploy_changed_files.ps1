param(
    [Parameter(Mandatory = $true)][string]$ProjectDir,
    [Parameter(Mandatory = $true)][string]$Server,
    [Parameter(Mandatory = $true)][string]$Key,
    [Parameter(Mandatory = $true)][string]$RemoteBase,
    [Parameter(Mandatory = $true)][string]$RemoteState,
    [Parameter(Mandatory = $true)][string]$InitialCommit,
    [int]$Attempts = 4,
    [int]$RetryDelaySeconds = 5
)

$ErrorActionPreference = 'Stop'

# -----------------------------------------------------------------------------
# Deploys the project tree to the shared host, mirroring the oop-rms workflow:
#
#   * the committed tree is the source of truth — nothing untracked is uploaded
#   * a marker file on the server records the last deployed commit
#   * first deployment (no marker) packs the whole tree into a tar and unpacks
#     it remotely; later deployments upload only the files that changed
#   * the marker only advances after every file succeeded, so a failed run
#     retries exactly the pending files
#
# Never uploaded: secrets (.env, app/config/config.php), logs, caches, tests,
# local archives and QA screenshots.
# -----------------------------------------------------------------------------

$SshOptions = @(
    '-o', 'BatchMode=yes',
    '-o', 'ConnectTimeout=15',
    '-o', 'ConnectionAttempts=1',
    '-o', 'ServerAliveInterval=15',
    '-o', 'ServerAliveCountMax=3',
    '-o', 'StrictHostKeyChecking=accept-new',
    '-4'
)

function Invoke-Remote {
    param(
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string]$What,
        [int]$MaxAttempts = $Attempts
    )

    $err = ''
    for ($i = 1; $i -le $MaxAttempts; $i++) {
        $output = & ssh -i $Key @SshOptions $Server $Command 2>&1
        if ($LASTEXITCODE -eq 0) {
            return [pscustomobject]@{ Ok = $true; Output = $output }
        }
        $err = (($output | Out-String).Trim())
        Write-Warning ("SSH attempt {0}/{1} failed for {2}: {3}" -f $i, $MaxAttempts, $What, $err)
        if ($i -lt $MaxAttempts) { Start-Sleep -Seconds ($RetryDelaySeconds * $i) }
    }

    return [pscustomobject]@{ Ok = $false; Output = $err }
}

function Send-File {
    param(
        [Parameter(Mandatory = $true)][string]$LocalPath,
        [Parameter(Mandatory = $true)][string]$RemotePath,
        [int]$MaxAttempts = $Attempts
    )

    for ($i = 1; $i -le $MaxAttempts; $i++) {
        $output = & scp -O -i $Key @SshOptions $LocalPath "${Server}:$RemotePath" 2>&1
        if ($LASTEXITCODE -eq 0) { return $true }
        $err = (($output | Out-String).Trim())
        Write-Warning ("SCP attempt {0}/{1} failed for {2}: {3}" -f $i, $MaxAttempts, $RemotePath, $err)
        if ($i -lt $MaxAttempts) { Start-Sleep -Seconds ($RetryDelaySeconds * $i) }
    }

    return $false
}

$head = (& git -C $ProjectDir rev-parse HEAD).Trim()
if ($LASTEXITCODE -ne 0 -or $head -notmatch '^[0-9a-f]{40}$') {
    throw 'Could not read the current Git commit.'
}

# ---- preflight ---------------------------------------------------------------
$ping = Invoke-Remote -Command 'echo DEPLOY_LINK_OK' -What 'connectivity preflight' -MaxAttempts 2
if (-not $ping.Ok -or (($ping.Output | Out-String) -notmatch 'DEPLOY_LINK_OK')) {
    $detail = (($ping.Output | Out-String).Trim())
    throw ("Cannot reach {0} over SSH. Nothing was uploaded and the deploy marker still points at the previous commit. " +
        "Check the hosting firewall / IP allow-list and that port 22 is open on this network. Detail: {1}") -f $Server, $detail
}

$stateCommand = "if [ -f '$RemoteState' ]; then cat '$RemoteState'; else printf '%s\n' '$InitialCommit'; fi"
$stateResult = Invoke-Remote -Command $stateCommand -What 'read deploy marker'
if (-not $stateResult.Ok) {
    throw ("SSH is reachable, but the marker at {0} could not be read. Nothing was uploaded. Detail: {1}") -f $RemoteState, (($stateResult.Output | Out-String).Trim())
}

$base = ((($stateResult.Output | Out-String)).Trim())
if ($base -notmatch '^[0-9a-f]{40}$') {
    throw "Invalid deployed commit marker returned by hosting: $base"
}

# Paths that must never leave this machine (or are irrelevant on the host).
$skip = '^(\.git|\.gitignore$|\.gitattributes$|\.env|app/config/config\.php$|config/config\.php$|' +
        'storage/logs/(?!\.gitignore$)|storage/cache/(?!\.gitignore$)|qa/|tools/|delivery/|browser-results/|' +
        'push-project\.bat$|.*\.zip$|database/[^/]*\.sql$|README\.md$|SECURITY\.md$|DEPLOYMENT\.md$)'

$files = @(& git -C $ProjectDir ls-tree -r --name-only HEAD)
if ($LASTEXITCODE -ne 0) { throw 'Could not list the committed files.' }

$files = $files | Where-Object { $_ -and ($_ -notmatch $skip) }
if ($files.Count -eq 0) { throw 'No deployable files found in the commit.' }

$firstDeploy = ($base -eq $InitialCommit)

if ($firstDeploy) {
    # ---- full upload in one tar: fast, and creates the remote tree in place ---
    Write-Host ("Full deployment: {0} file(s) from commit {1}" -f $files.Count, $head)

    $listFile = Join-Path $env:TEMP 'quranreader-deploy-list.txt'
    $tarFile = Join-Path $env:TEMP 'quranreader-deploy.tar.gz'
    $files | Set-Content -Path $listFile -Encoding ASCII

    & tar -czf $tarFile -C $ProjectDir -T $listFile
    if ($LASTEXITCODE -ne 0) { throw 'Could not build the deployment archive locally.' }

    $remoteTar = "/tmp/quranreader-deploy-$([guid]::NewGuid().ToString('N').Substring(0,8)).tar.gz"
    if (-not (Send-File -LocalPath $tarFile -RemotePath $remoteTar)) {
        Remove-Item $listFile, $tarFile -Force -ErrorAction SilentlyContinue
        throw 'Uploading the deployment archive failed. Nothing was changed on the server.'
    }
    Remove-Item $listFile, $tarFile -Force -ErrorAction SilentlyContinue

    $unpack = "mkdir -p '$RemoteBase' && tar -xzf '$remoteTar' -C '$RemoteBase' && rm -f '$remoteTar' && echo EXTRACT_OK"
    $result = Invoke-Remote -Command $unpack -What 'extract deployment archive'
    if (-not $result.Ok -or (($result.Output | Out-String) -notmatch 'EXTRACT_OK')) {
        throw ("Archive uploaded to {0} but extraction failed. Detail: {1}") -f $remoteTar, (($result.Output | Out-String).Trim())
    }

    $uploaded = $files.Count
    $removed = 0
} else {
    # ---- incremental: only files that changed since the last deployment ------
    $changes = @(& git -C $ProjectDir diff --name-status --no-renames $base $head)
    if ($LASTEXITCODE -ne 0) {
        throw "Could not compare deployed commit $base with local commit $head."
    }

    $uploaded = 0
    $removed = 0
    $failed = @()
    $pendingDirs = @{}

    foreach ($line in $changes) {
        if ([string]::IsNullOrWhiteSpace($line)) { continue }
        $parts = $line -split "`t", 2
        if ($parts.Count -ne 2) { throw "Unexpected git diff entry: $line" }
        $status = $parts[0]
        $relative = $parts[1].Replace('\', '/')

        if ($relative -notmatch '^[A-Za-z0-9_./-]+$' -or $relative -match '(^/|(^|/)\.\.(/|$))') {
            throw "Refusing unsafe project path: $relative"
        }
        if ($relative -match $skip) { continue }

        $remote = $RemoteBase.TrimEnd('/') + '/' + $relative

        if ($status -eq 'D') {
            Write-Host "Removing $relative"
            $del = Invoke-Remote -Command "rm -f -- '$remote'" -What "delete $relative"
            if (-not $del.Ok) { $failed += "delete $relative"; continue }
            $removed++
            continue
        }

        $local = Join-Path $ProjectDir ($relative.Replace('/', '\'))
        if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
            throw "Changed file is missing locally: $relative"
        }

        $parent = $remote.Substring(0, $remote.LastIndexOf('/'))
        if (-not $pendingDirs.ContainsKey($parent)) {
            $mkdir = Invoke-Remote -Command "mkdir -p -- '$parent'" -What "prepare folder for $relative"
            if (-not $mkdir.Ok) { $failed += "mkdir for $relative"; continue }
            $pendingDirs[$parent] = $true
        }

        Write-Host "Uploading $relative"
        if (-not (Send-File -LocalPath $local -RemotePath $remote)) {
            $failed += $relative
            continue
        }
        $uploaded++
    }

    if ($failed.Count -gt 0) {
        throw ("Uploaded {0}, removed {1}, but {2} item(s) failed: {3}. The deploy marker was not advanced, so rerunning retries the pending files." -f $uploaded, $removed, $failed.Count, ($failed -join ', '))
    }
}

$markerCommand = "printf '%s\n' '$head' > '$RemoteState.tmp' && mv '$RemoteState.tmp' '$RemoteState'"
$marker = Invoke-Remote -Command $markerCommand -What 'advance deploy marker'
if (-not $marker.Ok) {
    throw ("All {0} file(s) transferred, but the deploy marker could not be advanced. Rerunning would re-upload the same files, which is safe." -f $uploaded)
}

Write-Host "DEPLOY_OK: $uploaded file(s) uploaded, $removed removed; commit $head"
