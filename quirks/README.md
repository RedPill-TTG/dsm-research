# DSM booting quirks


List of known quirks (in a recommended to be read order):

1. [**Custom kernel params**](dsm-kernel-params.md)
    - Type: information
    - Understood: yes
    - Loader impact: N/A
    - Kernel options: N/A
 
2. [**Disk types**](disk-types.md)
    - Type: information
    - Understood: yes
    - Loader impact: N/A
    - Kernel options: N/A

3. [**Kernel `cmdline` validation**](cmdline-verification.md)
    - Type: integrity verification
    - Understood: yes, not verified
    - Loader impact: LKM shim
    - Kernel options: all custom ones
 
4. [**Synoboot location**](synoboot.md)
    - Type: operation parameter
    - Understood: yes
    - Loader impact: LKM shim
    - Kernel options: `vid=`, `pid=`

5. [**Ramdisk checksum**](ramdisk-checksum.md)
    - Type: integrity verification
    - Understood: yes
    - Loader impact: kernel binpatch
    - Kernel options: N/A

6. [**`boot_params` Validation**](boot_params-validation.md)
    - Type: integrity verification
    - Understood: partially
    - Loader impact: kernel binpatch
    - Kernel options: N/A

7. [**Serial port swapping**](serial-port-swapping.md)
    - Type: operation parameter
    - Understood: yes
    - Loader impact: LKM shim (hw version dependent)
    - Kernel options: N/A

8. [**Platform management unit**](pmu.md)
    - Type: hw feature
    - Understood: no
    - Loader impact: LKM emulator
    - Kernel options: N/A

9. [**Hardware manufacturer's BIOS**](mfgbios.md)
   - Type: hw feature, integrity verification
   - Understood: yes
   - Loader impact: LKM emulator
   - Kernel options: N/A

9. [**Low-level Firmware update**](hw-firmware-update.md)
   - Type: hw feature
   - Understood: yes
   - Loader impact: LKM shim or user-mode workaround 
   - Kernel options: N/A



### Generic notes

#### Current mysteries
See [mysteries file](mysteries.md) which contains bits-and-pieces of information about some things which aren't known
enough to warrant a file.

#### Kernel binary patching
Some quirks (*"Loader impact: kernel binpatch"*) require the kernel image to be patched. This is because a given check 
happens before any module can be loaded and Synology doesn't share full GPL sources (the code is full of 
`#ifdef MY_ABC_HERE`) so a simple patch to the kernel is not possible.

The only option we see is binary patching. However, while it's possible:
  - It isn't as easy as one may expect (and the patch part is just the tip of an iceberg)
  - Binary patching must be done on uncompressed image
      - After patching the kernel needs to recompressed (or we should say partially rebuilt)
      - This is a complex process requiring custom tooling.
