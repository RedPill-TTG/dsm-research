# Real Time Clock (RTC)

To keep the time when the system is powered off almost every computer and most advanced IoT devices use a dedicated
clock chip. Such chip is usually powered by a battery when the full system is off. Programmatically the RTC chip is 
read on system boot and written to on system power off.

There are a plethora of different RTC chips. On a regular PC, which is ACPI complaint, it is mandatory to support a very
old Motorola MC146818 (dating back to like 1970s). In reality, it is usually not that particular chip but something 
compatible with it. However, on bespoke system like NASes the manufacturer is free to do whatever.


### What this does to the system?
In a usual Linux system programs use the standard API to read/write time and wake-up alarms. Since most of the
hardware-related operations are abstracted via the [mfgBIOS](mfgbios.md) the RTC is no exception. On platforms which 
expect a standard Motorola MC146818-compatible the mfgBIOS will communicate with the RTC just fine. However, some other
platforms (e.g. 918+) expect a different chip with a direct I2C communication to it. Obviously almost no PC nor 
hypervisor will be able to interact with that.

With a broken RTC a null-date will be set upon boot until the network is reached. Allegedly, some time-related features
will crash if the RTC communication cannot be established.


### Implementation
The implementation is unknown as it lives somewhere in the closed-source mfgBIOS module.

However, some things can be derived looking at the header files. See [mfgBIOS](mfgbios.md) for more details.


### How does it play with loaders?
A simple shim which proxies the calls between the kernel's RTC interface and the format expected by the mfgBIOS is 
sufficient.

Jun's loader contains a bunch of functions for RTC emulation.  Some of the loader code bits related to that:  
![rtc functions](imgs/rtc1.png)  
![rtc code](imgs/rtc2.png)  

See https://github.com/RedPill-TTG/redpill-lkm/blob/master/shim/bios/rtc_proxy.c for details.

