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
function Add-Label($f, $text, $y, $h = 28, $bold = $false) {
    $l = New-Object System.Windows.Forms.Label
    $l.Text = $text; $l.Location = New-Object System.Drawing.Point(24, $y); $l.Size = New-Object System.Drawing.Size(500, $h)
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
    Add-Label $f "   Good for: slow or no internet, one computer, no hosting cost.`n   Keep in mind: no student/parent accounts; works only inside the school network." 98 70 | Out-Null
    $onl = New-Object System.Windows.Forms.RadioButton
    $onl.Text = 'Online (uses a MySQL database / web host)'
    $onl.Location = New-Object System.Drawing.Point(24, 176); $onl.Size = New-Object System.Drawing.Size(500, 30); $f.Controls.Add($onl)
    Add-Label $f "   Good for: parent and student accounts, email notifications.`n   Keep in mind: needs a MySQL database and someone to look after it." 208 70 | Out-Null
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
        $d = New-Form 'SmartSchool - database details' 420
        Add-Label $d 'Enter your MySQL database details' 20 32 $true | Out-Null
        $boxes = @{}; $y = 64
        foreach ($k in @('Host=127.0.0.1', 'Port=3306', 'Database=smartschool', 'User=smartschool', 'Password=', 'Website address=https://')) {
            $name, $def = $k -split '=', 2
            Add-Label $d $name ($y + 4) 26 | Out-Null
            $t = New-Object System.Windows.Forms.TextBox
            $t.Text = $def; $t.Location = New-Object System.Drawing.Point(190, $y); $t.Size = New-Object System.Drawing.Size(330, 30)
            if ($name -eq 'Password') { $t.UseSystemPasswordChar = $true }
            $d.Controls.Add($t); $boxes[$name] = $t; $y += 40
        }
        $b = New-Object System.Windows.Forms.Button
        $b.Text = 'Continue'; $b.Location = New-Object System.Drawing.Point(384, 320); $b.Size = New-Object System.Drawing.Size(140, 44)
        $b.DialogResult = 'OK'; $d.AcceptButton = $b; $d.Controls.Add($b)
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

# ---------- Start (every launch) ----------
$mode = Get-EnvValue 'SMARTSCHOOL_MODE'
$ip = if ($mode -eq 'lan') { Get-LanIp } else { '127.0.0.1' }
$url = "http://{0}:{1}" -f $ip, $Port
if ($mode -eq 'lan') { Set-EnvValue 'APP_URL' $url; Artisan @('config:cache') | Out-Null }

Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -like "*$App*" -or $_.ExecutablePath -eq $Php } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
$env:PHP_CLI_SERVER_WORKERS = '6'
Start-Process -FilePath $Php -ArgumentList @('artisan', 'serve', "--host=$ip", "--port=$Port") -WorkingDirectory $App -WindowStyle Hidden
Start-Process -FilePath $Php -ArgumentList @('artisan', 'schedule:work') -WorkingDirectory $App -WindowStyle Hidden
Start-Sleep -Seconds 3
Start-Process $url

$s = New-Form 'SmartSchool is running' 300
Add-Label $s 'SmartSchool is running' 20 32 $true | Out-Null
Add-Label $s 'Open this address on any phone or computer on the same Wi-Fi:' 64 28 | Out-Null
$a = Add-Label $s $url 98 36 $true
$a.ForeColor = [System.Drawing.Color]::FromArgb(31, 95, 70)
Add-Label $s 'First time? Log in and create your Superadmin. Write its email and password down - nobody can recover it for you. Keep this window open; closing it stops SmartSchool.' 140 70 | Out-Null
$s.Add_FormClosed({
    Get-CimInstance Win32_Process -Filter "Name='php.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.ExecutablePath -eq $Php } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
})
[System.Windows.Forms.Application]::Run($s)
