# Error 13

*This document is incomplete*

The error 13 is displayed by the initial installer. It can be see in logs as well as in the web installer. By itself it
means nothing, despite being labeled as "Image corrupted" (suggesting that PAT file is the culprit). However, in reality
the error 13 means "**something** went wrong with the installation".

To diagnose the culprit one needs to check:
  - `dmesg` for any shimming or [mfgBIOS](mfgbios.md) errors
  - `/var/log/messages` for any signs of firmware update, or (most likely) problems with [Synoboot shimming](synoboot.md)
  - `/var/log/junior_reason` to see why the installer even appeared
