@echo off

@setlocal

set LIB_PATH=%~dp0

if "%PHP_COMMAND%" == "" set PHP_COMMAND=php.exe

"%PHP_COMMAND%" "%LIB_PATH%fetch" %*

@endlocal