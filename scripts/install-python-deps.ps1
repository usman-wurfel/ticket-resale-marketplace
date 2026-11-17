# Install scipy for scripts/confidence_level.py (Windows / Laragon).
$Python = if ($env:RESALE_PYTHON_BIN) { $env:RESALE_PYTHON_BIN } else { "python" }
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

& $Python --version
if ($LASTEXITCODE -ne 0) {
    Write-Error "Python not found. Install Python 3 and/or set RESALE_PYTHON_BIN in .env"
    exit 1
}

& $Python -m pip install --upgrade pip
& $Python -m pip install -r (Join-Path $ScriptDir "requirements.txt")
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Write-Host "Done. Test with:"
Write-Host "  $Python $(Join-Path $ScriptDir 'confidence_level.py') --successful 20 --litigations 1"
