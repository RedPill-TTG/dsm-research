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
It looks like the swapping is more of an annoyance rather than a problem. In essence all kernel logs will be streamed to 
`ttyS1` instead of `ttyS0`.

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


**However** there's more serial modifications than just swapping.

Jun's loader does the swap them but does it less than ideally. The table below shows the summary:

| Platform   | Earlycon Set | Earlycon Out | BIOS Set      | BIOS Out | Console Set | Console Out |
|------------|--------------|--------------|---------------|----------|-------------|-------------|
| 918 (nat)  |  0x3f8 (1st) |     1st ✅   | serial1 (2nd) |   2nd ✅ | ttyS2 (3rd) |    3rd ✅  |
| 918 (nat)  |  0x3f8 (1st) |     1st ✅   | serial1 (2nd) |   2nd ✅ | ttyS0 (3rd) |  missing ❌   |
| 918 (Jun)  |  0x3f8 (1st) |   missing ❌ | serial1 (2nd) |    n/a   | ttyS0 (1st) |    1st ✅   |
| 3615 (nat) |  0x3f8 (1st) |     1st ✅   | serial1 (2nd) |   1st ❌ | ttyS0 (1st) |    2nd ❌  |
| 3615 (Jun) |  0x3f8 (1st) |     1st ✅   | serial1 (2nd) |    n/a   | ttyS0 (1st) |  2nd => 1st |

Notes:
  - Running native on 918 with 3 separate ports works as expected
  - Running native on 918 works as long as we don't touch ttyS0 beyond earlycon. 8250 driver has syno modifications in
    the kernel. This is most likely the culprit of why the ttyS0 and ttyS1 are broken (ttyS0 is not even detected by the
    kernel):
    ```
     //booting with console= set to ttyS0 
     [   28.842864] calling  serial8250_init+0x0/0x15f @ 1
     [   28.843535] Serial: 8250/16550 driver, 4 ports, IRQ sharing enabled
     [   33.221054] serial8250: ttyS1 at I/O 0x2f8 (irq = 3, base_baud = 115200) is a 16550A
     [   33.977563] console [ttyS0] enabled
     [   33.978163] bootconsole [uart0] disabled
     //no further output is produced on 1st serial port
    ```
    So the effect is no console anywhere as ttyS0 is never initialized.
  - Jun's loader doesn't display any mfgBIOS output as it's captured
  - Jun's loader breaks earlycon in 918 for some unknown reason (output is empty until proper console kicks in)
  - Running native on 3615 is strange due to 1st and 2nd serial being swapped. This doesn't affect earlycon because it
    doesn't use the ttyS* addressing but specifies `0x3f8` address directly.
  - Jun's loader swaps 3615 ports but it can only do that pretty late (as an LKM is loaded much later than console is
    initialized). This creates a strange effect where earlycon works as expected on 1st serial, then we get SOME of the
    kernel output on the 2nd serial (time between earlycon => console switching and LKM loading) and then it's switched
    back to 1st (as the ports are flipped)


### Implementation of swapping
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

Jun's loader does the following things with serials:
 - On v4 fixes `/dev/ttyS0` to point to a real `0x3f8` port using `early_serial_setup()` (as it's broken by syno stuff)
 - Removes a real `/dev/ttyS1` and mocks it in software for PMU emulation (so you will never get anything on 2nd serial)
 - On platforms with swapped serials it partially swaps them back:
   - changes `ttyS1` to `ttyS0` in kernel console drivers by altering `console_drivers` (exported symbol)
   - calls `update_console_cmdline()` to fix early console assignment
   - it does NOT fully swap `ttyS0` and `ttyS1` so that printing to `echo 123 > /dev/ttyS0` still prints on 2nd serial.
     There's no good way to achieve that. Since both serials are occupied by the OS stuff there's no real point of doing
     this anyway.

See https://github.com/RedPill-TTG/redpill-lkm/blob/master/shim/uart_fixer.c for details.
