# Kalmiya

[![Build Status](https://github.com/Innmind/Kalmiya/workflows/CI/badge.svg?branch=master)](https://github.com/Innmind/Kalmiya/actions?query=workflow%3ACI)
[![Type Coverage](https://shepherd.dev/github/Innmind/Kalmiya/coverage.svg)](https://shepherd.dev/github/Innmind/Kalmiya)

Personal tool to automatize most of the activities on my computer.

## Installation

```sh
composer global require innmind/kalmiya
```

## Usage

### Work

- `new` will create a new project on the machine with the backup remote and will open GitHub to create the repository

### Backups

- `backup` will copy all the files from the folders `Desktop`, `Documents`, `Downloads` and `Movies` from your home folder to an external drive that must be named `Backup` in a folder named after your user name.
- `restore` does the inverse operation of `backup`
