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
Unknown. Needs analysis.


### How does it play with loaders?
Unknown. Allegedly Jun's loader contains some code to emulate (all? parts of?) the PMU.
