@echo off
title Money Life - GitHub Deploy
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0push_to_github.ps1"
