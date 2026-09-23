<#
    SZESÜTI – telepíthető ZIP készítése.

    Használat:
        .\build.ps1                 # szetemeny-<verzió>.zip a dist mappába
        .\build.ps1 -Version 1.1.0  # verziószám frissítése is a forrásban

    A ZIP gyökerében egy "szesuti" mappa lesz, ahogy a WordPress elvárja.

    Két Windows-os csapdát kerül el szándékosan:
      1. NEM a Compress-Archive parancsot használja. Windows PowerShell 5.1 alatt az
         fordított perjellel (szesuti\szesuti.php) írja az útvonalakat, amit Linuxos
         tárhely nem mappaként, hanem fájlnévként bont ki – a plugin így telepíthetetlen
         és törölhetetlen lesz.
      2. NEM a Set-Content -Encoding UTF8 parancsot használja. Az 5.1 alatt BOM-ot ír
         a fájl elejére, ami a PHP-ben kimenetként jelenik meg és elrontja az átirányításokat.
#>

[CmdletBinding()]
param(
    [string] $Version
)

$ErrorActionPreference = 'Stop'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root   = $PSScriptRoot
$source = Join-Path $root 'szesuti'
$dist   = Join-Path $root 'dist'
$utf8   = New-Object System.Text.UTF8Encoding $false   # BOM nélkül

if (-not (Test-Path $source)) {
    throw "Nem talalom a plugin mappat: $source"
}

# --- Verzió kiolvasása vagy frissítése -----------------------------------

$mainFile = Join-Path $source 'szesuti.php'
$mainText = [System.IO.File]::ReadAllText($mainFile, $utf8)

if ($Version) {
    if ($Version -notmatch '^\d+\.\d+\.\d+$') { throw "A verzio formatuma legyen X.Y.Z (kapott: $Version)" }

    $mainText = [regex]::Replace($mainText, '(?m)^(\s*\*\s*Version:\s*).*$', "`${1}$Version")
    $mainText = [regex]::Replace($mainText, "(define\(\s*'SZESUTI_VERSION',\s*')[^']*(')", "`${1}$Version`${2}")
    [System.IO.File]::WriteAllText($mainFile, $mainText, $utf8)

    $readme = Join-Path $source 'readme.txt'
    if (Test-Path $readme) {
        $readmeText = [System.IO.File]::ReadAllText($readme, $utf8)
        $readmeText = [regex]::Replace($readmeText, '(?m)^(Stable tag:\s*).*$', "`${1}$Version")
        [System.IO.File]::WriteAllText($readme, $readmeText, $utf8)
    }

    Write-Host "Verzio frissitve: $Version"
}
else {
    $m = [regex]::Match($mainText, "define\(\s*'SZESUTI_VERSION',\s*'([^']+)'")
    if (-not $m.Success) { throw 'Nem sikerult kiolvasni a verziot.' }
    $Version = $m.Groups[1].Value
}

# --- Forrás ellenőrzése csomagolás előtt ----------------------------------

$problems = @()

Get-ChildItem -Path $source -Recurse -File -Include *.php, *.js, *.css, *.txt | ForEach-Object {
    $bytes = [System.IO.File]::ReadAllBytes($_.FullName)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        $problems += "BOM a fajl elejen: $($_.FullName.Substring($source.Length + 1))"
    }
}

if ($problems.Count) {
    $problems | ForEach-Object { Write-Host "  HIBA: $_" -ForegroundColor Red }
    throw 'A forras hibas, nem csomagolok.'
}

# --- Fájlok összegyűjtése, fejlesztői fájlok nélkül -----------------------

$excludeDirs  = @('.git', '.github', 'node_modules', 'tests', '.vscode', '.idea')
$excludeFiles = @('*.log', '*.zip', '.DS_Store', 'Thumbs.db', '*.map')

$files = Get-ChildItem -Path $source -Recurse -File | Where-Object {
    $relative = $_.FullName.Substring($source.Length).TrimStart('\', '/')
    $parts    = $relative -split '[\\/]'
    $skip     = $false
    foreach ($dir in $excludeDirs) { if ($parts -contains $dir) { $skip = $true } }
    foreach ($pattern in $excludeFiles) { if ($_.Name -like $pattern) { $skip = $true } }
    -not $skip
}

# --- Csomagolás (perjeles útvonalakkal) -----------------------------------

if (-not (Test-Path $dist)) { New-Item -ItemType Directory -Force -Path $dist | Out-Null }

$zipPath = Join-Path $dist "szetemeny-$Version.zip"
if (Test-Path $zipPath) { Remove-Item -LiteralPath $zipPath -Force }

$stream  = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
$archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    foreach ($file in $files) {
        $relative  = $file.FullName.Substring($source.Length).TrimStart('\', '/')
        $entryName = 'szesuti/' + ($relative -replace '\\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $file.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $archive.Dispose()
    $stream.Dispose()
}

# --- A kész ZIP ellenőrzése ------------------------------------------------

$check   = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
$entries = @($check.Entries | ForEach-Object { $_.FullName })
$check.Dispose()

$errors = @()
if ($entries | Where-Object { $_ -match '\\' })          { $errors += 'forditott perjel az utvonalban' }
if ($entries | Where-Object { $_ -notlike 'szesuti/*' }) { $errors += 'nem a szesuti/ mappa alatti bejegyzes' }
if ($entries -notcontains 'szesuti/szesuti.php')         { $errors += 'hianyzik a szesuti/szesuti.php' }
if ($entries.Count -ne @($files).Count)                  { $errors += "bejegyzesszam elter ($($entries.Count) vs $(@($files).Count))" }

if ($errors.Count) {
    Remove-Item -LiteralPath $zipPath -Force
    throw ('A ZIP hibas, toroltem: ' + ($errors -join '; '))
}

$size = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
Write-Host ""
Write-Host "Kesz: $zipPath  ($size KB, $($entries.Count) fajl)"
Write-Host "Ellenorizve: minden utvonal perjeles es a szesuti/ mappa alatt van."
