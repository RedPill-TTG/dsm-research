# Serial Port Swapping

Synology's kernel contains a rather strange feature, configurable using a compile-time option:

```
# synoconfigs/Kconfig.devices

config SYNO_X86_SERIAL_PORT_SWAP
    bool "Swap The First Two Serial Ports"
    depends on X86
```

According to a very useful report by Vortex from Xpenology Community we can piece together why this option exists. This 
looks like a hack for some unknown hardware quirk/bug in the actual DS. 

Normally the [PMU](pmu.md) communicates with the system using `/dev/ttyS1` while the kernel is free to use `/dev/ttyS0`
to present a console and boot log. However, on some platforms this behavior looks to be reversed using the swap kernel
flag (e.g. on DS3615xs which is `broadwell` but not on DS3617xs which is a `bromolow`). We can only speculate that on 
some of their hardware they ended up connecting the internal PMU to the 0th serial port and it was too costly to 
redesign the board, so they decided on a new kernel flag.


### What this does to the system?
It looks like to be more of an annoyance rather than a problem. In essence all kernel logs will be streamed to `ttyS1`
instead of `ttyS0`.

If UARTs are not swapped back you will see the following on the first serial port upon boot:
  ```
    # qm terminal 117 -iface serial0
    starting serial terminal on interface serial0 (press Ctrl+O to exit)
    //normal kernel boot log above
    [    0.000000] Console: colour dummy device 80x25
    [    0.000000] console [ttyS0] enabled, bootconsole disabled
    -4
    -9
    -3-4-
    //nothing further is logged
  ```

while the second one will show:
  ```
  # qm terminal 117 -iface serial1
  starting serial terminal on interface serial1 (press Ctrl+O to exit)
  [    0.000000] console [ttyS0] enabled, bootconsole disabled
  [    0.000000] allocated 2097152 bytes of page_cgroup
  //boot continues
  ```

### Implementation
Normally, on x86 serial ports are specified as follows in 
[`arch/x86/include/asm/serial.h`](https://github.com/torvalds/linux/blob/5bfc75d92efd494db37f5c4c173d3639d4772966/arch/x86/include/asm/serial.h#L23):
  ```C
  #define SERIAL_PORT_DFNS                                                              \
      /* UART         CLK           PORT      IRQ   FLAGS                         */    \
      { .uart = 0,    BASE_BAUD,    0x3F8,    4,    STD_COMX_FLAGS    }, /* ttyS0 */    \
      { .uart = 0,    BASE_BAUD,    0x2F8,    3,    STD_COMX_FLAGS    }, /* ttyS1 */    \
      { .uart = 0,    BASE_BAUD,    0x3E8,    4,    STD_COMX_FLAGS    }, /* ttyS2 */    \
      { .uart = 0,    BASE_BAUD,    0x2E8,    3,    STD_COM4_FLAGS    }, /* ttyS3 */
  ```

In Synology's kernel that section looks slightly different:
  ```C
  #ifdef CONFIG_SYNO_X86_SERIAL_PORT_SWAP
  #define SERIAL_PORT_DFNS            \
                   \
      { 0, BASE_BAUD, 0x2F8, 3, STD_COM_FLAGS },         \
      { 0, BASE_BAUD, 0x3F8, 4, STD_COM_FLAGS },         \
      { 0, BASE_BAUD, 0x3E8, 4, STD_COM_FLAGS },         \
      { 0, BASE_BAUD, 0x2E8, 3, STD_COM4_FLAGS },     
  #else  
  #define SERIAL_PORT_DFNS            \
                   \
      { 0, BASE_BAUD, 0x3F8, 4, STD_COM_FLAGS },         \
      { 0, BASE_BAUD, 0x2F8, 3, STD_COM_FLAGS },         \
      { 0, BASE_BAUD, 0x3E8, 4, STD_COM_FLAGS },         \
      { 0, BASE_BAUD, 0x2E8, 3, STD_COM4_FLAGS },     
  #endif  
  ```

Yup, it does exactly that - swaps addresses of `ttyS0` (`0x3F8`) and `ttyS1` (`0x2F8`).


### How does it play with loaders?
While this is just a minor annoyance during debugging, for consistency a decent loader should swap them back. On real
hardware the physical first UART is always the console and the second is always the PMU.

Jun's loader does the swap them + updates the kernel tty in cmdline.

For more details regarding implementation see https://github.com/RedPill-TTG/redpill-lkm docs & code.
