# DSM Disk Types

*This document is incomplete*

Synology added many more or less hacky things to the kernel. Some of their best additions are in the SCSI subsystem.
Two of them, working together, are detection of disk ports and types. 

Normally Linux kernel doesn't care where drive physically is, and it doesn't track what type of a port it is connected 
to. All the SCSI subsystem cares about is that the drive "speaks" SCSI. This is a very flexible approach as nothing 
has to distinguish whether it's talking to a USB flash driver or a SATA HDD. However, in a NAS scenario we need to know
if something is an internal SATA drive or an external USB stick.


### Port types
Syno kernel adds one of the following types to any SCSI host port:

  ```C
  // include/scsi/scsi_host.h
  enum {
    SYNO_PORT_TYPE_SATA = 1,
    SYNO_PORT_TYPE_USB = 2,
    SYNO_PORT_TYPE_SAS = 3,
  };
  ```

That value is saved in `(scsi_device *)sdp->host->hostt->syno_port_type` and is fixed during SCSI driver initialization.


### Disk types
The customized kernel contain another layer above port types dealing with disk types. The disk type represents a logical
role of a given block device in the system. It can be any of the following:

  ```C
  # drivers/scsi/sd.h
  
  typedef enum __syno_disk_type {
      SYNO_DISK_UNKNOWN = 0,
      SYNO_DISK_SATA,
      SYNO_DISK_USB,
      SYNO_DISK_SYNOBOOT,
      SYNO_DISK_ISCSI,
      SYNO_DISK_SAS,
  #ifdef MY_ABC_HERE
      SYNO_DISK_CACHE,  
  #endif  
      SYNO_DISK_END,  
  }SYNO_DISK_TYPE;
  ```

These types are determined in the `drivers/scsi/sd.c:sd_probe()`, called as the first function after drive appears in 
the system. All but one type should be self-explanatory. The `SYNO_DISK_SYNOBOOT` is discussed in more details in the
["Synoboot" section](synoboot.md).

#### SMART support
Since v7 the following types are expected to support SMART, or they will be constantly marked as unhealthy. This will 
case these disks to be unusable for pools creation (rendering them useless):
 - `SYNO_DISK_SATA`
 - `SYNO_DISK_SAS`
 - `SYNO_DISK_CACHE`


### Special cases

#### VirtIO SCSI disks
Disks connected using VirtIO driver can be passed via SCSI or SATA. Using the former allows for many more drives, while
the latter has more widespread support. The `sd.c` SCSI driver contains a special provisioning for SCSI VirtIO disks,
treating them exactly like `SYNO_DISK_SATA` type. However, that provisioning is active for limited subset of platforms.
Based on the constants in the GPL code it's probably only active in [VDSM mode](../VDSM/README.md):

```C
//sd.c:sd_probe()

#if defined(MY_ABC_HERE) || defined(CONFIG_SYNO_NEXTKVMX64)
  if (strcmp(sdp->host->hostt->name, "Virtio SCSI HBA") == 0){
#ifdef MY_ABC_HERE
    while (virtdev) {
      if (virtdev->driver && virtdev->driver->name && !strcmp(virtdev->driver->name, "virtio-pci")) {
        pcidev = to_pci_dev(virtdev);
        break;
      }
      virtdev = virtdev->parent;
    }
    if (pcidev && PCI_SLOT(pcidev->devfn) == CONFIG_SYNO_KVMX64_PCI_SLOT_BOOT) {
      return SYNO_DISK_SYNOBOOT;
    }
#endif  
    return SYNO_DISK_SATA;
  }
#endif  
```


### Flags
There are some flags associated with disks. This is by no means a complete list (see `drivers/scsi/sd.c`).

#### System disk flag
This flag, present in `struct gendisk->systemdisk` (`include/linux/genhd.h`) denotes if the disk may *potentially*
contain a system partition. Its absence blocks `mdadm` from automatically assembling the array during boot:

```
md: Autodetecting RAID arrays.
md: deny sdc1.
md: export_rdev(sdc1)
md: deny sdc2.
md: export_rdev(sdc2)
md: Scanned 2 and added 0 devices.
md: autorun ...
md: ... autorun DONE.
```

This in turn causes the system to boot into initramfs state after installation, as there are no OS disks to mount. The
method responsible for checking this is `blDenyDisks()` (`drivers/md/md.c`) called from the `autostart_arrays()`:

```c
if (blDenyDisks(rdev->bdev)) {
    char b[BDEVNAME_SIZE];

    printk(KERN_INFO "md: deny %s.\n",
           bdevname(rdev->bdev,b));

    export_rdev(rdev);
    continue;
}
```

The flag is set in `sd_probe()` (`drivers/scsi/sd.c`) for types `SYNO_DISK_SAS` and `SYNO_DISK_SATA`. There's nothing 
custom for the SAS disks. However, the SATA one must be connected to a port considered :

```c
    case SYNO_DISK_SATA:
#ifdef MY_ABC_HERE
        ap = ata_shost_to_port(sdp->host);
         
        if (NULL != ap && !syno_is_synology_pm(ap)) {
#endif  
            gd->systemDisk = 1;
#ifdef MY_ABC_HERE
        }
```

*todo: add more flags*
