# AlmaLinux 9 / RHEL 9 Linux Node Build

## Why This Build Exists

The normal Linux node package remains available as the standard Linux download. The AL9 package is a separate compatibility build for AlmaLinux 9 and RHEL 9 systems.

This separate package exists so the current public Linux package and download link can stay untouched while AL9/RHEL 9 users get binaries built for their runtime environment.

## Problem Fixed

The existing Linux package, `HobbyHash-Linux-Node-x86_64.tar.gz`, was built against a newer C++ runtime than AlmaLinux 9 provides. On AL9/RHEL 9, that can fail with missing runtime symbols such as:

```text
GLIBCXX_3.4.30 not found
GLIBCXX_3.4.31 not found
GLIBCXX_3.4.32 not found
CXXABI_1.3.15 not found
```

The AL9 build script uses a separate build directory and links the C++ runtime statically where the toolchain supports it:

```text
-static-libstdc++ -static-libgcc
```

## How To Run

Run the script from the source repo root:

```bash
./scripts/build-linux-al9-release.sh
```

The script detects the build system. For this source tree, it uses the Autotools build and keeps all AL9 build outputs separate from the existing build. If the source tree has already been configured in-place, the script builds from an isolated copy at `build-al9-x86_64/source` instead of cleaning the existing tree.

## Output

The final tarball is written to:

```text
dist/HobbyHash-Linux-Node-AL9-x86_64.tar.gz
```

The package includes:

```text
hobbyhashd
hobbyhash-cli
hobbyhash-wallet
hobbyhash-tx
hobbyhash-util
```

The release staging directory is:

```text
release-al9-x86_64/HobbyHash-Linux-Node-AL9-x86_64
```

The build directory is:

```text
build-al9-x86_64
```

For Autotools builds, the isolated source copy is:

```text
build-al9-x86_64/source
```

## Original Linux Package

The original Linux package remains untouched:

```text
HobbyHash-Linux-Node-x86_64.tar.gz
```

The AL9 build script writes only:

```text
HobbyHash-Linux-Node-AL9-x86_64.tar.gz
```

It does not replace the current public Linux tarball and does not change the website download link.
