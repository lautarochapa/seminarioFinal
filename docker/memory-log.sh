#!/usr/bin/env bash
set -euo pipefail

log_memory() {
    local root="${1:-/sys/fs/cgroup}" used limit inactive=0 key value working
    if [[ -r "$root/memory.current" ]]; then
        read -r used < "$root/memory.current"
        read -r limit < "$root/memory.max"
    elif [[ -r "$root/memory/memory.usage_in_bytes" ]]; then
        root="$root/memory"
        read -r used < "$root/memory.usage_in_bytes"
        read -r limit < "$root/memory.limit_in_bytes"
    else
        return 0
    fi

    if [[ -r "$root/memory.stat" ]]; then
        while read -r key value; do
            if [[ "$key" == inactive_file || "$key" == total_inactive_file ]]; then
                inactive="$value"
            fi
        done < "$root/memory.stat"
    fi
    [[ "$used" =~ ^[0-9]+$ && "$inactive" =~ ^[0-9]+$ ]] || return 0
    [[ "$limit" == max || "$limit" =~ ^[0-9]+$ ]] || return 0
    working=$((used > inactive ? used - inactive : 0))
    printf '[runtime-memory] used_bytes=%s working_set_bytes=%s limit_bytes=%s\n' \
        "$used" "$working" "$limit"
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
    while true; do
        log_memory
        sleep 60
    done
fi
