# Kalmiya

[![Build Status](https://github.com/Innmind/Kalmiya/workflows/CI/badge.svg)](https://github.com/Innmind/Kalmiya/actions?query=workflow%3ACI)
[![Type Coverage](https://shepherd.dev/github/Innmind/Kalmiya/coverage.svg)](https://shepherd.dev/github/Innmind/Kalmiya)

Personal tool to automatize most of the activities on my computer.

## Installation

```sh
composer global require innmind/kalmiya
```

## Usage

#### Apple Music

**Note**: You need a developer account to be able to use the commands below, as you need to create an app in your Apple developer account (see [documentation](https://help.apple.com/developer-account/#/devce5522674)).

- `music:library` will list all the albums from your library, useful in case you want to switch to a different streaming platform
- `music:releases` will list all the new albums/EPs/singles from the artits in your library, the goal is to never miss a new release

### Backups

- `backup` will copy all the files from the folders `Desktop`, `Documents`, `Downloads` and `Movies` from your home folder to an external drive that must be named `Backup` in a folder named after your user name.
- `restore` does the inverse operation of `backup`

### Setup

```sh
cd $HOME && bash -c "$(curl -fsSL https://raw.githubusercontent.com/Innmind/Kalmiya/master/setup.sh)"
```
