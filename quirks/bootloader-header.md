# Mount bootloader header check

While working around the [ramdisk checksum](ramdisk-checksum.md) we tripped over a check which is **not present in the
Synology's GPL source code** but it exists in the binary.

Either it's a new check/hack introduced in 25556 (to which, at the time of writing, GPL sources aren't available), or 
maybe perhaps this is something to prevent people from running the DSM using loaders?


### What this does to the system?

The check, looking at the ASM around where `fs/namespace.c:do_mount()` is expected to be, should do look similar to the
following:

  ```asm
  ; pseudocode, not the actual ASM in the binary
  MOV        retval,-0x1
  CMP        byte ptr [ramdisk_check_failed],0x0
  JNZ        dput_out
  ; execution continues
  ```

However, the real code does:
  ```asm
  ; pseudocode, not the actual ASM in the binary
  CMP        byte ptr [ramdisk_check_failed],0x0
  MOV        retval,-0x1
  JNZ        dput_out
  CMP        qword ptr [<obscure pointer>],0x0
  JNZ        dput_out
  ; execution continues
  ```

The flow is slightly obscure to people not familiar with ASM. However, instructions in assembly are executed in order 
and there are no blocks *per-se*. So in essence, here ramdisk flag is compared **THEN** `retval` is preemptively set to 
`EPERM` and then a `JNZ` (jump-if-not-zero/false) is executed. However, if that jump is not executed (i.e. the ramdisk 
wasn't tampered) it will do another comparison to an `<obscure pointer>`. If that check returns non-zero value it will 
jump to value return block. It will return `EPERM` as the `retval` is still set to the `EPERM`.

So in essence this check along with the `ramdisk_check_failed` is preventing `mount --bind` and `mound --move` from 
working properly.


### Implementation
We went further and started digging what that `<obscure pointer>` points to. It turns out it's a hook present very early 
in the boot process. It references a local function (`t` as opposed to `T`) which is present in the compiled kernel:

  ```
  $ cat /proc/kallsyms | grep -E '\spci_setup$'
  ffffffff818aa83f t pci_setup
  ```

While nothing unusual there if we look at the same function in the C and ASM there's a striking difference for one of 
the branches:
  ```c
  #drivers/pci/pci.c:pci_setup() from 25426
  } else if (!strncmp(str, "pcie_scan_all", 13)) {
      pci_add_flags(PCI_SCAN_ALL_PCIE_DEVS);
  
  #ASM translated to pseudo-C from 25556
  if (_boot_params.hdr.version < 0x209) {
    LOCK();
    pciFlags = pciFlags | 1;
  }]()
  ```

By itself reading through it nothing stands out... until you consider the cross reference of mount and checking for 
`<obscure pointer>` pointing to `pciFlags`. For me it was "wait a second..." moment:
  1. PCI setup checks [boot params header version](https://www.kernel.org/doc/html/latest/x86/boot.html)  
     <small>*(warning: this is a how messy x86 is - 40 pages of explanation for booting a binary on x86)*</small>
  2. A flag for rescan PCI devices is set
  3. Mount checks if any PCI flags were set and blocks mounting

This paints a nice picture:
  - stuff cannot be remapped using a variety of PCI-related flags
  - boot header is populated by the bootloader (in essence it's a "care package" from the bootloader to the kernel)
  - Synology's controls what version of the header will be detected by the kernel
  - They use that innocently-looking check to block mounting of the filesystem...

Is that a DRM or a hacky workaround for some internal bug? Who knows ¯\_(ツ)_/¯


### How does it play with loaders?
Requires a ([tricky binary patching](README.md#kernel-binary-patching)).


#### Kernel patches
Once we found it, patching this is *technically* easy, just two bytes to replace `JNZ` with `JL` (it's practically a
`NOOP` but with the correct length as the pointer references an unsigned value).

Mount check which needs to be patched located in `do_mount()` (see above):

  - **DS3615xs** with **25556** kernel
    The same pattern is present *twice* (once for `MS_BIND` and once for `MS_MOVE`).
    ```
    0f 85 cb fe ff ff     =to=>     0f 8c cb fe ff ff
    ```
  
  - **DS3617xs** with **25556** kernel  
    Two different patches are needed.

    - for `MS_REBIND` in `else`, around `ff81120eb7`
      ```
      48 83 3d b4 b1 98 00 00   CMP pciFlags,0
      0f 85 cb fe ff ff         JNZ dput_out with -EPERM
    
      change JNZ to JL [<], as pciFlags is never negative to:
      
      0f 8c cb fe ff ff         JL dput_out with -EPERM
      ```
      
    - for `MS_MOVE` around `fff81121194`
      ```
      48 83 3d e4 ae 98 00 00   CMP pciFlags,0
      0f 85 fb fb ff ff         JNZ dput_out with -EPERM
  
      change JNZ to JL [<], as pciFlags is never negative to:
      
      0f 8c fb fb ff ff         JL dput_out with -EPERM
      ```
    
