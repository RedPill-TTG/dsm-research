# Kernel `cmdline` validation

When the kernel boots, it saves all kernel options passed to it during boot. This information is written pretty early to 
the kernel's log (can be viewed e.g. using `dmesg`). Programmatically it is also available in `/proc/cmdline`.

[Allegedly, this `/proc/cmdline` is scrutinized by the DSM](https://xpenology.com/forum/topic/6253-dsm-61x-loader/?do=findComment&comment=54229) 
and must not contain any "suspicious" options (i.e. anything which doesn't look like a standard kernel option nor DSM 
kernel [custom option](dsm-kernel-params.md)).


### What this does to the system?
Unknown.


### Implementation
These check is probably implemented inside of the closed-source part of the DSM. No further details are known.


### How does it play with loaders?
Loaders must mask `/proc/cmdline` on the kernel level and possibly modify `dmesg` ring buffer. Jun's loader seems to
only do the former and removes all options defined in the loader (see [his loader description](../Jun%20loader/README.md#configuration)).

Most likely only the options which aren't REALLY possible on a given hardware should be removed (e.g. `SasIdxMap=` on a 
DS without SAS) + all custom ones (e.g. `vid=`).

See https://github.com/RedPill-TTG/redpill-lkm/blob/master/internal/stealth/sanitize_cmdline.c for details.
