# Synoboot

While running on a real hardware DSM needs some reliable way to distinguish types of devices. Linux keeps track of 
various things like whether the device is removable or which bus is used. However, a proper NAS system must:
 - know which drives are where in a physical sense (e.g. `/dev/sdv` = Slot 1, row 2, front bay; `/dev/sdc` = eSATA #1)
 - be able to locate its own bootloader drive reliably
 - separate remote targets from local ones

The issue is Linux doesn't care about such things, so Synology had to modify the kernel to include different types of 
disks. More details about them in [`disk-types.md`](disk-types.md).

One of the types is `SYNO_DISK_SYNOBOOT`. It is a special type. It can be any SCSI-based disk (SATA, USB, iSCSI...) 
which is essentially the boot disk of DSM. It's important to mention that it **does not** contain the OS. The boot disk
houses kernel and initial ramdisk, while the DSM OS resides on an RAID1 of the first 12 disks.

### How does it assign types?
There are many ways depending on the type. `SYNOBOOT` is special. Looking at the GPLed kernel code, DSM has a couple of 
methods to find its bootdisk. They don't seem to be geared towards authenticating the actual hardware (i.e. not like 
SMBIOS in Hackintosh) but to actually find the correct disk. The bootdisk can be present in multiple places (USB stick, 
SATA DOM, network, you name it).


#### USB-based boot device
The most common/widely used method is to check VID+PID of the USB device. On a genuine Synology hardware boot device has
ids matching the hardcoded and compiled into the kernel values:

  ```
  # include/linux/syno.h
  
  #ifdef    MY_ABC_HERE
  #define IS_SYNO_USBBOOT_ID_VENDOR(VENDOR) (0xF400 == (VENDOR) || 0xF401 == (VENDOR))
  #define IS_SYNO_USBBOOT_ID_PRODUCT(PRODUCT) (0xF400 == (PRODUCT) || 0xF401 == (PRODUCT))
  #else  
  #define IS_SYNO_USBBOOT_ID_VENDOR(VENDOR) (0xF400 == (VENDOR))
  #define IS_SYNO_USBBOOT_ID_PRODUCT(PRODUCT) (0xF400 == (PRODUCT))
  #endif  
  ```

Under normal circumstances `0xF400` are used. Under (currently unexplored) "mfg mode" `0xF401` are used. In order for a
disk to be considered `SYNO_DISK_SYNOBOOT` these must match:

  ```c
  # drivers/scsi/sd.c
  
  if (IS_SYNO_USBBOOT_ID_VENDOR(le16_to_cpu(usbdev->descriptor.idVendor)) &&
      IS_SYNO_USBBOOT_ID_PRODUCT(le16_to_cpu(usbdev->descriptor.idProduct))) {
    //<fragment removed>
    if (!syno_find_synoboot()) {
        return SYNO_DISK_SYNOBOOT;
    }
  }
  ```


#### SATA-based boot device
There is also the SATA boot device support - it gets checked based on the vendor name and model:
  
  ```c
  # drivers/scsi/sd.c
  
      if (SYNO_PORT_TYPE_SATA == sdp->host->hostt->syno_port_type) {
  #ifdef MY_ABC_HERE
           
          if (1 == gSynoBootSATADOM) {
              if (!strncmp(CONFIG_SYNO_SATA_DOM_VENDOR, sdp->vendor, strlen(CONFIG_SYNO_SATA_DOM_VENDOR))
                  && !strncmp(CONFIG_SYNO_SATA_DOM_MODEL, sdp->model, strlen(CONFIG_SYNO_SATA_DOM_MODEL))) {
                  blIsSynoboot = true;
              } else if (!strncmp(CONFIG_SYNO_SATA_DOM_VENDOR_SECOND_SRC, sdp->vendor, strlen(CONFIG_SYNO_SATA_DOM_VENDOR_SECOND_SRC))
                  && !strncmp(CONFIG_SYNO_SATA_DOM_MODEL_SECOND_SRC, sdp->model, strlen(CONFIG_SYNO_SATA_DOM_MODEL_SECOND_SRC))) {
                  blIsSynoboot = true;
              }
  ```

Some details:
  - `gSynoBootSATADOM` is set based on `synoboot_satadom` [kernel cmdline param](dsm-kernel-params.md)
  - Only some models are expected to use SATA DOM (Disk-on-Module) which is controlled by `CONFIG_SYNO_BOOT_SATA_DOM`
  - Different DS models use different disk modules, so that their `CONFIG_SYNO_SATA_DOM_VENDOR` and
    `CONFIG_SYNO_SATA_DOM_MODEL` are different 
  - Only a limited subset of platforms can support native SATA DOM (e.g. `apollolake` platform is built without
    `CONFIG_SYNO_BOOT_SATA_DOM`)

Example of a configuration for `bromolow` platform:  
  ```
  CONFIG_SYNO_BOOT_SATA_DOM=y
  CONFIG_SYNO_SATA_DOM_VENDOR="SATADOM-"
  CONFIG_SYNO_SATA_DOM_MODEL="TYPE D 3SE"
  CONFIG_SYNO_SATA_DOM_VENDOR_SECOND_SRC="SATADOM"
  CONFIG_SYNO_SATA_DOM_MODEL_SECOND_SRC="D150SH"
  ```


### What this does to the system?
If a proper `DISK_SYNOBOOT` is not found the OS will not even boot properly. During attempted install it will cause
an infamous [Error 13](error13.md).


### How does it play with loaders?
The loader must take care to present the boot drive so that it satisfies checks to be marked as `SYNO_DISK_SYNOBOOT`. 
This designation cannot really be applied after the drive finishes probing. This is because even if the type is altered
in the SCSI drivers structure the `/dev` entry will stay incorrect.

To process for ensuring correct type for **USB** device:

  1. Register loader LKM as quickly as possible
  2. Register for notifications using `register_module_notifier() `to get notified when a new module is loaded into the 
     kernel
  3. Wait until `usbcore` is registered while continuing other tasks
  4. When `usbcore` registration event comes in there's a short window (~6-10ms) of time to do a `usb_register_notify()`
     1. This will watch for new devices found on USB hubs 
     2. This symbol **cannot** be linked in the module as the kernel module loader will explode when module is loaded 
        before `usbcore` (and it has to!)
     3. Symbols can be accessed using a `__symbol_get()` kernel interface
  5. When new device is inserted we need to quickly respond to the event by checking of VID+PID matches the one 
     specified as override in the cmdline and replace them with `0xf400` or `0xf401` (mfg/retail) *before* SCSI layer is 
     able to get to them

The last point is a straight race condition. Normally you avoid it like a fire - however in this case it's the desirable 
approach (sort of like RGH on Xbox 360 ;)). This is why on slower single-core machines that hack may fail and even 
[Jun warned about that](https://xpenology.com/forum/topic/6253-dsm-61x-loader/).


To process for ensuring correct type for **SATA** device:

  1. Register loader LKM as quickly as possible
  2. Find SCSI subsystem driver
  3. Replace probing function (`sd_probe()`) with a custom one
     1. The custom function will be called for every **new** device
     2. SATA disk matching some criteria can be then altered to have the correct vendor/model
     3. Call original `sd_probe()` and let the OS handle the rest
  4. Iterate over existing devices and check for matches
     1. This is needed because SCSI is usually not a module so we cannot load before it (but after OR during)
     2. If device matches shimming criteria kick remove the device (=simulated unplug)
     3. Rescan all ports on the SCSI host who got the drive removed

Jun's loader does *not* use this process. Instead, it relies on user-land hack (rename `/dev/sdX` nodes to 
`/dev/synoboot` nodes). However, this has many disadvantages:

  - easy to detect by DSM
  - unstable in v7
  - leaves 50MB pseudo-disk visible in the DSM UI (can be worked around by "pushing" the disk beyond number of supported
    disks)

The only quirk with all that, mentioned [even by Jun](https://xpenology.com/forum/topic/6253-dsm-61x-loader/) and
followed by Synology in their [VDSM](../VDSM/vdsm-investigation.md) is that a drive which is designated as 
`DISK_SYNOBOOT` must contain at least 2 partitions.

See https://github.com/RedPill-TTG/redpill-lkm/blob/master/shim/bios_shim.c for details.
