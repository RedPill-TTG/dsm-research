# PCI Devices

Most computer systems consist of a CPU and a various of peripherals connected via various buses. The predominant bus 
nowadays is PCI (with its extension - PCI-Express). Before digging in it's important to understand that all IBM/PC
computers are a x86-class machines but not all x86-class machines are IBM/PC complain. A lot of NAS devices (DS 
included) are x86-class devices and thus reassembly IBM/PC in some shape or form.

In case of our NASes they contain a variety of PCI devices which are detected with the BIOS and later on configured by
the kernel to populate the device tree. On other platforms (e.g. ARM-based) PCI device tree is mostly static. All this 
means that if you know what your hardware platform *should* look like you can later on verify if it indeed looks like
you expected and react accordingly.


### What this does to the system?
DSM kernel doesn't do any crazy things within the PCI subsystem. However, according to [reports](../Jun%20loader) it 
later performs checks to verify if the hardware it's running on contains expected devices for the given platform. If not
it will start reporting errors.

This mechanism is believed to serve two purposes:
 - diagnostics (if a device is not there, and it should the hardware is probably malfunctioning)
 - verification whether it was run on the correct hardware

While the second can be seen as an anti-unofficial hardware measure, we don't believe it's truly the case. Reason being
that the checks are merely used for reporting errors. It looks like to us that it's a mechanism to prevent running
with device model specified as Y with an image made for X while actually running under hardware Z. Such configuration is
not that hard to create accidentally during dev & testing and will produce strange and hard to debug scenarios.


### Implementation
These check are probably implemented inside of the closed-source part of the DSM. No further details are known.


### How does it play with loaders?
There are multiple ways to ensure that the system looks correctly:
 1. Adjusting DSDT/SSDT to make sure BIOS reports correct hardware
 2. Shimming `pci_seq_{start|next|stop}` and other places like `/proc/bus/pci/devices` providing access to PCI devices
 3. Full PCI emulation

Each method has its pros and cons:
 - **DSDT/SSDT**
   - Most vanilla one and allows for the greatest control over the structure (after all kernel reads the config from 
     there)
   - Requires adapting for each individual hardware
   - Requires modification after changes in hardware
   - Varying difficulty level from "medium" to "insanely hard" depending of how broken your hardware implementation is 
     (and it is, for sure - thank Windows for the hacks needed)
   - Can be risky for the hardware if used improperly

 - **Shimming access methods** *(used by Jun's loader)*
   - The most hacky and potentially fragile of all
   - Introduces inconsistencies between different places where PCI can be read as the kernel doesn't really have these
     devices in its internal structures
   - Mocks the existence of devices to a degree but not their behavior
   - Easy to implement as you're dealing with already prepared data
   - Can easily be reversed by unloading the module which restores access points to vanilla state
   
 - **Full PCI emulation**
   - The most difficult method of all (quoting `Documentation/PCI/pci.txt`: *"The world of PCI is vast and full of 
     (mostly unpleasant) surprises."*)
   - Robust as the kernel doesn't have a split-brain situation (they really do exist, they're just virtual)
   - Uses officially supported PCI subsystem API
   - Offers full control over PCI devices emulation
     - Every device has configuration with way more fields than just VID+PID
     - Devices can obviously communicate back and forth with the system
     - This method allows for responding to the communication if needed
   - Linux doesn't have an off-the-shelf API for PCI virtualization. However, it can be accomplished with a low-level 
     API. Essentially a virtual PCI memory segment is needed, which is then read using callback passed to 
     [`pci_scan_bus()`](https://elixir.bootlin.com/linux/v3.10.108/source/drivers/pci/probe.c#L1910)
   - See https://github.com/dsm-redpill/redpill-lkm/blob/master/shim/pci_shim.c for details.
