# AlmaLinux 9 Container Release Build

## Purpose

The host is AlmaLinux 10.2, so the AL9-compatible Linux node package must not be built directly on the host. This lane builds HobbyHash inside an `almalinux:9` container and keeps the standard Linux package untouched.

The separate AL9 output is:

```text
dist/HobbyHash-Linux-Node-AL9-x86_64.tar.gz
```

## What It Fixes

The current standard Linux package can fail on AlmaLinux 9 / RHEL 9 when it is linked against a newer host C++ runtime. Typical failures are:

```text
GLIBCXX_3.4.30 not found
GLIBCXX_3.4.31 not found
GLIBCXX_3.4.32 not found
CXXABI_1.3.15 not found
```

The container lane builds on AlmaLinux 9 and uses static C++ runtime linker flags:

```text
-static-libstdc++ -static-libgcc
```

## Files

```text
scripts/build-linux-al9-container.sh
packaging/al9/Containerfile
docs/AL9_CONTAINER_RELEASE_BUILD.md
```

## How To Run

Run from the source repo root:

```bash
bash scripts/build-linux-al9-container.sh
```

The script builds the Podman image:

```text
hobbyhash-al9-builder
```

Then it mounts the source repo into the container and performs the build inside the AlmaLinux 9 container.

## Build And Release Directories

The container lane uses separate paths:

```text
build-al9-container-x86_64
release-al9-container-x86_64
```

For Autotools builds, the script makes an isolated source copy at:

```text
build-al9-container-x86_64/source
```

This avoids cleaning or deleting the existing configured source tree.

## Package Contents

The tarball includes:

```text
hobbyhashd
hobbyhash-cli
hobbyhash-wallet
hobbyhash-tx
hobbyhash-util
```

All packaged binaries are installed executable.

## Verification

The script verifies:

```text
hobbyhashd --version
hobbyhash-cli --version
ldd hobbyhashd
ldd hobbyhash-cli
tar -tzf dist/HobbyHash-Linux-Node-AL9-x86_64.tar.gz
```

The script also checks that `ldd` output does not contain `not found`.

## Existing Standard Linux Package

The standard Linux package remains separate and untouched:

```text
/home/hobbyhashcoin/public_html/downloads/linux/HobbyHash-Linux-Node-x86_64.tar.gz
```

The container lane does not upload anything to the website and does not change the existing downloads page or standard Linux download link.
