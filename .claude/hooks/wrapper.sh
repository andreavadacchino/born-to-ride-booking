#!/bin/bash
# Wrapper per gestire path con spazi
SCRIPT_PATH="$1"
shift
exec python3 "$SCRIPT_PATH" "$@"