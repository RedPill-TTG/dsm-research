# Platform Management Unit

Following reputable claims by Vortex from Xpenology Community, as well as some kernel & Jun's loader poking, DS hardware 
contains a PMU which serves the following functions:
  - Controlling fans
  - Alarms (wakeups on time etc)
  - Speaker operation
  - ~~RTC~~ the PMU itself doesn't handle RTC, it uses similar pathways as PMU via [mfgBIOS](mfgbios.md) thou
  - VBUS for USB (see `CONFIG_SYNO_USB_VBUS_GPIO_CONTROL` and `CONFIG_SYNO_USB_VBUS_NUM_GPIO` in kernel)
  - Some handshake between DSM and the PMU


### What this does to the system?
Unknown. According to an old Jun's post [it will trigger a time-bomb](https://xpenology.com/forum/topic/6253-dsm-61x-loader/?do=findComment&comment=67324). 
Based on [other posts]((https://xpenology.com/forum/topic/6253-dsm-61x-loader/?do=findComment&comment=54229)) it seems 
it will disable disks and render the system unusable if not emulated.


### Implementation
The implementation of the PMU side of things is obviously unknown, as it lies in a closed-source binary flased into a
8-bit microcontroller. However, the "client-side" part of it isn't secret as even the official SDK lists the commands.
They can be found in any `.dev` toolkit release withing the `hwctl` module, e.g. 
`ds.apollolake-7.0.dev.txz/usr/local/include/hwctl/external.h`:

```C
% grep UART2_CMD 'ds.apollolake-7.0.dev/usr/local/include/hwctl/external.h'
#define UART2_CMD_BUTTON_POWER              0x30
#define UART2_CMD_SHUTDOWN                  0x31
#define UART2_CMD_BUZZER_SHORT              0x32
//...continued
```

### Communication with the system
The PMU communication consists of two parts: `synobios` module doing some low level communication, and userland `scemd` 
daemon. While none of them are GPLed nor open source, the former used to be. It can be found in the older synogpl 
packages (up to 5004?). For example the `synogpl-3776-bromolow` contains a full source tree for the `synobios` (in
`synogpl-3776-bromolow/source/linux-2.6.32/drivers/synobios`) as well as the definition of PMU commands in 
`synogpl-3776-bromolow/source/libsynosdk/gpl/synosdk/external.h`:


Some components of the PMU operation are implemented in the kernel itself. For example some platforms (e.g. 918+) use
SCSI `libata` to control HDD LEDs for block devices. This allows the kernel to map block device (e.g. `/dev/sda`) to
physical channel and call [mfgBIOS](mfgbios.md) via ioctl which will send an information to the PMU. This allows for the
separation of concerns, so that neither mfgBIOS nor PMU must know about internal mapping in the kernel. Currently, the 
following API has been discovered:
  - `funcSYNOSATADiskLedCtrl` (`drivers/ata/libata-scsi.c`)
  - `syno_ahci_disk_led_enable` (`drivers/ata/libahci.c`)
  - `syno_ahci_disk_led_enable_by_port` (`drivers/ata/libahci.c`)


### How does it play with loaders?
The legacy Jun's loader registers a virtual port to receive and respond to PMU commands internally (from within the 
kernel module). RedPill implements that layer completely differently. However, the end effect is the same: commands 
coming from userland and/or mfgBIOS are handled interanlly within the kernel.

The kernel parts of the PMU communication (e.g. `funcSYNOSATADiskLedCtrl`) are platform-dependent implementation. See 
the RedPill repo for details (you need to search for it, they may be in a different places).
