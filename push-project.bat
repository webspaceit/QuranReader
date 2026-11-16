@echo off
setlocal EnableExtensions EnableDelayedExpansion
cd /d "%~dp0"
if errorlevel 1 (
    echo Cannot open the project folder.
    pause
    exit /b 1
)

set "PROJECT_DIR=%~dp0"
if "%PROJECT_DIR:~-1%"=="\" set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"
set "SSH_KEY=%USERPROFILE%\.ssh\id_ed25519_mf"
set "SSH_USER=mfinnovation.xyz@ssh.gb.stackcp.com"
set "REMOTE_BASE=/home/sites/27b/0/0b72e9e422/quranreader.mfinnovation.xyz"
set "REMOTE_STATE=/home/sites/27b/0/0b72e9e422/.quranreader-last-deploy"
rem Empty tree: the very first run uploads everything instead of a diff.
set "INITIAL_DEPLOYED_COMMIT=4b825dc642cb6eb9a060e54bf8d69288fbee4904"
set "DEPLOY_SCRIPT=%~dp0tools\deploy_changed_files.ps1"
set "MSG_FILE=%TEMP%\quranreader-commit-msg.txt"

echo ============================================================
echo  Quran Reader - GitHub push + SSH deploy
echo ============================================================
echo  Project : %PROJECT_DIR%
echo  GitHub  : https://github.com/webspaceit/QuranReader
echo  Server  : %SSH_USER%
echo  Remote  : %REMOTE_BASE%
echo.

rem ---------------------------------------------------------- prerequisites
set "MISSING="
for %%T in (git.exe ssh.exe scp.exe tar.exe powershell.exe) do (
    where %%T >nul 2>nul || set "MISSING=!MISSING! %%T"
)
if defined MISSING (
    echo Missing required command^(s^):!MISSING!
    echo Install Git for Windows with the OpenSSH client, then retry.
    goto failed
)
if not exist "%SSH_KEY%" (
    echo SSH key not found: %SSH_KEY%
    goto failed
)
if not exist "%DEPLOY_SCRIPT%" (
    echo Deploy helper not found: %DEPLOY_SCRIPT%
    goto failed
)

rem ------------------------------------------------------------- repo state
git rev-parse --is-inside-work-tree >nul 2>nul
if errorlevel 1 (
    echo This folder is not a Git working tree.
    goto failed
)
git remote get-url origin >nul 2>nul
if errorlevel 1 (
    echo No "origin" remote configured.
    goto failed
)

for /f "delims=" %%B in ('git rev-parse --abbrev-ref HEAD') do set "BRANCH=%%B"
echo  Branch  : !BRANCH!
echo.
git status --short
if errorlevel 1 goto failed
echo.

set "MSG="
set /p "MSG=Commit message (blank = Update Quran Reader): "
if not defined MSG set "MSG=Update Quran Reader"

git add -A
if errorlevel 1 goto failed

rem Never commit local credentials or generated artifacts.
if exist ".env" git restore --staged -- .env >nul 2>nul
if exist "app\config\config.php" git restore --staged -- app/config/config.php >nul 2>nul
if exist "quran-website-v1.zip" git restore --staged -- quran-website-v1.zip >nul 2>nul

git diff --cached --quiet
if errorlevel 2 goto failed
if errorlevel 1 goto commit
echo Nothing new to commit.
goto push

:commit
rem Commit through a file so &, |, <, > and %% inside the message are safe.
> "%MSG_FILE%" echo(!MSG!
git commit -F "%MSG_FILE%"
if errorlevel 1 (
    del "%MSG_FILE%" >nul 2>nul
    goto failed
)
del "%MSG_FILE%" >nul 2>nul
echo.

:push
echo [1/2] Pushing to GitHub...
git push origin HEAD
if errorlevel 1 (
    echo.
    echo GitHub push failed. Common causes:
    echo   * not authenticated:  gh auth login   (or add an SSH key to GitHub)
    echo   * remote moved ahead: git pull --rebase  then run this script again
    goto failed
)

echo.
echo [2/2] Deploying to the hosting account...
powershell -NoProfile -ExecutionPolicy Bypass -File "%DEPLOY_SCRIPT%" -ProjectDir "%PROJECT_DIR%" -Server "%SSH_USER%" -Key "%SSH_KEY%" -RemoteBase "%REMOTE_BASE%" -RemoteState "%REMOTE_STATE%" -InitialCommit "%INITIAL_DEPLOYED_COMMIT%"
if errorlevel 1 goto deploy_failed

echo.
echo ============================================================
echo  DONE - GitHub push and hosting deployment completed.
echo  Site: https://quranreader.mfinnovation.xyz
echo ============================================================
set "RESULT=0"
goto finish

:deploy_failed
echo.
echo ============================================================
echo  GitHub push OK, but the hosting deployment FAILED.
echo  The deploy marker was not advanced, so running this script
echo  again retries exactly the pending files. Nothing is lost.
echo ============================================================
set "RESULT=1"
goto finish

:failed
echo.
echo ============================================================
echo  FAILED - nothing was deployed. Review the message above.
echo ============================================================
set "RESULT=1"

:finish
echo.
pause
exit /b %RESULT%
