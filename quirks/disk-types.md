# DSM Disk Types

/* Placeholder for the actual documentation */

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
