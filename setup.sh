#!/usr/bin/env bash

set -e

if [[ "$PWD" != "$HOME" ]]; then
    echo 'setup.sh must be run in your home directory';
    exit 1;
fi

# First manual step before invoking this script is to run `xcode-select --install`
# (it's not done here as it will start an off process installer, that would require
# somehow to watch that the installer has done its job)

/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install.sh)"
brew install php@8.0
brew install composer
composer global require innmind/kalmiya
composer global exec 'kalmiya setup' -v
