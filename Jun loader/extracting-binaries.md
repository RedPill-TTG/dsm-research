# Jun's Loader Binaries

This instruction contains information how to extract binaries from the Jun's loader. 

To understand the meaning of these files see the [main `README`](README.md) for Jun's loader.

## Extracting binaries
Assuming you've got the loader its image:
  1. Attach it: `losetup -P /dev/loop1 synoboot_103b_ds3617xs.img`
  2. Verify it has the expected partitions:
     ```
     # fdisk -l /dev/loop1
     Disk /dev/loop1: 50 MiB, 52428800 bytes, 102400 sectors
     Units: sectors of 1 * 512 = 512 bytes
     Sector size (logical/physical): 512 bytes / 512 bytes
     I/O size (minimum/optimal): 512 bytes / 512 bytes
     Disklabel type: gpt
     Disk identifier: AFB38D11-BCEA-4409-B348-F4FEEE602114
     
     Device       Start    End Sectors Size Type
     /dev/loop1p1  2048  32767   30720  15M EFI System
     /dev/loop1p2 32768  94207   61440  30M Linux filesystem
     /dev/loop1p3 94208 102366    8159   4M BIOS boot
     ```
  3. Get the early kernel ramdisk
     1. `mkdir jun-3617xs-p2`
     2. `mount -o ro /dev/loop1p2 jun-3617xs-p2`
     3. `mkdir extra_lzma-3617xs`
     4. `cd extra_lzma-3617xs`
     4. `xz -dc < ../jun-3617xs-p2/extra.lzma | cpio -idmv`
  4. You should now be in a `extra_lzma-3617xs` folder with a `modprobe` file:
     ```
     # file usr/sbin/modprobe
     usr/sbin/modprobe: ELF 64-bit LSB executable, x86-64, version 1 (SYSV), statically linked, stripped
     
     # du -h usr/sbin/*
     36K	usr/sbin/modprobe
     
     # binwalk usr/sbin/modprobe

     DECIMAL       HEXADECIMAL     DESCRIPTION
     --------------------------------------------------------------------------------
     0             0x0             ELF, 64-bit LSB executable, AMD x86-64, version 1 (SYSV)
     872           0x368           ELF, 64-bit LSB relocatable, AMD x86-64, version 1 (SYSV)
     ```
     
     This is the file you will find as a `binaries/[loader]/modprobe.elf`.
  5. To get the kernel module itself you have to strip the first ELF header:  
     `dd if=usr/sbin/modprobe of=jun.ko bs=1 skip=872`  
     This is the file you will find as `binaries/[loader]/jun.ko`


## Special thanks
- [`Vortex` from Xpenology Community](https://xpenology.com/forum/profile/154-vortex/) for extracting Jun's LKM and 
  revealing where it was hidden

