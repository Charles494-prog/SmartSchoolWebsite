; SmartSchool Windows installer (built by .github/workflows/windows-installer.yml)
Unicode true
!define APPNAME "SmartSchool"
!ifndef VERSION
  !define VERSION "0.1.0"
!endif
Name "${APPNAME}"
OutFile "SmartSchool-Setup.exe"
InstallDir "C:\SmartSchool"
RequestExecutionLevel admin
SetCompressor /SOLID lzma
!include "MUI2.nsh"
!define MUI_ABORTWARNING
!define MUI_FINISHPAGE_RUN
!define MUI_FINISHPAGE_RUN_TEXT "Open SmartSchool now (you will choose Offline or Online)"
!define MUI_FINISHPAGE_RUN_FUNCTION LaunchApp
!insertmacro MUI_PAGE_WELCOME
!insertmacro MUI_PAGE_LICENSE "build\app\LICENSE"
!insertmacro MUI_PAGE_DIRECTORY
!insertmacro MUI_PAGE_INSTFILES
!insertmacro MUI_PAGE_FINISH
!insertmacro MUI_UNPAGE_CONFIRM
!insertmacro MUI_UNPAGE_INSTFILES
!insertmacro MUI_LANGUAGE "English"

!define LAUNCH 'powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$INSTDIR\SmartSchool.ps1"'

Function LaunchApp
  Exec '${LAUNCH}'
FunctionEnd

Section "Install"
  SetOutPath "$INSTDIR"
  File "SmartSchool.ps1"
  File /r "build\php"
  File /r "build\app"
  ; Let phones and laptops on the school network reach SmartSchool (port 8000, private networks only)
  nsExec::Exec 'netsh advfirewall firewall delete rule name="SmartSchool"'
  nsExec::Exec 'netsh advfirewall firewall add rule name="SmartSchool" dir=in action=allow protocol=TCP localport=8000 profile=private,domain'
  ; The app writes to storage/ and database/
  nsExec::Exec 'icacls "$INSTDIR\app\storage" /grant *S-1-5-32-545:(OI)(CI)M /T /Q'
  nsExec::Exec 'icacls "$INSTDIR\app\bootstrap\cache" /grant *S-1-5-32-545:(OI)(CI)M /T /Q'
  nsExec::Exec 'icacls "$INSTDIR\app\database" /grant *S-1-5-32-545:(OI)(CI)M /T /Q'
  CreateShortcut "$DESKTOP\SmartSchool.lnk" "powershell.exe" '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$INSTDIR\SmartSchool.ps1"' "$INSTDIR\app\public\favicon.ico"
  CreateDirectory "$SMPROGRAMS\SmartSchool"
  CreateShortcut "$SMPROGRAMS\SmartSchool\SmartSchool.lnk" "powershell.exe" '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$INSTDIR\SmartSchool.ps1"' "$INSTDIR\app\public\favicon.ico"
  CreateShortcut "$SMPROGRAMS\SmartSchool\Uninstall SmartSchool.lnk" "$INSTDIR\Uninstall.exe"
  WriteUninstaller "$INSTDIR\Uninstall.exe"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\SmartSchool" "DisplayName" "SmartSchool"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\SmartSchool" "DisplayVersion" "${VERSION}"
  WriteRegStr HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\SmartSchool" "UninstallString" '"$INSTDIR\Uninstall.exe"'
SectionEnd

Section "Uninstall"
  nsExec::Exec 'netsh advfirewall firewall delete rule name="SmartSchool"'
  Delete "$DESKTOP\SmartSchool.lnk"
  RMDir /r "$SMPROGRAMS\SmartSchool"
  ; School data (database, uploads) is kept in $INSTDIR\app\database and storage – only program files are removed.
  RMDir /r "$INSTDIR\php"
  Delete "$INSTDIR\SmartSchool.ps1"
  Delete "$INSTDIR\Uninstall.exe"
  DeleteRegKey HKLM "Software\Microsoft\Windows\CurrentVersion\Uninstall\SmartSchool"
SectionEnd
