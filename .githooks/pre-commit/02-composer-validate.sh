#!/bin/sh
# Ensure `composer validate` checks pass
# Mostly to catch changes to composer.json without updating composer.lock

set -Ceu

exec composer validate -q
