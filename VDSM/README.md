# Virtual DSM (VDSM)

## Background
During investigation of "what to do next?" on Xpenology Community a user ilovepancakes suggested running VDSM instead of 
fighting with DS-intended DSM distribution. Here's *(somewhat scatter-brain)* documentation of the findings.

These texts were copied mostly 1:1 from my forum posts in the topic linked above. As they were made as I was discovering
things some details may be corrected in the later part of the text. After getting to the licensing part I decided to 
take a different approach - a [franken DSM](franken-dsm.md) ;)


## Glossary
  - **VMM**: Virtual Machine Manager; a QEmu-based hypervisor + GUI present in the DSM
  - **VDSM**: Virtual-DSM; a special distribution of DSM which can officially be run under VMM
  - **PAT**: an archive format used by Synology to distribute system packages


## Read more
  - [VDSM exploration](vdsm-investigation.md): getting sense of how it work and attempting to use it outside the VMM
  - [Franken DSM](franken-dsm.md): attempting to pair a DS (e.g. 3615xs) OS with VDSM kernel
