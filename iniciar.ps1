param()
$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$portablePhp = Join-Path $projectRoot '.runtime/php/php.exe'
$phpCommand = if (Test-Path -LiteralPath $portablePhp) { $portablePhp } else { (Get-Command php -ErrorAction Stop).Source }
$phpVersion = & $phpCommand -r 'echo PHP_VERSION;'
if ([version]$phpVersion -lt [version]'8.3') { throw 'Se requiere PHP 8.3 o superior. El PHP de XAMPP 8.2 no puede ejecutar este Laravel.' }
$env:PATH = (Split-Path $phpCommand) + ';' + $env:PATH
$logDirectory = Join-Path $projectRoot '.local'
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
$backendDirectory = Join-Path $projectRoot 'backend'
$frontendDirectory = Join-Path $projectRoot 'frontend'
if (!(Test-Path (Join-Path $backendDirectory 'vendor/autoload.php'))) { throw 'Instala las dependencias del backend con Composer antes de iniciar.' }
if (!(Test-Path (Join-Path $frontendDirectory 'node_modules/.bin/quasar.cmd'))) { throw 'Ejecuta npm install en frontend antes de iniciar.' }
$listeners = [System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
if ($listeners.Port -contains 8000 -or $listeners.Port -contains 9000) { throw 'Los puertos 8000 o 9000 ya están en uso. Si Tópico ya está abierto, entra a http://localhost:9000.' }
if ($listeners.Port -notcontains 3306) {
    $xamppInstallations = Get-ItemProperty 'HKLM:/SOFTWARE/Microsoft/Windows/CurrentVersion/Uninstall/*','HKLM:/SOFTWARE/WOW6432Node/Microsoft/Windows/CurrentVersion/Uninstall/*' -ErrorAction SilentlyContinue |
        Where-Object { $_.DisplayName -eq 'XAMPP' -and $_.InstallLocation } |
        Select-Object -ExpandProperty InstallLocation
    $mysqlRoot = @($xamppInstallations | ForEach-Object { Join-Path $_ 'mysql' }) + @('C:/Users/PC001/Downloads/esktop/OFFICE/mysql', 'C:/xampp/mysql') |
        Where-Object { (Test-Path -LiteralPath (Join-Path $_ 'data/topico')) -and (Test-Path -LiteralPath (Join-Path $_ 'bin/mysqld.exe')) } |
        Select-Object -First 1
    if (!$mysqlRoot) { throw 'No se encontró el MySQL que contiene la base topico. Enciende la instalación original de XAMPP.' }
    $mysqlConfiguration = Join-Path $mysqlRoot 'bin/my.ini'
    $mysqlConfigurationArgument = '--defaults-file="' + $mysqlConfiguration + '"'
    Start-Process -FilePath (Join-Path $mysqlRoot 'bin/mysqld.exe') -ArgumentList @($mysqlConfigurationArgument, '--standalone') -WorkingDirectory $mysqlRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDirectory 'mysql-original.log') -RedirectStandardError (Join-Path $logDirectory 'mysql-original-error.log') | Out-Null
    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        Start-Sleep -Milliseconds 500
        $mysqlListeners = [System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
        if ($mysqlListeners.Port -contains 3306) { break }
    }
    if ($mysqlListeners.Port -notcontains 3306) { throw 'MySQL no pudo iniciar. Consulta .local/mysql-original-error.log.' }
}
$backendProcess = Start-Process -FilePath $phpCommand -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', '--port=8000', '--tries=1', '--no-reload') -WorkingDirectory $backendDirectory -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDirectory 'backend.log') -RedirectStandardError (Join-Path $logDirectory 'backend-error.log') -PassThru
$frontendCommand = Join-Path $frontendDirectory 'node_modules/.bin/quasar.cmd'
$frontendProcess = Start-Process -FilePath $env:ComSpec -ArgumentList @('/d', '/c', ('"' + $frontendCommand + '" dev')) -WorkingDirectory $frontendDirectory -WindowStyle Hidden -RedirectStandardOutput (Join-Path $logDirectory 'frontend.log') -RedirectStandardError (Join-Path $logDirectory 'frontend-error.log') -PassThru
@{ backend = $backendProcess.Id; frontend = $frontendProcess.Id } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $logDirectory 'procesos.json')
Write-Host 'Tópico se está iniciando en http://localhost:9000'
Write-Host 'MySQL está activo. Los registros de arranque están en .local.'
