# Hardware manufacturer's BIOS (mfgBIOS)

Synology uses their software on a variety of hardware where features only slightly different. However,the hardware 
ranges from simple low power ARM boxes to rack-mounted beasts. This means there has to be some bridge between the low
level stuff and high-level things like the web panel.


### What this does to the system?
Due to the closed-source nature of the module everything is a suspicion. However, lack of many of the interfaces will 
surely make the later layers (i.e. the whole DSM) unhappy. Immediatelly after the module loads kernel log is being 
filled with:

```
[  154.736374] parameter error. gpiobase=00000000, pin=4, pValue=ffff88000e1d79c4
[  154.736688] parameter error. gpiobase=00000000, pin=4, pValue=ffff88000e1d79c4
[  156.738788] parameter error. gpiobase=00000000, pin=4, pValue=ffff88000e1d79c4
```


### Implementation
Looking through GPLed source code of Linux kernel for DS3615xs we can find many code paths which aren't called directly
but are exported from the kernel. Moreover, these code paths deal with specific hardware features (e.g.
`syno_mv_9235_disk_led_set` and `syno_mv_9235_disk_led_get` in `drivers/ata/ahci.c`). Looking through what actually
consumes these symbols we can see that the `synobios` module seems to be the one:

```
$ readelf -a /usr/lib/modules/synobios.ko | grep 'syno_mv_9235_disk_led_'
0000000051ea  00bd00000002 R_X86_64_PC32     0000000000000000 syno_mv_9235_disk_led_ - 4
0000000051f4  00e300000002 R_X86_64_PC32     0000000000000000 syno_mv_9235_disk_led_ - 4
0000000059ef  00bd00000002 R_X86_64_PC32     0000000000000000 syno_mv_9235_disk_led_ - 4
0000000059f9  00e300000002 R_X86_64_PC32     0000000000000000 syno_mv_9235_disk_led_ - 4
   189: 0000000000000000     0 NOTYPE  GLOBAL DEFAULT  UND syno_mv_9235_disk_led_set
   227: 0000000000000000     0 NOTYPE  GLOBAL DEFAULT  UND syno_mv_9235_disk_led_get
```

This makes perfect sense - synobios presumably provides an abstraction of these allowing for a safe access from
non-privileged code (i.e. code not running in the kernel). This can be inferred by the presence of `/dev/synobios` and
`/proc/synobios/`. As dissembling non-GPL binaries is tricky legally we stayed within roams of what the module tells us
by itself. Looking at the symbols itself we can see supported platforms (or at least we know it supports these "at
minimum"):

```
$ cat /proc/kallsyms | grep DS
ffffffffa01dd310 t DS3612xsInitModuleType	[bromolow_synobios]
ffffffffa01dd340 t DS3615xsInitModuleType	[bromolow_synobios]
ffffffffa01dd410 t DS3611xsInitModuleType	[bromolow_synobios]
ffffffffa01dd570 t DS2414xsInitModuleType	[bromolow_synobios]
```

### How does it play with loaders?
Based on the analysis of Jun's module and symbols provided by the bios module itself we can find a table in memory. The
table contains a list of multiple pointers. As the module exports all symbols necessary, there's no need for disassembly 
of the proprietary code to understand which parts needs to be provided to make the code happy:

```
40 80 1e a0 ff ff ff ff  [00] 0x000 	ffffffffa01e8040	synobios_model_cleanup+0x7e40/0x8 [bromolow_synobios]
a0 ed 1d a0 ff ff ff ff  [01] 0x008 	ffffffffa01deda0	GetBrand+0x0/0x10 [bromolow_synobios]
50 f7 1d a0 ff ff ff ff  [02] 0x010 	ffffffffa01df750	GetModel+0x0/0x250 [bromolow_synobios]
00 00 00 00 00 00 00 00  [03] 0x018 	          (null)	          (null)
c0 e7 1d a0 ff ff ff ff  [04] 0x020 	ffffffffa01de7c0	rtc_bandon_get_time+0x0/0x1a0 [bromolow_synobios]
20 ea 1d a0 ff ff ff ff  [05] 0x028 	ffffffffa01dea20	rtc_bandon_set_time+0x0/0x350 [bromolow_synobios]
00 00 00 00 00 00 00 00  [06] 0x030 	          (null)	          (null)
90 f5 1d a0 ff ff ff ff  [07] 0x038 	ffffffffa01df590	SetFanStatus+0x0/0xe0 [bromolow_synobios]
70 f6 1d a0 ff ff ff ff  [08] 0x040 	ffffffffa01df670	GetSysTemperature+0x0/0x20 [bromolow_synobios]
50 f2 1d a0 ff ff ff ff  [09] 0x048 	ffffffffa01df250	GetCpuTemperatureDenlowI3Transfer+0x0/0x80 [bromolow_synobios]
90 fb 1d a0 ff ff ff ff  [10] 0x050 	ffffffffa01dfb90	SetDiskLedStatusBy9235GPIOandAHCISGPIO+0x0/0x60 [bromolow_synobios]
00 00 00 00 00 00 00 00  [11] 0x058 	          (null)	          (null)
00 00 00 00 00 00 00 00  [12] 0x060 	          (null)	          (null)
00 00 00 00 00 00 00 00  [13] 0x068 	          (null)	          (null)
00 00 00 00 00 00 00 00  [14] 0x070 	          (null)	          (null)
70 ee 1d a0 ff ff ff ff  [15] 0x078 	ffffffffa01dee70	SetGpioPin+0x0/0x50 [bromolow_synobios]
f0 ee 1d a0 ff ff ff ff  [16] 0x080 	ffffffffa01deef0	GetGpioPin+0x0/0x50 [bromolow_synobios]
00 00 00 00 00 00 00 00  [17] 0x088 	          (null)	          (null)
60 e9 1d a0 ff ff ff ff  [18] 0x090 	ffffffffa01de960	rtc_bandon_set_auto_poweron+0x0/0xc0 [bromolow_synobios]
10 e5 1d a0 ff ff ff ff  [19] 0x098 	ffffffffa01de510	rtc_get_auto_poweron+0x0/0x50 [bromolow_synobios]
00 00 00 00 00 00 00 00  [20] 0x0a0 	          (null)	          (null)
00 00 00 00 00 00 00 00  [21] 0x0a8 	          (null)	          (null)
70 f5 1d a0 ff ff ff ff  [22] 0x0b0 	ffffffffa01df570	SetAlarmLed+0x0/0x20 [bromolow_synobios]
10 ee 1d a0 ff ff ff ff  [23] 0x0b8 	ffffffffa01dee10	GetBuzzerCleared+0x0/0x30 [bromolow_synobios]
e0 ed 1d a0 ff ff ff ff  [24] 0x0c0 	ffffffffa01dede0	SetBuzzerClear+0x0/0x30 [bromolow_synobios]
40 ee 1d a0 ff ff ff ff  [25] 0x0c8 	ffffffffa01dee40	GetPowerStatus+0x0/0x30 [bromolow_synobios]
00 00 00 00 00 00 00 00  [26] 0x0d0 	          (null)	          (null)
b0 ed 1d a0 ff ff ff ff  [27] 0x0d8 	ffffffffa01dedb0	InitModuleType+0x0/0x30 [bromolow_synobios]
70 f4 1d a0 ff ff ff ff  [28] 0x0e0 	ffffffffa01df470	Uninitialize+0x0/0x20 [bromolow_synobios]
90 f4 1d a0 ff ff ff ff  [29] 0x0e8 	ffffffffa01df490	SetCpuFanStatus+0x0/0xe0 [bromolow_synobios]
00 00 00 00 00 00 00 00  [30] 0x0f0 	          (null)	          (null)
00 00 00 00 00 00 00 00  [31] 0x0f8 	          (null)	          (null)
00 00 00 00 00 00 00 00  [32] 0x100 	          (null)	          (null)
c0 db 1d a0 ff ff ff ff  [33] 0x108 	ffffffffa01ddbc0	CheckMicropId+0x0/0x90 [bromolow_synobios]
40 db 1d a0 ff ff ff ff  [34] 0x110 	ffffffffa01ddb40	SetMicropId+0x0/0x80 [bromolow_synobios]
00 00 00 00 00 00 00 00  [35] 0x118 	          (null)	          (null)
00 00 00 00 00 00 00 00  [36] 0x120 	          (null)	          (null)
00 00 00 00 00 00 00 00  [37] 0x128 	          (null)	          (null)
00 00 00 00 00 00 00 00  [38] 0x130 	          (null)	          (null)
00 00 00 00 00 00 00 00  [39] 0x138 	          (null)	          (null)
40 ef 1d a0 ff ff ff ff  [40] 0x140 	ffffffffa01def40	GetCPUInfo+0x0/0x70 [bromolow_synobios]
00 00 00 00 00 00 00 00  [41] 0x148 	          (null)	          (null)
00 00 00 00 00 00 00 00  [42] 0x150 	          (null)	          (null)
00 00 00 00 00 00 00 00  [43] 0x158 	          (null)	          (null)
00 00 00 00 00 00 00 00  [44] 0x160 	          (null)	          (null)
10 f4 1d a0 ff ff ff ff  [45] 0x168 	ffffffffa01df410	HWMONGetFanSpeedRPMFromADT+0x0/0x60 [bromolow_synobios]
00 00 00 00 00 00 00 00  [46] 0x170 	          (null)	          (null)
b0 f3 1d a0 ff ff ff ff  [47] 0x178 	ffffffffa01df3b0	HWMONGetVoltageSensorFromADT+0x0/0x60 [bromolow_synobios]
00 00 00 00 00 00 00 00  [48] 0x180 	          (null)	          (null)
50 f3 1d a0 ff ff ff ff  [49] 0x188 	ffffffffa01df350	HWMONGetThermalSensorFromADT+0x0/0x60 [bromolow_synobios]
00 00 00 00 00 00 00 00  [50] 0x190 	          (null)	          (null)
00 00 00 00 00 00 00 00  [51] 0x198 	          (null)	          (null)
```

Jun's code seems to patch all hardware-specific bits of the vtable:  
![jun vtable patch](imgs/bios_vtable_patch.png)


However, while this looks simple it is not. This is because finding that vtable reliably is tricky due to how kernel
modules are loaded and how certain parts of kernel's memory are protected (from accidental change).  

See https://github.com/RedPill-TTG/redpill-lkm/blob/master/shim/bios_shim.c for details.

#### Appendix: official `synobios_ops` structure

While Synobios itself isn't GPLed it actually used to open source. Intentionally or not Synology included its full
source with some of their releases. See more in the [GPL section](gpl.md). While the code is not explicitly marked as
GPL it is open sourced. However, even if the [headers aren't copyrightable anyway](https://softwareengineering.stackexchange.com/a/216480).

Additionally, as @Vortex pointed out, the newly-released DSMv7.0 dev toolkit contains the full `synobios_ops` structure.
It contains not only the list but also function declarations making the identification easier. You can find it in e.g.
`ds.broadwell-7.0.dev.txz/usr/local/include/synobios/synobios.h`:

```C
//Formatting adjusted here
// Copyright (c) 2000-2003 Synology Inc. All rights reserved.
struct synobios_ops {
    struct module   *owner;
    int	    (*get_brand)(void);
    int	    (*get_model)(void);
    int	    (*get_cpld_version)(void);
    int	    (*get_rtc_time)(struct _SynoRtcTimePkt *);
    int	    (*set_rtc_time)(struct _SynoRtcTimePkt *);
    int	    (*get_fan_status)(int, FAN_STATUS *);
    int	    (*set_fan_status)(FAN_STATUS, FAN_SPEED);
    int	    (*get_sys_temperature)(struct _SynoThermalTemp *);
    int	    (*get_cpu_temperature)(struct _SynoCpuTemp *);
#if defined(CONFIG_SYNO_PORT_MAPPING_V2)
    int	    (*set_disk_led)(DISKLEDSTATUS*);
#else /* CONFIG_SYNO_PORT_MAPPING_V2 */
    int	    (*set_disk_led)(int, SYNO_DISK_LED);
#endif /* CONFIG_SYNO_PORT_MAPPING_V2 */
    int	    (*set_power_led)(SYNO_LED);
    int	    (*get_cpld_reg)(CPLDREG *);
    int	    (*set_mem_byte)(MEMORY_BYTE *);
    int	    (*get_mem_byte)(MEMORY_BYTE *);
    int	    (*set_gpio_pin)(GPIO_PIN *);
    int	    (*get_gpio_pin)(GPIO_PIN *);
    int	    (*set_gpio_blink)(GPIO_PIN *);
    int	    (*set_auto_poweron)(SYNO_AUTO_POWERON *);
    int	    (*get_auto_poweron)(SYNO_AUTO_POWERON *);
    int	    (*init_auto_poweron)(void);
    int	    (*uninit_auto_poweron)(void);
    int	    (*set_alarm_led)(unsigned char);
    int	    (*get_buzzer_cleared)(unsigned char *buzzer_cleared);
    int	    (*set_buzzer_clear)(unsigned char buzzer_clear);
    int	    (*get_power_status)(POWER_INFO *);
    int	    (*get_backplane_status)(BACKPLANE_STATUS *);
    int	    (*module_type_init)(struct synobios_ops *);
    int	    (*uninitialize)(void);
    int	    (*set_cpu_fan_status)(FAN_STATUS, FAN_SPEED);
    int     (*set_phy_led)(SYNO_LED);
    int     (*set_hdd_led)(SYNO_LED);
    int	    (*pwm_ctl)(SynoPWMCTL *);
    int	    (*check_microp_id)(const struct synobios_ops *);
    int	    (*set_microp_id)(void);
    int	    (*get_superio)(SYNO_SUPERIO_PACKAGE *);
    int	    (*set_superio)(SYNO_SUPERIO_PACKAGE *);
    int	    (*exdisplay_handler)(struct _SynoMsgPkt *);
    int	    (*read_memory)(SYNO_MEM_ACCESS*);
    int	    (*write_memory)(SYNO_MEM_ACCESS*);
    void    (*get_cpu_info)(SYNO_CPU_INFO*, const unsigned int);
    int     (*set_aha_led)(struct synobios_ops *, SYNO_AHA_LED);
    int     (*get_copy_button_status)(void); // for matching userspace usage, button pressed = 0, else = 1
    int     (*hwmon_get_fan_speed_rpm)(SYNO_HWMON_SENSOR_TYPE *);
    int     (*hwmon_get_psu_status)(SYNO_HWMON_SENSOR_TYPE *, int);
    int     (*hwmon_get_sys_voltage)(SYNO_HWMON_SENSOR_TYPE *);
    int     (*hwmon_get_backplane_status)(SYNO_HWMON_SENSOR_TYPE *);
    int     (*hwmon_get_sys_thermal)(SYNO_HWMON_SENSOR_TYPE *);
    int     (*hwmon_get_sys_current)(SYNO_HWMON_SENSOR_TYPE *);
    int     (*set_ok_to_remove_led)(unsigned char ledON);
    int	    (*get_sys_current)(unsigned long*);
    int     (*get_disk_intf)(SYNO_DISK_INTF_INFO *);
};
```