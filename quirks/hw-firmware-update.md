# Low-level BIOS update

Every device has multiple layers of execution upon boot. It's no different with Synology DS devices. As majority of them
are x86-based systems we can expect them to contain familiar elements as they run a modified version of Linux.

Besides PMU the motherboard of (at least some) DSs contains a BIOS which presumably servers a role akin to a normal PC 
BIOS. PAT files containing DSM also ship with some BIOS-related stuff in place:

```
$ tar -tvf DSM_DS918+_25556.pat
...
-rwxr-xr-x root/root   1046607 2021-03-18 07:57 H2OFFT-Lx64
-rwxr-xr-x root/root   9142600 2021-03-18 07:57 bios.ROM
-rwxr-xr-x root/root     40543 2021-03-18 07:57 platform.ini
....
-rw-r--r-- root/root      1913 2021-03-18 07:57 checksum.syno
```

The `H2OFFT-Lx64` file is an off-the-shelf [*Insyde BIOS update utility*](https://help.ubuntu.com/community/FimwareUpgrade/Insyde)
which most likely reads the `bios.ROM` and updates the BIOS according to the `platform.ini` configuration. These files,
as everything in the PAT, are protected by the checksum:

```
$ tar -xvf DSM_DS918+_25556.pat checksum.syno
checksum.syno
$ cat checksum.syno | grep 'bios.ROM\|H2OFFT'
864353750 1046607 H2OFFT-Lx64 2032129 1
291852179 9142600 bios.ROM 12339783 765
```

### What this does to the system?
As we saw during [Franken DSM investigation](../VDSM/franken-dsm.md) (*search for `H2OFFT-Lx64`*) attempting to run the
updater, even under a VM, causes an instant crash of the machine. This is expected as for sure Insyde uses some internal
and undocumented methods to trigger BIOS update. Even worse, if our machine had Insyde-complaint BIOS flashing the
`bios.ROM` **could result in a bricked system**. However this is unlikely as the installer seems to do *some* checking.

Since the BIOS update is triggered every time a PAT is installed, the files are protected by the checksum, and we cannot
allow the update to start a circumvention of that needs to be implemented to even install the OS.


### Implementation
The firmware handling process is handled partially by `updater` (present in the root of a PAT file) and partially by 
the Insyde flasher tool (`H2OFFT-Lx64`). The flasher doesn't seem to be run blindly (fortunately!) since running the 
updater just like that produces the following log, just before the installation aborts itself:

```
synocodesign: BLAKE2b-256 (bios.ROM) = a61a3746c63b47f7808a720556cbe6bed147132b2d61364a82e15bf9296515c1
synocodesign: bios.ROM: OK
//...
updater: updater.c:2062 Bios upgrade needed file is not exist for 3.10.105
updater: updater.c:7082 fail to upgrade BIOS
```

While this message is nonsensical it gives some clue to the problem. See next section.


### How does it play with loaders?
There are two methods to go around that problem. The simplest relies on the vulnerability of the checksum code. It first 
reads the files and verified checksums and then, way later, actually uses them. This makes it easy to circumvent the
BIOS update by deleting `bios.ROM` **after** checksum is verified but **before** `H2OFFT-Lx64` can read it:

```
rm /tmp/checksum.syno.tmp
while [ ! -f /tmp/checksum.syno.tmp ]; do true; done; rm '/tmpData/upd@te/bios.ROM'
```

However, this method is clunky and relies on the fact that updater ignores the obvious error of `bios.ROM` not being 
present. 

---

Using LKM approach is harder but nicer. Jun's loader seems to block any execution of 
`./H2OFFT-Lx64`:

```
$ md5sum ./a.out H2OFFT-Lx64
2d30a1d3aa7c034228464fb3768e0f0c  ./a.out
2d30a1d3aa7c034228464fb3768e0f0c  H2OFFT-Lx64
$ ./a.out
Hello world!
$ ./H2OFFT-Lx64
$ echo $?
0
$ mv ./H2OFFT-Lx64 derp
$ ./derp
Hello world!
```

The simplest way to achieve that is to hijack the `execve(2)` syscall and respond instantly instead of executing the 
real file. This way when the checksum code reads the file the checksum will match, but when it's executed it will be 
nooped.


Additionally, the updater, as mentioned in the "Implementation" section, does check if the motherboard to be flashed is
indeed a correct one. However, if the check doesn't pass we get a nonsensical error message. Jun loader roughly replaces
one function in the kernel (`dmi_get_system_info()`) to return bogus data to get around that.


See https://github.com/RedPill-TTG/redpill-lkm/blob/master/shim/block_fw_update_shim.c for details.
