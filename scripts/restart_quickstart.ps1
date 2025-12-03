<#
.SYNOPSIS
Startet die lokalen Stacks (Dev/Preview/Main) für jozapf.de nach einem Neustart.

.DESCRIPTION
- Startet nach Bedarf die Stacks:
  * Preview (nginx statisch, bedient ./out) – Port aus PREVIEW_PORT
  * Main (nginx + php-fpm) – Port aus HTTP_PORT
  * Dev (Next.js HMR) – Port 3000
- Baut Preview nur, wenn ./out/index.html fehlt (oder via -ForceBuild).
- Validiert rudimentär (HTTP-Checks).
- Liest Ports aus .env im Projekt-Root; .env.local beeinflusst nur Next.

.PARAMETER Root
Projekt-Root. Standard: E:\Projects\jozapf-de

.PARAMETER Dev
Nur Dev-Stack starten (Next HMR, Port 3000).

.PARAMETER Preview
Nur Preview-Stack starten (nginx static, Port PREVIEW_PORT).

.PARAMETER Main
Nur Main-Stack starten (nginx + php-fpm, Port HTTP_PORT).

.PARAMETER ForceBuild
Erzwingt npm-Build vor Preview-Start, auch wenn ./out/index.html vorhanden ist.

.EXAMPLE
Start-JZStacks
Startet alle drei Stacks. Baut Preview falls ./out/index.html fehlt.

.EXAMPLE
Start-JZStacks -Preview -ForceBuild
Baut Preview neu und startet den Preview-Stack.

.EXAMPLE
Start-JZStacks -Main
Startet nur den Nginx+PHP-Stack (prod-nah).

.NOTES
Voraussetzungen: Docker Desktop + Compose V2, Node.js v20+, PowerShell 5.1+.
#>
function Start-JZStacks {
  [CmdletBinding()]
  param(
    [string]$Root = "E:\Projects\jozapf-de",
    [switch]$Dev,      # nur Dev
    [switch]$Preview,  # nur Preview
    [switch]$Main,     # nur Main
    [switch]$ForceBuild # Preview: Build erzwingen
  )

  # Wenn kein Ziel gewählt → alle starten
  if(-not ($Dev -or $Preview -or $Main)) { $Dev=$true; $Preview=$true; $Main=$true }

  Write-Host "[>>] Wechsel ins Projekt: $Root" -ForegroundColor Cyan
  if(!(Test-Path $Root)){ throw "Projektpfad nicht gefunden: $Root" }
  Set-Location $Root

  # Sanity-Check: Docker/Compose
  $null = docker --version 2>$null; if($LASTEXITCODE -ne 0){ throw "Docker nicht verfügbar." }
  $null = docker compose version 2>$null; if($LASTEXITCODE -ne 0){ throw "Docker Compose V2 nicht verfügbar." }

  # Hilfsfunktion: Port-Verfügbarkeit prüfen
  function Test-PortAvailable([int]$Port, [switch]$IgnoreDocker) {
    # Prüft ob Port frei ist (nicht von anderem Prozess oder Hyper-V blockiert)
    $excluded = netsh interface ipv4 show excludedportrange protocol=tcp 2>$null
    if ($excluded -match "\s$Port\s") {
      Write-Warning "Port $Port liegt im Hyper-V/NAT reservierten Bereich!"
      Write-Warning "Loesung: PREVIEW_PORT in .env aendern (z.B. 3080) oder Hyper-V Portbereich anpassen."
      return $false
    }
    $conn = Get-NetTCPConnection -LocalPort $Port -ErrorAction SilentlyContinue
    if ($conn) {
      $proc = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
      # Docker-Prozesse ignorieren wenn -IgnoreDocker gesetzt (Container wird eh neu gestartet)
      if ($IgnoreDocker -and $proc.ProcessName -match 'docker|com\.docker') {
        Write-Host "... Port $Port durch Docker belegt (Container wird neu gestartet)" -ForegroundColor DarkGray
        return $true
      }
      Write-Warning "Port $Port bereits belegt durch: $($proc.ProcessName) (PID $($conn.OwningProcess))"
      return $false
    }
    return $true
  }

  # Hilfsfunktion: Port aus Compose ermitteln (gibt nur die Portnummer zurück)
  function Get-ComposePort([string]$File,[string]$Service,[int]$PrivatePort){
    $pi = docker compose -f $File port $Service $PrivatePort 2>$null
    if([string]::IsNullOrWhiteSpace($pi)){ return $null }
    return ($pi -split ':')[-1].Trim()
  }

  # ---- PREVIEW ---------------------------------------------------------------
  if($Preview){
    Write-Host "[>>] Preview-Stack (nginx static) vorbereiten..." -ForegroundColor Cyan
    
    # Port aus .env lesen und pruefen
    $envFile = Join-Path $Root ".env"
    $previewPort = 8080  # Default
    if (Test-Path $envFile) {
      $envContent = Get-Content $envFile -Raw
      if ($envContent -match 'PREVIEW_PORT=(\d+)') { $previewPort = [int]$Matches[1] }
    }
    if (-not (Test-PortAvailable $previewPort -IgnoreDocker)) {
      throw "Port $previewPort nicht verfuegbar. Bitte PREVIEW_PORT in .env aendern."
    }
    
    $outIndex = Join-Path $Root "out\index.html"
    if($ForceBuild -or -not (Test-Path $outIndex)){
      Write-Host "... kein out/index.html gefunden -> baue Export (npm ci + npm run build)" -ForegroundColor Yellow
      npm ci
      if($LASTEXITCODE -ne 0){ throw "npm ci fehlgeschlagen." }
      npm run build
      if($LASTEXITCODE -ne 0){ throw "npm run build fehlgeschlagen." }
    } else {
      Write-Host "... out/index.html vorhanden -> Build uebersprungen" -ForegroundColor DarkGray
    }

    docker compose -f compose.preview.yml up -d --force-recreate
    if($LASTEXITCODE -ne 0){ throw "Preview-Stack konnte nicht gestartet werden." }

    $prevPort = Get-ComposePort "compose.preview.yml" "next-static" 80
    if(!$prevPort){ Write-Warning "Konnte Preview-Port nicht ermitteln."; } else {
      Write-Host "[OK] Preview erreichbar: http://localhost:${prevPort}/" -ForegroundColor Green
      Start-Sleep -Seconds 2
      try {
        $null = Invoke-WebRequest "http://localhost:${prevPort}/" -UseBasicParsing -TimeoutSec 5
      } catch { Write-Warning "HTTP-Check Preview fehlgeschlagen: $($_.Exception.Message)" }
    }
  }

  # ---- MAIN (8088) ----------------------------------------------------------
  if($Main){
    Write-Host "[>>] Main-Stack (nginx + php-fpm) starten..." -ForegroundColor Cyan
    docker compose -f compose.yml up -d nginx php
    if($LASTEXITCODE -ne 0){ throw "Main-Stack konnte nicht gestartet werden." }

    $mainPort = Get-ComposePort "compose.yml" "nginx" 80
    if(!$mainPort){ Write-Warning "Konnte Main-Port nicht ermitteln."; } else {
      Write-Host "[OK] Main erreichbar: http://localhost:${mainPort}/" -ForegroundColor Green
      Start-Sleep -Seconds 3
      try {
        $res = Invoke-WebRequest "http://localhost:${mainPort}/assets/php/health-check.php" -UseBasicParsing -TimeoutSec 5
        if($res.StatusCode -eq 200 -and $res.Content -match '"ok"\s*:\s*true'){
          Write-Host "[OK] PHP-Health OK" -ForegroundColor Green
        } else {
          Write-Warning "PHP-Health unerwartete Antwort: $($res.Content)"
        }
      } catch { Write-Warning "HTTP-Check Main fehlgeschlagen: $($_.Exception.Message)" }
    }
  }

  # ---- DEV (3000) -----------------------------------------------------------
  if($Dev){
    Write-Host "[>>] Dev-Stack (Next HMR) starten..." -ForegroundColor Cyan

    # Node-Module-Check: Einfacher Einzeiler, robust fuer PowerShell -> sh Uebergabe
    $shellCmd = 'test -d node_modules && test -n "$(ls -A node_modules 2>/dev/null)" || npm ci || npm i'
    
    docker compose -f compose.yml -f compose.next.yml run --rm next-dev sh -c $shellCmd
    if($LASTEXITCODE -ne 0){ 
      Write-Warning "Vor-Install im Dev-Container evtl. fehlgeschlagen (pruefe Logs)." 
    }

    docker compose -f compose.yml -f compose.next.yml --profile next up -d next-dev
    if($LASTEXITCODE -ne 0){ throw "Dev-Stack konnte nicht gestartet werden." }

    $devPort = Get-ComposePort "compose.yml;compose.next.yml" "next-dev" 3000
    # Bei zusammengesetzten Compose-Dateien löst 'port' nicht immer korrekt auf → als Fallback: 3000
    if(!$devPort){ $devPort = "3000" }
    Write-Host "[OK] Dev erreichbar: http://localhost:${devPort}/" -ForegroundColor Green
    Start-Sleep -Seconds 4
    try {
      $null = Invoke-WebRequest "http://localhost:${devPort}/robots.txt" -UseBasicParsing -TimeoutSec 5
    } catch { 
      Write-Warning "HTTP-Check Dev fehlgeschlagen: $($_.Exception.Message)" 
    }
  }

  Write-Host "[FERTIG] Alle gewaehlten Stacks gestartet." -ForegroundColor Green
}
