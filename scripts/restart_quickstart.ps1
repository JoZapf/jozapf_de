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

  Write-Host "▶ Wechsel ins Projekt: $Root" -ForegroundColor Cyan
  if(!(Test-Path $Root)){ throw "Projektpfad nicht gefunden: $Root" }
  Set-Location $Root

  # Sanity-Check: Docker/Compose
  $null = docker --version 2>$null; if($LASTEXITCODE -ne 0){ throw "Docker nicht verfügbar." }
  $null = docker compose version 2>$null; if($LASTEXITCODE -ne 0){ throw "Docker Compose V2 nicht verfügbar." }

  # Hilfsfunktion: Port aus Compose ermitteln (gibt nur die Portnummer zurück)
  function Get-ComposePort([string]$File,[string]$Service,[int]$PrivatePort){
    $pi = docker compose -f $File port $Service $PrivatePort 2>$null
    if([string]::IsNullOrWhiteSpace($pi)){ return $null }
    return ($pi -split ':')[-1].Trim()
  }

  # ---- PREVIEW (8080) -------------------------------------------------------
  if($Preview){
    Write-Host "▶ Preview-Stack (nginx static) vorbereiten…" -ForegroundColor Cyan
    $outIndex = Join-Path $Root "out\index.html"
    if($ForceBuild -or -not (Test-Path $outIndex)){
      Write-Host "… kein out/index.html gefunden → baue Export (npm ci + npm run build)" -ForegroundColor Yellow
      npm ci
      if($LASTEXITCODE -ne 0){ throw "npm ci fehlgeschlagen." }
      npm run build
      if($LASTEXITCODE -ne 0){ throw "npm run build fehlgeschlagen." }
    } else {
      Write-Host "… out/index.html vorhanden → Build übersprungen" -ForegroundColor DarkGray
    }

    docker compose -f compose.preview.yml up -d --force-recreate
    if($LASTEXITCODE -ne 0){ throw "Preview-Stack konnte nicht gestartet werden." }

    $prevPort = Get-ComposePort "compose.preview.yml" "next-static" 80
    if(!$prevPort){ Write-Warning "Konnte Preview-Port nicht ermitteln."; } else {
      Write-Host ("✓ Preview erreichbar: http://localhost:{0}/" -f $prevPort) -ForegroundColor Green
      try {
        $null = Invoke-WebRequest ("http://localhost:{0}/" -f $prevPort) -UseBasicParsing -TimeoutSec 5
      } catch { Write-Warning "HTTP-Check Preview fehlgeschlagen: $($_.Exception.Message)" }
    }
  }

  # ---- MAIN (8088) ----------------------------------------------------------
  if($Main){
    Write-Host "▶ Main-Stack (nginx + php-fpm) starten…" -ForegroundColor Cyan
    docker compose -f compose.yml up -d nginx php
    if($LASTEXITCODE -ne 0){ throw "Main-Stack konnte nicht gestartet werden." }

    $mainPort = Get-ComposePort "compose.yml" "nginx" 80
    if(!$mainPort){ Write-Warning "Konnte Main-Port nicht ermitteln."; } else {
      Write-Host ("✓ Main erreichbar: http://localhost:{0}/" -f $mainPort) -ForegroundColor Green
      try {
        $res = Invoke-WebRequest ("http://localhost:{0}/assets/php/health.php" -f $mainPort) -UseBasicParsing -TimeoutSec 5
        if($res.StatusCode -eq 200 -and $res.Content -match "OK"){
          Write-Host "✓ PHP-Health OK" -ForegroundColor Green
        } else {
          Write-Warning "PHP-Health unerwartete Antwort."
        }
      } catch { Write-Warning "HTTP-Check Main fehlgeschlagen: $($_.Exception.Message)" }
    }
  }

  # ---- DEV (3000) -----------------------------------------------------------
  if($Dev){
    Write-Host "▶ Dev-Stack (Next HMR) starten…" -ForegroundColor Cyan

    # Falls node_modules im Volume leer wäre: einmalige Befüllung (robust)
    docker compose -f compose.yml -f compose.next.yml run --rm next-dev sh -lc "if [ ! -d node_modules ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then npm ci || npm i; fi"
    if($LASTEXITCODE -ne 0){ Write-Warning "Vor-Install im Dev-Container evtl. fehlgeschlagen (prüfe Logs)." }

    docker compose -f compose.yml -f compose.next.yml --profile next up -d next-dev
    if($LASTEXITCODE -ne 0){ throw "Dev-Stack konnte nicht gestartet werden." }

    $devPort = Get-ComposePort "compose.yml;compose.next.yml" "next-dev" 3000
    # Bei zusammengesetzten Compose-Dateien löst 'port' nicht immer korrekt auf → als Fallback: 3000
    if(!$devPort){ $devPort = "3000" }
    Write-Host ("✓ Dev erreichbar: http://localhost:{0}/" -f $devPort) -ForegroundColor Green
    try {
      $null = Invoke-WebRequest ("http://localhost:{0}/robots.txt" -f $devPort) -UseBasicParsing -TimeoutSec 5
    } catch { Write-Warning "HTTP-Check Dev fehlgeschlagen: $($_.Exception.Message)" }
  }

  Write-Host "✔ Fertig." -ForegroundColor Green
}