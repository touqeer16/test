$msg = "New commit files of today - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
Write-Host "Commit message: $msg"
git add .
git commit -m "$msg"
git push
