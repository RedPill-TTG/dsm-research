# `boot_params` Validation

Kernel image shipped since build 25556 contains a previously unknown mechanism of [`boot_params` validation](https://elixir.bootlin.com/linux/v3.10.108/source/arch/x86/include/uapi/asm/bootparam.h#L111).
The structure is validated in a variety of ways. It is believed that majority of the validation is aimed towards
preventing physical DS boxes from being compromised by a malicious actor. The checks in place are meant to trigger a
tripwire if the chain of trust is broken.


### What this does to the system?
In short, if any of the checks fails system will not boot past the initramfs stage. The flag set by the checks will 
cause the kernel to **deliberately**:
  - disable `mount(MS_MOVE)` syscall
  - disable `mount(MS_BIND)` syscall


### Implementation
As of the time of writing Synology did not release GPL source code for the 25556 build. Thus, the analysis was performed
using a disassembled GPL kernel binary.

This feature contains two distinct parts:
 - `boot_params` validation, setting a flag
 - Checking of the flag in `fs/namespace.c:do_mount()`


#### How is the flag set?
At present this is not fully understood. Analyzing ASM of the kernel code is a mundane task knowing that sooner than 
later the GPL source code will be released. Given that, this section is left for the reader to explore in details.

You can easily locate the function performing the check. The method establishing the chain of trust is implemented as a
`initcall` and automatically called on the `postcore` level by the kernel's `init/main.c:do_initcall_level()`.
It can also be located using your favorite SRE by looking at the XREFs of `synoChainOfTrustBroken` (as marked in the
`do_mount()` description below).

The location of the function can be confirmed by byte-patching `UD2` (`0x0F 0x0B`) instruction somewhere in the initial 
stack growth and observing an early crash:
```
Kernel BUG at ffffffff818aa7c6 [verbose debug info unavailable]
invalid opcode: 0000 [#1] SMP
Modules linked in:
CPU: 0 PID: 1 Comm: swapper/0 Not tainted 3.10.105 #25556
Hardware name: QEMU Standard PC (Q35 + ICH9, 2009), BIOS rel-1.14.0-0-g155821a1990b-prebuilt.qemu.org 04/01/2014
task: ffff88000ee8d800 ti: ffff88000ee98000 task.ti: ffff88000ee98000
RIP: 0010:[<ffffffff818aa7c6>]  [<ffffffff818aa7c6>] dynamic_debug_init+0x267/0x3df
RSP: 0000:ffff88000ee9bee0  EFLAGS: 00010202
RAX: ffff88000ee9bfd8 RBX: 0000000000000000 RCX: ffff88000ee9bed0
RDX: ffffffff818572f0 RSI: 0000000000000246 RDI: ffffffff818aa7b7
RBP: ffffffff818aa7b7 R08: 0000000000000000 R09: 0000000000000000
R10: ffff88000eeb9c00 R11: 0000000000000001 R12: 0000000000000093
R13: 0000000000000000 R14: 0000000000000000 R15: 0000000000000000
FS:  0000000000000000(0000) GS:ffff88000f200000(0000) knlGS:0000000000000000
CS:  0010 DS: 0000 ES: 0000 CR0: 000000008005003b
CR2: ffff880001ac2000 CR3: 000000000180e000 CR4: 00000000000006f0
DR0: 0000000000000000 DR1: 0000000000000000 DR2: 0000000000000000
DR3: 0000000000000000 DR6: 00000000ffff0ff0 DR7: 0000000000000400
Stack:
 0000000000000000 ffffffff818aa7b7 0000000000000093 0000000000000000
 ffffffff8100038a ffffffff81928ce0 0000000000000002 0000000000000093
 0000000000000000 ffffffff81888e3d ffffffff814bc9c0 0000000000000000
Call Trace:
 [<ffffffff818aa7b7>] ? dynamic_debug_init+0x258/0x3df
 [<ffffffff8100038a>] ? do_one_initcall+0xca/0x180
 [<ffffffff81888e3d>] ? kernel_init_freeable+0x13a/0x1bb
 [<ffffffff814bc9c0>] ? rest_init+0x70/0x70
 [<ffffffff814bc9c5>] ? kernel_init+0x5/0x180
 [<ffffffff814cfc0d>] ? ret_from_fork+0x5d/0xb0
 [<ffffffff814bc9c0>] ? rest_init+0x70/0x70
Code: c7 c7 60 a2 83 81 e8 5a 06 c2 ff 31 c0 48 83 c4 10 5b 5d 41 5c 41 5d 41 5e 41 5f c3 66 81 3d 86 3d 09 00 08 02 41 55 41 54 55 53 <0f> 0b f0 80 0d b0 07 20 00 01 eb 44 48 8b 1d b7 3d 09 00 45 31
RIP  [<ffffffff818aa7c6>] dynamic_debug_init+0x267/0x3df
 RSP <ffff88000ee9bee0>
```

#### How is the flag validated?
The check guarding `MS_MOVE` and `MS_BIND` in `fs/namespace.c:do_mount()`, as implemented in build <25556, looks similar 
to this snippet:

  ```asm
  ; pseudocode, not the actual ASM in the binary
  MOV        retval,-0x1
  CMP        byte ptr [ramdisk_check_failed],0x0
  JNZ        dput_out
  ; execution continues
  ```
*(which roughly translates to `if (ramdisk_check_failed != '\0') goto dput_out;`)*

However, the code changed since 25556:
  ```asm
  ; pseudocode, not the actual ASM in the binary
  CMP        byte ptr [ramdisk_check_failed],0x0
  MOV        retval,-0x1
  JNZ        dput_out
  CMP        qword ptr [synoChainOfTrustBroken],0x0
  JNZ        dput_out
  ; execution continues
  ```
*(which roughly translates to `if ((ramdisk_check_failed != '\0') || (_synoChainOfTrustBroken != 0)) goto dput_out;`)*

The flow is slightly obscure to people not familiar with ASM. However, instructions in assembly are executed in order
and there are no blocks *per-se*. So in essence, here ramdisk flag is compared **THEN** `retval` is preemptively set to
`EPERM` and then a `JNZ` (jump-if-not-zero/false) is executed. However, if that jump is not executed (i.e. the ramdisk
wasn't tampered) it will do another comparison to an `synoChainOfTrustBroken`. If that check returns non-zero value it 
will jump to value return block. It will return `EPERM` as the `retval` is still set to the `EPERM`.

So in essence this check along with the `ramdisk_check_failed` is preventing `mount --bind` and `mound --move` from
working properly.


### How does it play with loaders?
Requires a ([tricky binary patching](README.md#kernel-binary-patching)) as the check happens WAY before any modules are
loaded, and the variable is not exported.

#### Kernel patches
Once I found it, patching this is *technically* easy. There are two methods:
  - patching `do_mount()`
  - adding a `NOOP`-sled or an immediate `RET` in the flag verification code
  
Currently, there's a tested patch for the first method. However, it's possible the flag will be used in more places,
and it may have more unknown side effects. Thus, the second method is preferred. 

##### Patching `do_mount()`
***THIS IS NOT RECOMMENDED - see next section***

The `do_mount()` patch relies on replacing a `JNZ` with `JL` (it's practically a `NOOP` but with the correct length as 
the pointer references an unsigned value).

  - **DS3615xs** with **25556** kernel
    The same pattern is present *twice* (once for `MS_BIND` and once for `MS_MOVE`).
    ```
    0f 85 cb fe ff ff     =to=>     0f 8c cb fe ff ff
    ```
  
  - **DS3617xs** with **25556** kernel  
    Two different patches are needed.

    - for `MS_REBIND` in `else`, around `ff81120eb7`
      ```
      48 83 3d b4 b1 98 00 00   CMP synoChainOfTrustBroken,0
      0f 85 cb fe ff ff         JNZ dput_out with -EPERM
    
      change JNZ to JL [<], as synoChainOfTrustBroken is never negative to:
      
      0f 8c cb fe ff ff         JL dput_out with -EPERM
      ```
      
    - for `MS_MOVE` around `fff81121194`
      ```
      48 83 3d e4 ae 98 00 00   CMP synoChainOfTrustBroken,0
      0f 85 fb fb ff ff         JNZ dput_out with -EPERM
  
      change JNZ to JL [<], as synoChainOfTrustBroken is never negative to:
      
      0f 8c fb fb ff ff         JL dput_out with -EPERM
      ```

##### Patching validation
  - **DS3615xs** with **25556** kernel  
    ~~After finding the method, its beginning can be patched with `RET` (`0xC3`).~~   
    ~~Patch the first instruction and fill with `NOP` (`0x90`) to the replaced instruction boundary.~~
    ```
    66 81 3d 86 3d 09 00 08 02    CMP word ptr [<boot_params....>],0x208
               =to=
    c3 90 90 90 90 90 90 90 90    RET, NOP*8
    ```
    This patch was left only for historical reasons - use [`boot_params` autopatcher](../tools/patch-boot_params-check.php) 
    which creates a more stable patch!  

  - **DS918+** with **25556** kernel  
    *You can use the [`boot_params` autopatcher](../tools/patch-boot_params-check.php) to apply the same changes automatically.*  
    
    The check actually seems to be a part of `swiotlb_free()` initcall, which must execute. Thus, it requires a 
    different approach - patching flag sets. 
    ```asm
    ; 4 patches, first line is to find, second to replace
    80 0d f4 f2 12 00 01      OR  byte ptr [synoChainOfTrustBroken],0x1
    80 25 f4 f2 12 00 01      AND byte ptr [synoChainOfTrustBroken],0x1
    
    80 0d 99 f2 12 00 02      OR  byte ptr [synoChainOfTrustBroken],0x2
    80 25 99 f2 12 00 02      AND byte ptr [synoChainOfTrustBroken],0x2
    
    80 0d 5f f2 12 00 04      OR  byte ptr [synoChainOfTrustBroken],0x4
    80 25 5f f2 12 00 04      OR  byte ptr [synoChainOfTrustBroken],0x4
    
    80 0d 4c f2 12 00 08      OR  byte ptr [synoChainOfTrustBroken],0x8
    80 25 4c f2 12 00 08      AND byte ptr [synoChainOfTrustBroken],0x8    
    ```

