# Some Serial Port Are Muted

Due to unknown reason on some platforms (e.g. 918+) the first serial port is not functional after boot. It's unknown if
this affects other platforms and/or other serial ports.


### What this does to the system?
The affected serial port will work for the kernel earlycon but it will fail as soon as the proper 8250 driver 
initializes. Effectively this means you **cannot** set `console=` boot option to `ttyS0` as it will go silent after a
short boot log. If `console=` specifies another port (e.g. `ttyS2` which is free, unlike `ttyS1` used by the [PMU](pmu.md))
the boot process will continue as usual with the terminal appearing on such port.


### Implementation of swapping
It is unknown how/why is the `ttyS0` broken. It may be an intentional change to prevent console access to a real box, 
thus improving security and at least partially mitigating supply-chain attacks. We spaculate that if intentional it's
most likely hidden in plain sight in an overbloated `8250_core.c`, maybe under `MY_DEF_HERE` constant.


### How does it play with loaders?
While this is just a minor annoyance during debugging, for consistency a decent loader should fix it and have the 
console on `ttyS0` like a real box. We cannot be sure if some programs running in the OS don't assume `ttyS0` is the
console.

See https://github.com/RedPill-TTG/redpill-lkm/blob/master/shim/uart_fixer.c for details.
