# Synology GPL

### What's GPL?
As with most of the NAS manufacturers Synology takes work of the open-source community and integrates it into their 
products. A lot of software (especially low-level things touching the kernel) is licensed under GPL. This has a huge
implication on what they can, cannot, and have to do. This is **by no means** a summary of the GPL licensing, but in 
short they:

  - can use any of the code for free
  - can integrate GPL code into proprietary code
  - can modify the code and do whatever they want with it
  - can sell it and take all the profit
  - cannot change the license of a derivative work
  - have to include the original source used for building binaries
  - have to publish ALL changes they made to the source

There's [more to it](https://tldrlegal.com/license/gnu-general-public-license-v2) but it's not important for our 
discussion here.

### Synology & GPL
Synology has a long standing history of... well... not being so friendly to the open-source community. Historically they
didn't release the source code, later on they only gave it to selected people, then finally began releasing archives 
through [SourceForge](https://sourceforge.net/projects/dsgpl/files/Synology%20NAS%20GPL%20Source/). Notably, they are
released much later than the actual DSM updates utilizing them. Over the years the scope of released materials changed.

### Source Code Releases
As of 2021 the directory contains the following versions:
```
  631branch    2008-09-04
  722branch    2008-12-06
  844branch    2009-05-25
  944branch    2009-12-15
 1142branch    2010-05-03
 1337branch    2011-11-02
 1594branch    2011-11-02
 1742branch    2011-11-02
 1922branch    2011-11-04
 2198branch    2012-03-19
 2636branch    2012-12-06
 3201branch    2013-03-21
 3203branch    2013-06-10 (empty)
 3776branch    2013-09-11
 3810branch    2014-02-20 (contains ffmpeg v1 only)
 4418branch    2014-02-18
 4458branch    2014-05-02
 4482branch    2014-04-23 (contains ffmpeg v2 only)
 5004branch    2014-12-18
 5510branch    2017-09-13
 5565branch    2017-09-12
 5644branch    2017-09-12
 7321branch    2017-09-13
 8451branch    2017-03-27
15047branch    2017-09-13
15152branch    2017-08-22
22259branch    2017-11-01
24922branch    2020-09-11
25426branch    2021-01-19
7.0 Beta       2020-11-03
41890branch    2021-07-?? (pulled as of 2021/07/15, see https://sourceforge.net/p/dsgpl/discussion/862835/thread/a519b80124/)
```

### Scope
As the archives are shared in form of compressed `tar`s it's not easy to compare them. However, there were some notable
points in the 

 - Earlier releases (up to somewhere around 5004) the code contained in ~1.5GB archives looked like a complete OS code
 - Since ~5004 the code will no longer compile as some constants were replaced with `MY_ABC_HERE`
 - Releases >5004 removed `synobios` kernel module code from main branch directories
 - Newer releases contains a subdirectory `6281-source` with Linux v2.6.32 which still contains `synobios` code
 - Releases >5644 no longer publish the full 1.5GB archives but separate packages (e.g. for kernel or ssh)
 - Release 8451 the kernel does not `synobios` headers at all
 - Since 15047 the kernel does NOT contain full headers for `synobios` structures (in `include/linux/synobios.h`)
 

### Accessibility
As mentioned before the company shares GPL sources through SourceForge: https://sourceforge.net/projects/dsgpl/files/Synology%20NAS%20GPL%20Source/
The code is not shared in any repository. Instead, there are a ton of compressed archives. Over the years the 
organization of them changed and includes 3 different organization schemes:

- **`synogpl-<VERSION>.{tar.bz2|tbz|tgz}`** (~250-1.3GB) - full dump, single platform
    - *[todo: add internal structure here]*
    - Releases: from `631branch` to `1142branch`

- **`synogpl-<VERSION>-<PLATFORM>.tbz`** (~550-1600MB) - full dump with platform designation
    - *[todo: add internal structure here]*
    - Platforms:
        - `5281` (`1337branch` - `1742branch`)
        - `6180` (`1337branch` - `1922branch`)
        - `6281` (`1337branch` - `5004branch` >>)
        - `824x` (`1337branch` - `3201branch`)
        - `853x` (`1337branch` - `5004branch` >>)
        - `854x` (`1337branch` - `2198branch`)
        - `alpine` (`5004branch` - `5004branch` >>)
        - `armada370` (`3776branch` - `5004branch` >>)
        - `armada375` (`5004branch` - `5004branch` >>)
        - `armadaxp` (`3776branch` - `5004branch` >>)
        - `avoton` (`5004branch` - `5004branch` >>)
        - `bromolow` (`1594branch` - `5004branch` >>)
        - `cedarview` (`2198branch` - `5004branch` >>)
        - `comcerto2k` (`4458branch` - `5004branch` >>)
        - `evansport` (`3776branch` - `5004branch` >>)
        - `ppc` (`1337branch` - `3201branch`)
        - `qoriq` (`2636branch` - `5004branch` >>)
        - `x64` (`1337branch` - `5004branch` >>)
    - Releases: from `1337branch` to `5004branch`
    - Note: ranges ignore versions which were incomplete:
        - `3203branch` is empty
        - `3810branch` contains ffmpeg v1.0 only
        - `4482branch` contains ffmpeg v2.0 only
    - Notes
        - `>>` next to a version means it is available further (in the next format)

- **Full dump + additional libraries**
    - Types
        - `<PLATFORM>-source.txz` (~550-1600MB) - full dump with platform designation
        - `<PLATFORM>-<LIB_NAME>.txz` (small files)
    - *[todo: add internal structure here]*
    - Platforms/libs:
        - `6281`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `853x`
            - `-source` (<< `5510branch` - `5565branch`)
            - `-chroot` (`5510branch` - `5565branch`)
        - `alpine`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `armada370`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `armada375`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `armada38x`
            - `-source` (`5644branch` only >>)
            - `-chroot` (`5644branch` only)
        - `armadaxp`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `avoton`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `braswell`
            - `-source` (`5644branch` only >>)
            - `-chroot` (`5644branch` only)
        - `bromolow`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `cedarview`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `comcerto2k`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `evansport`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `monaco`
            - `-source` (`5644branch` only >>)
            - `-chroot` (`5644branch` only)
        - `qoriq`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
        - `x64`
            - `-source` (<< `5510branch` - `5565branch` >>)
            - `-chroot` (`5510branch` - `5565branch`)
    - Releases: from `5510branch` to `5644branch`
    - Notes
        - `<<` next to a version means it was available previously (in the previous format)
        - `>>` next to a version means it is available further (in the next format)

- **Components segregated by platform**
    - Types
        - `<VERSION>-source/<LIB_NAME>.txz` (mostly small files, except Linux)
    - *[todo: add internal structure here]*
    - Platforms:
        - `6281` (<< `7321branch` - )
        - `alpine` (<< `7321branch` - )
        - `alpine4k` (`7321branch` - )
        - `apollolake` (`15152branch` - )
        - `armada370` (<< `7321branch` - )
        - `armada375` (<< `7321branch` - )
        - `armada37xx` (`24922branch` - ) 
        - `armada38x` (<< `7321branch` - ) 
        - `armadaxp` (<< `7321branch` - )
        - `avoton` (<< `7321branch` - )
        - `braswell` (<< `7321branch` - )
        - `broadwell` (`7321branch` - ) => Linux v3
        - `broadwellnk` (`22259branch` - ) => Linux v4
        - `broadwellntbap` (`25426branch` - )
        - `bromolow` (<< `7321branch` - )
        - `cedarview` (<< `7321branch` - )
        - `comcerto2k` (<< `7321branch` - )
        - `denverton` (`15152branch`, `25426branch` - )
        - `dockerx64` (`7321branch` - )
        - `evansport` (<< `7321branch` - )
        - `geminilake` (`24922branch` - )
        - `grantley` (`7321branch` - )
        - `hi3535` (`7321branch` - `15152branch`, `24922branch` - )
        - `kvmx64` (`7321branch` - )
        - `monaco` (<< `7321branch` - )
        - `purley` (`24922branch` - )
        - `qoriq` (<< `7321branch` - )
        - `rtd1296-source` (`15152branch` - )
        - `v1000-source` (`25426branch` - )
        - `x64` (<< `7321branch` - )
    - Releases: from `7321branch` to `41890branch` (so far, probably will continue)
    - Notes
        - `<<` next to a version means it was available previously (in the previous format)


## Making it more accessible
We have a plan to make all GPL resources indexed & more accessible. This will involve mirroring them on GitHub and 
organizing them into repos like so:

```
[repo: linux-v...]
 [branch: bromolow]
   [tag: build-24922]
   [tag: build-25426]
 [branch: bromowell]
   [tag: build-24922]
   [tag: build-25426]
 [branch: x64]
   [tag: build-24922]
   [tag: build-25426]

[repo: openssh-v...]
 [branch: bromolow]
   [tag: build-24922]
   [tag: build-25426]
 [branch: bromowell]
   [tag: build-24922]
   [tag: build-25426]
 [branch: x64]
   [tag: build-24922]
   [tag: build-25426]
```

Since GitHub allows for storing (reasonably) unlimited amount of binary/packed files, the source archives (i.e. 1:1 from
SF) will be kept as GitHub Releases. Probably this will need one or more separate repos as not everything is organized
per-module in SF.

It will allow for an easy comparison and searching. Additionally, to make the process at least half-automatic (more so 
to keep it consistent) we will keep the list of all libs/modules/archives as a single JSON file. This will also allow
for a very easy generation of GH Pages with a matrix of features/libs per version/platform.

```json
{
  "formats": {
    "synogpl-full-platformless": {
      "collection_url": "https://sourceforge.net/projects/dsgpl/files/Synology%20NAS%20GPL%20Source/@@version@@branch/synogpl-@@version@@.@@file_ext@@/download",
      "component_url": null,
      "has_platform": false
    },
    "synogpl-full-platform": {
      "collection_url": "https://sourceforge.net/projects/dsgpl/files/Synology%20NAS%20GPL%20Source/@@version@@branch/synogpl-@@version@@-@@platform@@.tbz/download",
      "component_url": null,
      "has_platform": true
    },
    "mixed-platform": {
      "collection_url": "https://sourceforge.net/projects/dsgpl/files/Synology%20NAS%20GPL%20Source/@@version@@branch/@@platform@@-source.txz/download",
      "component_url": "https://sourceforge.net/projects/dsgpl/files/Synology%20NAS%20GPL%20Source/@@version@@branch/@@platform@@-@@component@@.txz/download",
      "has_platform": true
    },
    "unpacked-components": {
      "collection_url": null,
      "component_url": "https://sourceforge.net/projects/dsgpl/files/Synology%20NAS%20GPL%20Source/@@version@@branch/@@platform@@-source/@@component@@.txz/download",
      "has_platform": true      
    }
  },
  "git": {
    "gh_root": "github.com/user-or-org",
    "repository_name": ""
  },
  "releases": {
    "build-5510": {
      "format": "mixed-platform",
      "vars": {
        "version": 5510
      },
      "components": {
        "linux-3.10.x": { "platforms": "*", "source": "collection_url", "source_path": "sources/linux-3.10.x/" },
        "chroot": { "platforms": "*", "source": "component_url", "source_path": "/" }
      }
    },
    "build-25426": {
      "format": "unpacked-components",
      "vars": {
        "version": 25426
      },
      "components": {
        "linux-3.10.x": { "platforms": "*", "source": "component_url", "source_path": "linux-3.10.x/" }
      }
    }
  }
}
```

