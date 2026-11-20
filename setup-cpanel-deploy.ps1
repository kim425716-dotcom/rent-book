param(
    [string]$FtpHost = "logictech.co.ke",
    [string]$FtpUsername,
    [string]$FtpPassword,
    [string]$DeployPath = "/public_html/rentbook/"
)

$repo = "kim425716-dotcom/rent-book"

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "  Rent Book — Setup cPanel Auto-Deploy via GitHub         " -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

if ([string]::IsNullOrWhiteSpace($FtpUsername)) {
    $FtpUsername = Read-Host "cPanel FTP Username"
}

if ([string]::IsNullOrWhiteSpace($FtpPassword)) {
    $securePass = Read-Host "cPanel FTP Password" -AsSecureString
    $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePass)
    $FtpPassword = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
}

if (-not $DeployPath.EndsWith("/")) {
    $DeployPath = $DeployPath + "/"
}

function Set-Secret($name, $value) {
    if (-not [string]::IsNullOrWhiteSpace($value)) {
        $value | gh secret set $name --repo $repo 2>&1 | Out-Null
        if ($LASTEXITCODE -eq 0) {
            Write-Host "  [✓] Set secret: $name" -ForegroundColor Green
        } else {
            Write-Host "  [!] Failed to set: $name" -ForegroundColor Red
        }
    }
}

Write-Host "Saving deployment secrets to GitHub ($repo)..." -ForegroundColor Cyan
Set-Secret "FTP_HOST" $FtpHost
Set-Secret "FTP_USERNAME" $FtpUsername
Set-Secret "FTP_PASSWORD" $FtpPassword
Set-Secret "DEPLOY_PATH_PROD" $DeployPath

Write-Host "Triggering deployment workflow..." -ForegroundColor Yellow
gh workflow run deploy.yml --repo $repo
Write-Host "Monitor deployment at: https://github.com/$repo/actions" -ForegroundColor Green
