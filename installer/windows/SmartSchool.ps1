# SmartSchool launcher for Windows.
# First launch: asks Offline (LAN) or Online, prepares the database, then starts SmartSchool.
# Later launches: just starts it and shows the address. Mode is stored ONLY in .env (SMARTSCHOOL_MODE).
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Windows.Forms, System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

$Root = Split-Path -Parent $MyInvocation.MyCommand.Path
$App  = Join-Path $Root 'app'
$Php  = Join-Path $Root 'php\php.exe'
$Env  = Join-Path $App '.env'
$Port = 8000
$Font = New-Object System.Drawing.Font('Segoe UI', 11)

function Artisan([string[]]$a) {
    $p = Start-Process -FilePath $Php -ArgumentList (@('artisan') + $a) -WorkingDirectory $App -NoNewWindow -Wait -PassThru `
        -RedirectStandardOutput (Join-Path $App 'storage\logs\setup.log') -RedirectStandardError (Join-Path $App 'storage\logs\setup-error.log')
    return $p.ExitCode
}
function Set-EnvValue($k, $v) {
    $lines = if (Test-Path $Env) { @(Get-Content $Env) } else { @() }
    $found = $false
    $lines = $lines | ForEach-Object { if ($_ -match "^$k=") { $found = $true; "$k=$v" } else { $_ } }
    if (-not $found) { $lines += "$k=$v" }
    Set-Content -Path $Env -Value $lines -Encoding UTF8
}
function Get-EnvValue($k) { if (Test-Path $Env) { (Get-Content $Env | Where-Object { $_ -match "^$k=" } | Select-Object -First 1) -replace "^$k=", '' } }
function Get-LanIp {
    $ip = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.PrefixOrigin -ne 'WellKnown' } |
        Sort-Object InterfaceMetric | Select-Object -First 1 -ExpandProperty IPAddress
    if ($ip) { $ip } else { '127.0.0.1' }
}
function New-Form($title, $h) {
    $f = New-Object System.Windows.Forms.Form
    $f.Text = $title; $f.Size = New-Object System.Drawing.Size(560, $h); $f.StartPosition = 'CenterScreen'
    $f.FormBorderStyle = 'FixedDialog'; $f.MaximizeBox = $false; $f.BackColor = 'White'; $f.Font = $Font
    $f
}
function Add-Label($f, $text, $y, $h = 28, $bold = $false, $w = 500) {
    $l = New-Object System.Windows.Forms.Label
    $l.Text = $text; $l.Location = New-Object System.Drawing.Point(24, $y); $l.Size = New-Object System.Drawing.Size($w, $h)
    if ($bold) { $l.Font = New-Object System.Drawing.Font('Segoe UI', 13, [System.Drawing.FontStyle]::Bold) }
    $f.Controls.Add($l); $l
}

# ---------- First launch: choose the mode ----------
if (-not (Get-EnvValue 'SMARTSCHOOL_MODE')) {
    $f = New-Form 'SmartSchool - first-time setup' 520
    Add-Label $f 'How will your school use SmartSchool?' 20 32 $true | Out-Null
    $lan = New-Object System.Windows.Forms.RadioButton
    $lan.Text = 'Offline on school Wi-Fi (LAN)  - recommended'; $lan.Checked = $true
    $lan.Location = New-Object System.Drawing.Point(24, 66); $lan.Size = New-Object System.Drawing.Size(500, 30); $f.Controls.Add($lan)
    Add-Label $f "   Staff do all the clerk work (registering students).`n   Opens on campus only - no student/parent accounts." 98 70 | Out-Null
    $onl = New-Object System.Windows.Forms.RadioButton
    $onl.Text = 'Online (uses a MySQL database / web host)'
    $onl.Location = New-Object System.Drawing.Point(24, 176); $onl.Size = New-Object System.Drawing.Size(500, 30); $f.Controls.Add($onl)
    Add-Label $f "   Families apply from home; students and parents get their own accounts.`n   Needs a web host and a MySQL database." 208 70 | Out-Null
    Add-Label $f 'You can change this later in Settings (Superadmin only).' 296 28 | Out-Null
    $ok = New-Object System.Windows.Forms.Button
    $ok.Text = 'Continue'; $ok.Location = New-Object System.Drawing.Point(384, 410); $ok.Size = New-Object System.Drawing.Size(140, 44)
    $ok.DialogResult = 'OK'; $f.AcceptButton = $ok; $f.Controls.Add($ok)
    if ($f.ShowDialog() -ne 'OK') { exit }
    $mode = if ($lan.Checked) { 'lan' } else { 'hosted' }

    if (-not (Test-Path $Env)) { Copy-Item (Join-Path $App '.env.example') $Env }
    Set-EnvValue 'APP_ENV' 'production'; Set-EnvValue 'APP_DEBUG' 'false'
    Set-EnvValue 'SMARTSCHOOL_MODE' $mode

    if ($mode -eq 'lan') {
        $db = Join-Path $App 'database\database.sqlite'
        if (-not (Test-Path $db)) { New-Item -ItemType File $db | Out-Null }
        Set-EnvValue 'DB_CONNECTION' 'sqlite'; Set-EnvValue 'DB_DATABASE' $db
        Set-EnvValue 'APP_URL' ("http://{0}:{1}" -f (Get-LanIp), $Port)
    } else {
        $guide = 'https://smartschoolinstallation.vercel.app/#hosting'
        $q = "Online mode runs SmartSchool on a web server so families can use it from home.`n`nThe easiest way: rent a server (VPS) and run the one-command installer there - it sets up the database, website and HTTPS for you.`n`nDo you already have a MySQL database you want this computer to use?`n`nYes - enter the database details now.`nNo - open the step-by-step hosting guide."
        if ([System.Windows.Forms.MessageBox]::Show($q, 'SmartSchool - Online', 'YesNo', 'Question') -ne 'Yes') {
            Remove-Item $Env -ErrorAction SilentlyContinue
            Start-Process $guide
            exit
        }
        $d = New-Form 'SmartSchool - database details' 460
        Add-Label $d 'Enter your MySQL database details' 20 32 $true | Out-Null
        Add-Label $d 'Your hosting company gives you these. See "Hosting & database" on the SmartSchool website.' 300 40 | Out-Null
        $boxes = @{}; $y = 64
        foreach ($k in @('Host=127.0.0.1', 'Port=3306', 'Database=smartschool', 'User=smartschool', 'Password=', 'Website address=https://')) {
            $name, $def = $k -split '=', 2
            Add-Label $d $name ($y + 4) 26 $false 160 | Out-Null
            $t = New-Object System.Windows.Forms.TextBox
            $t.Text = $def; $t.Location = New-Object System.Drawing.Point(190, $y); $t.Size = New-Object System.Drawing.Size(330, 30); $t.BorderStyle = 'FixedSingle'
            if ($name -eq 'Password') { $t.UseSystemPasswordChar = $true }
            $d.Controls.Add($t); $boxes[$name] = $t; $y += 40
        }
        $b = New-Object System.Windows.Forms.Button
        $b.Text = 'Continue'; $b.Location = New-Object System.Drawing.Point(384, 350); $b.Size = New-Object System.Drawing.Size(140, 44)
        $b.DialogResult = 'OK'; $d.AcceptButton = $b; $d.Controls.Add($b)
        foreach ($t in $boxes.Values) { $t.BringToFront() }
        if ($d.ShowDialog() -ne 'OK') { Remove-Item $Env; exit }
        Set-EnvValue 'DB_CONNECTION' 'mysql'
        Set-EnvValue 'DB_HOST' $boxes['Host'].Text; Set-EnvValue 'DB_PORT' $boxes['Port'].Text
        Set-EnvValue 'DB_DATABASE' $boxes['Database'].Text; Set-EnvValue 'DB_USERNAME' $boxes['User'].Text
        Set-EnvValue 'DB_PASSWORD' ('"' + $boxes['Password'].Text + '"'); Set-EnvValue 'APP_URL' $boxes['Website address'].Text
    }

    $wait = New-Form 'SmartSchool' 160
    Add-Label $wait 'Preparing SmartSchool... this takes about a minute.' 40 30 | Out-Null
    $wait.Show(); $wait.Refresh()
    $ok1 = (Artisan @('key:generate', '--force')) -eq 0
    $ok2 = $ok1 -and ((Artisan @('migrate', '--force')) -eq 0)
    if ($ok2) { Artisan @('db:seed', '--force') | Out-Null; Artisan @('config:cache') | Out-Null; Artisan @('view:cache') | Out-Null }
    $wait.Close()
    if (-not $ok2) {
        Remove-Item $Env -ErrorAction SilentlyContinue
        [System.Windows.Forms.MessageBox]::Show("The database could not be prepared. Check the details and try again.`nDetails: $App\storage\logs\setup-error.log", 'SmartSchool', 'OK', 'Error') | Out-Null
        exit 1
    }
}

# ---------- Control panel (every launch) ----------
$mode = Get-EnvValue 'SMARTSCHOOL_MODE'
$script:ip = if ($mode -eq 'lan') { Get-LanIp } else { '127.0.0.1' }
$script:url = "http://{0}:{1}" -f $script:ip, $Port
$script:procs = @()

function Stop-Server {
    foreach ($p in $script:procs) { try { if (-not $p.HasExited) { Stop-Process -Id $p.Id -Force } } catch {} }
    $script:procs = @()
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.ExecutablePath -eq $Php } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}
function Start-Server {
    Stop-Server
    if ($mode -eq 'lan') {
        $script:ip = Get-LanIp
        $script:url = "http://{0}:{1}" -f $script:ip, $Port
        Set-EnvValue 'APP_URL' $script:url
        Artisan @('config:cache') | Out-Null
    }
    $env:PHP_CLI_SERVER_WORKERS = '6'
    $script:procs = @(
        (Start-Process -FilePath $Php -ArgumentList @('artisan', 'serve', "--host=$($script:ip)", "--port=$Port") -WorkingDirectory $App -WindowStyle Hidden -PassThru),
        (Start-Process -FilePath $Php -ArgumentList @('artisan', 'schedule:work') -WorkingDirectory $App -WindowStyle Hidden -PassThru)
    )
}
function Test-Web {
    try { (Invoke-WebRequest -Uri "$($script:url)/health" -UseBasicParsing -TimeoutSec 2).StatusCode -eq 200 } catch { $false }
}

# The Windows firewall only lets other devices in on a Private network.
$public = @(Get-NetConnectionProfile -ErrorAction SilentlyContinue | Where-Object { $_.NetworkCategory -eq 'Public' })
if ($mode -eq 'lan' -and $public.Count -gt 0) {
    $msg = "This computer's network ($($public[0].Name)) is set to Public, so phones and other computers can't reach SmartSchool.`n`nMake it Private now? (Windows will ask for permission.)`n`nOn every other Windows computer that will use SmartSchool, also set the school network (Wi-Fi or cable) to Private."
    if ([System.Windows.Forms.MessageBox]::Show($msg, 'SmartSchool - network', 'YesNo', 'Warning') -eq 'Yes') {
        $ids = ($public | ForEach-Object { $_.InterfaceIndex }) -join ','
        try { Start-Process powershell.exe -Verb RunAs -Wait -ArgumentList "-NoProfile -Command Set-NetConnectionProfile -InterfaceIndex $ids -NetworkCategory Private" } catch {}
    }
}

# ---- window
$green = [System.Drawing.Color]::FromArgb(22, 163, 74); $amber = [System.Drawing.Color]::FromArgb(217, 119, 6)
$red = [System.Drawing.Color]::FromArgb(220, 38, 38); $muted = [System.Drawing.Color]::FromArgb(100, 116, 139)
$ink = [System.Drawing.Color]::FromArgb(17, 24, 39); $line = [System.Drawing.Color]::FromArgb(229, 231, 235)
$s = New-Object System.Windows.Forms.Form
$s.Text = 'SmartSchool Server'; $s.ClientSize = New-Object System.Drawing.Size(560, 470); $s.StartPosition = 'CenterScreen'
$s.FormBorderStyle = 'FixedSingle'; $s.MaximizeBox = $false; $s.BackColor = 'White'; $s.Font = $Font; $s.AutoScaleMode = 'Dpi'

function New-Dot($x, $y, $size) {
    $d = New-Object System.Windows.Forms.Panel
    $d.Location = New-Object System.Drawing.Point($x, $y); $d.Size = New-Object System.Drawing.Size($size, $size)
    $gp = New-Object System.Drawing.Drawing2D.GraphicsPath; $gp.AddEllipse(0, 0, $size, $size)
    $d.Region = New-Object System.Drawing.Region($gp); $d.BackColor = $muted
    $s.Controls.Add($d); $d
}
function New-Text($text, $x, $y, $w, $h, $size = 11, $bold = $false, $color = $ink) {
    $l = New-Object System.Windows.Forms.Label
    $l.Text = $text; $l.Location = New-Object System.Drawing.Point($x, $y); $l.Size = New-Object System.Drawing.Size($w, $h)
    $l.Font = New-Object System.Drawing.Font('Segoe UI', $size, $(if ($bold) { [System.Drawing.FontStyle]::Bold } else { [System.Drawing.FontStyle]::Regular }))
    $l.ForeColor = $color; $s.Controls.Add($l); $l
}
function New-Button($text, $x, $y, $w, $primary = $false) {
    $b = New-Object System.Windows.Forms.Button
    $b.Text = $text; $b.Location = New-Object System.Drawing.Point($x, $y); $b.Size = New-Object System.Drawing.Size($w, 44)
    $b.FlatStyle = 'Flat'; $b.Cursor = 'Hand'
    if ($primary) { $b.BackColor = [System.Drawing.Color]::FromArgb(31, 95, 70); $b.ForeColor = 'White'; $b.FlatAppearance.BorderSize = 0 }
    else { $b.BackColor = 'White'; $b.FlatAppearance.BorderColor = $line }
    $s.Controls.Add($b); $b
}

# header status
$bigDot = New-Dot 28 30 18
$title = New-Text 'Starting...' 56 22 460 34 16 $true
$sub = New-Text 'Please wait a moment.' 56 56 470 24 10 $false $muted

# indicators
$sep1 = New-Object System.Windows.Forms.Label; $sep1.Location = New-Object System.Drawing.Point(28, 92); $sep1.Size = New-Object System.Drawing.Size(504, 1); $sep1.BackColor = $line; $s.Controls.Add($sep1)
$webDot = New-Dot 30 112 12;  New-Text 'Website' 52 105 160 26 | Out-Null; $webState = New-Text '' 220 105 310 26 11 $false $muted
$jobDot = New-Dot 30 146 12;  New-Text 'Background jobs' 52 139 160 26 | Out-Null; $jobState = New-Text '' 220 139 310 26 11 $false $muted
$netDot = New-Dot 30 180 12;  New-Text 'Network' 52 173 160 26 | Out-Null; $netState = New-Text '' 220 173 310 26 11 $false $muted

# address
$sep2 = New-Object System.Windows.Forms.Label; $sep2.Location = New-Object System.Drawing.Point(28, 214); $sep2.Size = New-Object System.Drawing.Size(504, 1); $sep2.BackColor = $line; $s.Controls.Add($sep2)
New-Text 'Open this address on any phone or computer on the school network:' 28 226 504 24 10 $false $muted | Out-Null
$addr = New-Text $script:url 28 252 330 34 15 $true ([System.Drawing.Color]::FromArgb(31, 95, 70))
$copy = New-Button 'Copy' 372 248 76
$open = New-Button 'Open' 456 248 76

$hint = New-Text 'Every device must use the same network (Wi-Fi or cable) set to Private. Keep this window open - closing it shuts SmartSchool down.' 28 298 504 48 10 $false $muted

# controls
$restart = New-Button 'Restart' 28 380 150
$power = New-Button 'Shut down' 190 380 150
$logs = New-Button 'Open logs' 352 380 180

$copy.Add_Click({ [System.Windows.Forms.Clipboard]::SetText($script:url); $copy.Text = 'Copied' })
$open.Add_Click({ Start-Process $script:url })
$logs.Add_Click({ Start-Process explorer.exe (Join-Path $App 'storage\logs') })

$script:wanted = $true   # should the server be running?
$script:startedAt = Get-Date
function Set-Dot($d, $c) { $d.BackColor = $c }

function Update-Status {
    $alive = @($script:procs | Where-Object { $_ -and -not $_.HasExited })
    $webUp = $script:wanted -and (Test-Web)
    $jobsUp = $script:wanted -and $alive.Count -ge 2
    $starting = $script:wanted -and -not $webUp -and ((Get-Date) - $script:startedAt).TotalSeconds -lt 20

    if (-not $script:wanted) {
        Set-Dot $bigDot $red; $title.Text = 'SmartSchool is stopped'; $sub.Text = 'Nobody can open SmartSchool until you start it again.'
        $power.Text = 'Start'; $power.BackColor = [System.Drawing.Color]::FromArgb(31, 95, 70); $power.ForeColor = 'White'
    } elseif ($webUp) {
        Set-Dot $bigDot $green; $title.Text = 'SmartSchool is running'; $sub.Text = 'Running since ' + $script:startedAt.ToString('h:mm tt') + '.'
        $power.Text = 'Shut down'; $power.BackColor = 'White'; $power.ForeColor = $ink
    } elseif ($starting) {
        Set-Dot $bigDot $amber; $title.Text = 'Starting...'; $sub.Text = 'Please wait a moment.'
    } else {
        Set-Dot $bigDot $red; $title.Text = 'SmartSchool is not responding'; $sub.Text = 'Click Restart. If it keeps happening, click Open logs.'
    }
    Set-Dot $webDot $(if ($webUp) { $green } elseif ($starting) { $amber } else { $red })
    $webState.Text = if ($webUp) { 'Online' } elseif ($starting) { 'Starting' } elseif ($script:wanted) { 'Not responding' } else { 'Stopped' }
    Set-Dot $jobDot $(if ($jobsUp) { $green } elseif ($script:wanted) { $amber } else { $red })
    $jobState.Text = if ($jobsUp) { 'Running (reminders, backups)' } elseif ($script:wanted) { 'Starting' } else { 'Stopped' }
    $prof = @(Get-NetConnectionProfile -ErrorAction SilentlyContinue)
    $isPublic = @($prof | Where-Object { $_.NetworkCategory -eq 'Public' }).Count -gt 0
    if ($mode -ne 'lan') { Set-Dot $netDot $green; $netState.Text = 'This computer only' }
    elseif ($prof.Count -eq 0) { Set-Dot $netDot $red; $netState.Text = 'Not connected' }
    elseif ($isPublic) { Set-Dot $netDot $amber; $netState.Text = 'Public - other devices are blocked' }
    else { Set-Dot $netDot $green; $netState.Text = 'Private - ' + $prof[0].Name }
    $addr.Text = $script:url
}

$restart.Add_Click({
    $restart.Enabled = $false; $script:wanted = $true; $script:startedAt = Get-Date
    Set-Dot $bigDot $amber; $title.Text = 'Restarting...'; $s.Refresh()
    Start-Server; Update-Status; $restart.Enabled = $true
})
$power.Add_Click({
    if ($script:wanted) {
        if ([System.Windows.Forms.MessageBox]::Show('Shut SmartSchool down? Nobody will be able to use it until you start it again.', 'SmartSchool', 'YesNo', 'Question') -ne 'Yes') { return }
        $script:wanted = $false; Stop-Server
    } else {
        $script:wanted = $true; $script:startedAt = Get-Date; Start-Server
    }
    Update-Status
})

$timer = New-Object System.Windows.Forms.Timer
$timer.Interval = 4000
$timer.Add_Tick({ Update-Status })

$s.Add_Shown({
    Start-Server; Update-Status; $timer.Start()
    $waited = 0
    while (-not (Test-Web) -and $waited -lt 15) { Start-Sleep -Milliseconds 500; $waited += 0.5; [System.Windows.Forms.Application]::DoEvents() }
    Update-Status
    if (Test-Web) { Start-Process $script:url }
})
$s.Add_FormClosing({
    param($sender, $e)
    if ($script:wanted -and [System.Windows.Forms.MessageBox]::Show('Closing this window shuts SmartSchool down for everyone. Close it?', 'SmartSchool', 'YesNo', 'Warning') -ne 'Yes') { $e.Cancel = $true; return }
    $timer.Stop(); Stop-Server
})
[System.Windows.Forms.Application]::Run($s)
