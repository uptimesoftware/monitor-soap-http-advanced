@ECHO OFF
setlocal EnableDelayedExpansion
set SCRIPT_DIR=C:\PROGRA~1\UPTIME~1\uptime\snmp

cscript %SCRIPT_DIR%\Trap_Receiver.vbs !cmdcmdline!
