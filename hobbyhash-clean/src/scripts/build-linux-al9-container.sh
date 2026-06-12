#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
IMAGE_NAME="hobbyhash-al9-builder"
CONTAINERFILE="$REPO_ROOT/packaging/al9/Containerfile"
PACKAGE_NAME="HobbyHash-Linux-Node-AL9-x86_64"
TARBALL_PATH="$REPO_ROOT/dist/$PACKAGE_NAME.tar.gz"
OLD_TARBALL_NAME="HobbyHash-Linux-Node-x86_64.tar.gz"
STANDARD_WEBSITE_TARBALL="/home/hobbyhashcoin/public_html/downloads/linux/$OLD_TARBALL_NAME"

if [[ "$(pwd -P)" != "$REPO_ROOT" ]]; then
  echo "Run this script from the source repo root: $REPO_ROOT" >&2
  exit 1
fi

if ! command -v podman >/dev/null 2>&1; then
  echo "podman is required to build the AL9 container release." >&2
  exit 1
fi

if [[ ! -f "$CONTAINERFILE" ]]; then
  echo "Missing container definition: $CONTAINERFILE" >&2
  exit 1
fi

if [[ "$PACKAGE_NAME.tar.gz" == "$OLD_TARBALL_NAME" ]]; then
  echo "Refusing to overwrite the existing Linux package name: $OLD_TARBALL_NAME" >&2
  exit 1
fi

old_targz_state=""
if [[ -f "$STANDARD_WEBSITE_TARBALL" ]]; then
  old_targz_state="$(stat -c '%s %Y' "$STANDARD_WEBSITE_TARBALL")"
  echo "Existing standard Linux package will not be touched: $STANDARD_WEBSITE_TARBALL"
fi

echo "Building AL9 builder image: $IMAGE_NAME"
podman build \
  --tag "$IMAGE_NAME" \
  --file "$CONTAINERFILE" \
  "$REPO_ROOT/packaging/al9"

echo "Running AL9 build inside container."
podman run --rm \
  --userns=keep-id \
  --user "$(id -u):$(id -g)" \
  --env HOME=/tmp \
  --env JOBS="${JOBS:-}" \
  --security-opt label=disable \
  --volume "$REPO_ROOT:/workspace:rw" \
  "$IMAGE_NAME" \
  bash -lc '
set -euo pipefail

source /etc/os-release
if [[ "${ID:-}" != "almalinux" || "${VERSION_ID:-}" != 9* ]]; then
  echo "This build must run inside an AlmaLinux 9 container." >&2
  exit 1
fi

REPO_ROOT="/workspace"
BUILD_DIR="$REPO_ROOT/build-al9-container-x86_64"
BUILD_SRC_DIR="$BUILD_DIR/source"
RELEASE_DIR="$REPO_ROOT/release-al9-container-x86_64"
PACKAGE_NAME="HobbyHash-Linux-Node-AL9-x86_64"
PACKAGE_DIR="$RELEASE_DIR/$PACKAGE_NAME"
DIST_DIR="$REPO_ROOT/dist"
TARBALL_PATH="$DIST_DIR/$PACKAGE_NAME.tar.gz"
OLD_TARBALL_NAME="HobbyHash-Linux-Node-x86_64.tar.gz"

BINARIES=(
  hobbyhashd
  hobbyhash-cli
  hobbyhash-wallet
  hobbyhash-tx
  hobbyhash-util
)

JOBS="${JOBS:-$(getconf _NPROCESSORS_ONLN 2>/dev/null || echo 2)}"
JOBS="${JOBS:-2}"

cd "$REPO_ROOT"

if [[ "$PACKAGE_NAME.tar.gz" == "$OLD_TARBALL_NAME" ]]; then
  echo "Refusing to overwrite the existing Linux package name: $OLD_TARBALL_NAME" >&2
  exit 1
fi

mkdir -p "$BUILD_DIR" "$RELEASE_DIR" "$DIST_DIR"
rm -rf "$BUILD_SRC_DIR" "$PACKAGE_DIR"
mkdir -p "$BUILD_SRC_DIR" "$PACKAGE_DIR"

tar \
  --exclude="./build-al9-x86_64" \
  --exclude="./build-al9-container-x86_64" \
  --exclude="./build-mingw-x86_64" \
  --exclude="./release-al9-x86_64" \
  --exclude="./release-al9-container-x86_64" \
  --exclude="./dist" \
  --exclude="./.git" \
  --exclude="./autom4te.cache" \
  -C "$REPO_ROOT" \
  -cf - . | tar -C "$BUILD_SRC_DIR" -xf -

find "$BUILD_SRC_DIR" \( -name ".deps" -o -name ".libs" -o -name "autom4te.cache" \) -type d -prune -exec rm -rf {} +
find "$BUILD_SRC_DIR" -type f \( -name "*.o" -o -name "*.lo" -o -name "*.la" \) -delete

rm -f \
  "$BUILD_SRC_DIR/config.log" \
  "$BUILD_SRC_DIR/config.status" \
  "$BUILD_SRC_DIR/libbitcoinconsensus.pc" \
  "$BUILD_SRC_DIR/libtool" \
  "$BUILD_SRC_DIR/Makefile" \
  "$BUILD_SRC_DIR/src/Makefile" \
  "$BUILD_SRC_DIR/src/config/bitcoin-config.h" \
  "$BUILD_SRC_DIR/src/config/stamp-h1"

for binary in "${BINARIES[@]}"; do
  rm -f "$BUILD_SRC_DIR/src/$binary"
done

if [[ ! -x "$BUILD_SRC_DIR/configure" ]]; then
  if [[ ! -x "$BUILD_SRC_DIR/autogen.sh" ]]; then
    echo "configure is missing and autogen.sh is not executable." >&2
    exit 1
  fi
  (cd "$BUILD_SRC_DIR" && ./autogen.sh)
fi

CONFIGURE_ARGS=(
  --with-daemon
  --with-utils
  --enable-wallet
  --enable-util-cli
  --enable-util-tx
  --enable-util-wallet
  --enable-util-util
  --without-gui
  --disable-tests
  --disable-bench
  --disable-zmq
  --without-miniupnpc
  --without-natpmp
)

if "$BUILD_SRC_DIR/configure" --help | grep -q -- "--without-bdb"; then
  CONFIGURE_ARGS+=(--without-bdb)
fi

(
  cd "$BUILD_SRC_DIR"
  env \
    CXXFLAGS="-O2" \
    LDFLAGS="-static-libstdc++ -static-libgcc" \
    ./configure "${CONFIGURE_ARGS[@]}"

  make -j"$JOBS" -C src "${BINARIES[@]}"
)

for binary in "${BINARIES[@]}"; do
  built_path="$BUILD_SRC_DIR/src/$binary"
  if [[ ! -f "$built_path" ]]; then
    echo "Required binary was not built: $binary" >&2
    exit 1
  fi
  install -m 0755 "$built_path" "$PACKAGE_DIR/$binary"
done

tar -C "$RELEASE_DIR" -czf "$TARBALL_PATH" "$PACKAGE_NAME"

echo
echo "Version checks:"
"$PACKAGE_DIR/hobbyhashd" --version
"$PACKAGE_DIR/hobbyhash-cli" --version

check_ldd() {
  local binary="$1"
  local output

  echo
  echo "ldd $binary:"
  output="$(ldd "$binary" 2>&1 || true)"
  printf "%s\n" "$output"
  if grep -q "not found" <<<"$output"; then
    echo "Missing shared library detected for $binary" >&2
    exit 1
  fi
}

check_ldd "$PACKAGE_DIR/hobbyhashd"
check_ldd "$PACKAGE_DIR/hobbyhash-cli"

echo
echo "Tarball contents:"
tar -tzf "$TARBALL_PATH"

if [[ ! -f "$TARBALL_PATH" ]]; then
  echo "Final tarball was not created: $TARBALL_PATH" >&2
  exit 1
fi

echo
echo "Created: $TARBALL_PATH"
'

if [[ ! -f "$TARBALL_PATH" ]]; then
  echo "Container build did not create $TARBALL_PATH" >&2
  exit 1
fi

if [[ -n "$old_targz_state" ]]; then
  new_targz_state="$(stat -c '%s %Y' "$STANDARD_WEBSITE_TARBALL")"
  if [[ "$new_targz_state" != "$old_targz_state" ]]; then
    echo "The standard Linux package changed unexpectedly: $STANDARD_WEBSITE_TARBALL" >&2
    exit 1
  fi
  echo "Verified standard Linux package was not changed: $STANDARD_WEBSITE_TARBALL"
fi

echo "AL9 container release package is ready: $TARBALL_PATH"
