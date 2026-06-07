#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

BUILD_DIR="$REPO_ROOT/build-al9-x86_64"
AUTOTOOLS_SRC_DIR="$BUILD_DIR/source"
RELEASE_DIR="$REPO_ROOT/release-al9-x86_64"
PACKAGE_NAME="HobbyHash-Linux-Node-AL9-x86_64"
PACKAGE_DIR="$RELEASE_DIR/$PACKAGE_NAME"
DIST_DIR="$REPO_ROOT/dist"
TARBALL_NAME="$PACKAGE_NAME.tar.gz"
TARBALL_PATH="$DIST_DIR/$TARBALL_NAME"
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

if [[ "$TARBALL_NAME" == "$OLD_TARBALL_NAME" ]]; then
  echo "Refusing to overwrite the existing Linux package name: $OLD_TARBALL_NAME" >&2
  exit 1
fi

if [[ -f "$DIST_DIR/$OLD_TARBALL_NAME" ]]; then
  echo "Existing Linux package found and will not be touched: $DIST_DIR/$OLD_TARBALL_NAME"
fi

if [[ -d "$REPO_ROOT/depends" ]]; then
  echo "depends/ detected; using native host dependencies for the AL9 build."
  echo "Set AL9_CONFIG_SITE=/absolute/path/to/separate/al9/config.site to use a separate depends prefix."
fi

build_autotools() {
  mkdir -p "$BUILD_DIR"
  rm -rf "$AUTOTOOLS_SRC_DIR"
  mkdir -p "$AUTOTOOLS_SRC_DIR"

  tar \
    --exclude='./build-al9-x86_64' \
    --exclude='./release-al9-x86_64' \
    --exclude='./dist' \
    --exclude='./.git' \
    --exclude='./autom4te.cache' \
    -C "$REPO_ROOT" \
    -cf - . | tar -C "$AUTOTOOLS_SRC_DIR" -xf -

  rm -f \
    "$AUTOTOOLS_SRC_DIR/config.log" \
    "$AUTOTOOLS_SRC_DIR/config.status" \
    "$AUTOTOOLS_SRC_DIR/libbitcoinconsensus.pc" \
    "$AUTOTOOLS_SRC_DIR/libtool" \
    "$AUTOTOOLS_SRC_DIR/Makefile" \
    "$AUTOTOOLS_SRC_DIR/src/Makefile" \
    "$AUTOTOOLS_SRC_DIR/src/config/bitcoin-config.h" \
    "$AUTOTOOLS_SRC_DIR/src/config/stamp-h1"

  if [[ ! -x "$AUTOTOOLS_SRC_DIR/configure" ]]; then
    if [[ ! -x "$AUTOTOOLS_SRC_DIR/autogen.sh" ]]; then
      echo "configure is missing and autogen.sh is not executable." >&2
      exit 1
    fi
    (cd "$AUTOTOOLS_SRC_DIR" && ./autogen.sh)
  fi

  local config_site_env=()
  if [[ -n "${AL9_CONFIG_SITE:-}" ]]; then
    if [[ "$AL9_CONFIG_SITE" != /* ]]; then
      echo "AL9_CONFIG_SITE must be an absolute path." >&2
      exit 1
    fi
    config_site_env=(CONFIG_SITE="$AL9_CONFIG_SITE")
  fi

  (
    cd "$AUTOTOOLS_SRC_DIR"
    env \
      "${config_site_env[@]}" \
      CXXFLAGS="-O2" \
      LDFLAGS="-static-libstdc++ -static-libgcc" \
      ./configure \
        --with-daemon \
        --with-utils \
        --enable-wallet \
        --enable-util-cli \
        --enable-util-tx \
        --enable-util-wallet \
        --enable-util-util \
        --without-gui \
        --disable-tests \
        --disable-bench \
        --disable-zmq \
        --without-miniupnpc \
        --without-natpmp

    make -j"$JOBS" -C src "${BINARIES[@]}"
  )
}

cmake_target_exists() {
  local target="$1"
  cmake --build "$BUILD_DIR" --target help 2>/dev/null | grep -E "^[[:space:]]*(\\.\\.\\.)?[[:space:]]*$target$" >/dev/null
}

build_cmake() {
  mkdir -p "$BUILD_DIR"

  cmake \
    -S "$REPO_ROOT" \
    -B "$BUILD_DIR" \
    -DCMAKE_BUILD_TYPE=Release \
    -DCMAKE_CXX_FLAGS="-O2" \
    -DCMAKE_EXE_LINKER_FLAGS="-static-libstdc++ -static-libgcc"

  local existing_targets=()
  local missing_targets=()
  local binary
  for binary in "${BINARIES[@]}"; do
    if cmake_target_exists "$binary"; then
      existing_targets+=("$binary")
    else
      missing_targets+=("$binary")
    fi
  done

  if [[ "${#existing_targets[@]}" -gt 0 && "${#missing_targets[@]}" -eq 0 ]]; then
    cmake --build "$BUILD_DIR" --parallel "$JOBS" --target "${existing_targets[@]}"
  else
    if [[ "${#missing_targets[@]}" -gt 0 ]]; then
      echo "CMake target names not found for: ${missing_targets[*]}"
      echo "Building the configured project and then verifying required binaries."
    fi
    cmake --build "$BUILD_DIR" --parallel "$JOBS"
  fi
}

detect_and_build() {
  if [[ -f "$REPO_ROOT/configure.ac" || -x "$REPO_ROOT/configure" ]]; then
    echo "Detected Autotools build system."
    build_autotools
  elif [[ -f "$REPO_ROOT/CMakeLists.txt" ]]; then
    echo "Detected CMake build system."
    build_cmake
  else
    echo "Could not detect a supported build system in $REPO_ROOT." >&2
    exit 1
  fi
}

find_built_binary() {
  local binary="$1"
  local candidates=(
    "$BUILD_DIR/src/$binary"
    "$BUILD_DIR/$binary"
    "$AUTOTOOLS_SRC_DIR/src/$binary"
    "$REPO_ROOT/src/$binary"
  )

  local candidate
  for candidate in "${candidates[@]}"; do
    if [[ -f "$candidate" ]]; then
      printf '%s\n' "$candidate"
      return 0
    fi
  done

  return 1
}

stage_release() {
  mkdir -p "$RELEASE_DIR" "$DIST_DIR"
  rm -rf "$PACKAGE_DIR"
  mkdir -p "$PACKAGE_DIR"

  local binary
  local built_path
  for binary in "${BINARIES[@]}"; do
    if ! built_path="$(find_built_binary "$binary")"; then
      echo "Required binary was not built: $binary" >&2
      exit 1
    fi
    install -m 0755 "$built_path" "$PACKAGE_DIR/$binary"
  done

  chmod 0755 "$PACKAGE_DIR"/*

  tar -C "$RELEASE_DIR" -czf "$TARBALL_PATH" "$PACKAGE_NAME"
}

verify_release() {
  echo
  echo "Packaged binaries:"

  local binary
  for binary in "${BINARIES[@]}"; do
    file "$PACKAGE_DIR/$binary"
  done

  echo
  ldd "release-al9-x86_64/HobbyHash-Linux-Node-AL9-x86_64/hobbyhashd" || true
  ldd "release-al9-x86_64/HobbyHash-Linux-Node-AL9-x86_64/hobbyhash-cli" || true

  echo
  ./release-al9-x86_64/HobbyHash-Linux-Node-AL9-x86_64/hobbyhashd --version
  ./release-al9-x86_64/HobbyHash-Linux-Node-AL9-x86_64/hobbyhash-cli --version

  if [[ ! -f "$TARBALL_PATH" ]]; then
    echo "Final tarball was not created: $TARBALL_PATH" >&2
    exit 1
  fi

  echo
  echo "Created: $TARBALL_PATH"
}

detect_and_build
stage_release
verify_release
