#!/usr/bin/env bash

set -e

# First manual step before invoking this script is to run `xcode-select --install`
# (it's not done here as it will start an off process installer, that would require
# somehow to watch that the installer has done its job)

/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install.sh)"
brew install php
brew install composer
composer global require innmind/kalmiya
