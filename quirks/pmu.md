# Platform Management Unit

Following reputable claims by Vortex from Xpenology Community, as well as some kernel & Jun's loader poking, DS hardware 
contains a PMU which serves the following functions:
  - Controlling fans
  - Alarms (wakeups on time etc)
  - Speaker operation
  - RTC
  - VBUS for USB (see `CONFIG_SYNO_USB_VBUS_GPIO_CONTROL` and `CONFIG_SYNO_USB_VBUS_NUM_GPIO` in kernel)
  - Some handshake between DSM and the PMU


### What this does to the system?
Unknown. According to an old Jun's post [it will trigger a time-bomb](https://xpenology.com/forum/topic/6253-dsm-61x-loader/?do=findComment&comment=67324). 
Based on [other posts]((https://xpenology.com/forum/topic/6253-dsm-61x-loader/?do=findComment&comment=54229)) it seems 
it will disable disks and render the system unusable if not emulated.


### Implementation
Unknown. Needs deeper analysis.

The PMU communication consists of two parts: `synobios` module doing some low level communication, and userland `scemd` 
daemon. While none of them are GPLed nor open source, the former used to be. It can be found in the older synogpl 
packages (up to 5004?). For example the `synogpl-3776-bromolow` contains a full source tree for the `synobios` (in
`synogpl-3776-bromolow/source/linux-2.6.32/drivers/synobios`) as well as the definition of PMU commands in 
`synogpl-3776-bromolow/source/libsynosdk/gpl/synosdk/external.h`:




### How does it play with loaders?
Unknown. Allegedly Jun's loader contains some code to emulate (all? parts of?) the PMU.
