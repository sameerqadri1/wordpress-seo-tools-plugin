# PowerShell Script to Push to GitHub
# Run this after creating your GitHub repository

# Colors for output
$ErrorActionPreference = "Stop"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " PUSH TO GITHUB" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Get GitHub username
$githubUsername = Read-Host "Enter your GitHub username"

if ([string]::IsNullOrWhiteSpace($githubUsername)) {
    Write-Host "ERROR: GitHub username is required!" -ForegroundColor Red
    exit 1
}

# Confirm repository name
$repoName = "seo-marketing-tools"
Write-Host "Repository name: $repoName" -ForegroundColor Yellow
$confirm = Read-Host "Is this correct? (Y/n)"

if ($confirm -eq "n" -or $confirm -eq "N") {
    $repoName = Read-Host "Enter repository name"
}

# Build GitHub URL
$githubUrl = "https://github.com/$githubUsername/$repoName.git"

Write-Host ""
Write-Host "GitHub URL: $githubUrl" -ForegroundColor Green
Write-Host ""
Write-Host "IMPORTANT: Make sure you've created this repository on GitHub first!" -ForegroundColor Yellow
Write-Host "Visit: https://github.com/new" -ForegroundColor Yellow
Write-Host ""
$ready = Read-Host "Have you created the repository on GitHub? (Y/n)"

if ($ready -eq "n" -or $ready -eq "N") {
    Write-Host ""
    Write-Host "Please create the repository first, then run this script again." -ForegroundColor Yellow
    exit 0
}

Write-Host ""
Write-Host "Pushing to GitHub..." -ForegroundColor Cyan

try {
    # Add remote
    Write-Host "1. Adding remote origin..." -ForegroundColor White
    git remote add origin $githubUrl 2>$null
    if ($LASTEXITCODE -ne 0) {
        # Remote might already exist, remove and add again
        git remote remove origin 2>$null
        git remote add origin $githubUrl
    }
    
    # Rename branch to main
    Write-Host "2. Renaming branch to main..." -ForegroundColor White
    git branch -M main
    
    # Push to GitHub
    Write-Host "3. Pushing to GitHub..." -ForegroundColor White
    git push -u origin main
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host " SUCCESS! " -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Your repository is now on GitHub!" -ForegroundColor Green
    Write-Host "View it at: https://github.com/$githubUsername/$repoName" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Yellow
    Write-Host "1. Visit your repository and add a description" -ForegroundColor White
    Write-Host "2. Add topics: wordpress, plugin, seo, ai, gemini, php" -ForegroundColor White
    Write-Host "3. Star your repo (optional)" -ForegroundColor White
    Write-Host ""
    
} catch {
    Write-Host ""
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Common issues:" -ForegroundColor Yellow
    Write-Host "- Repository doesn't exist on GitHub yet" -ForegroundColor White
    Write-Host "- Wrong username or repository name" -ForegroundColor White
    Write-Host "- Not authenticated with GitHub (may need to login)" -ForegroundColor White
    exit 1
}
