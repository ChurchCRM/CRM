#!/usr/bin/env python3
"""Extract a packaged ChurchCRM release, preserving dotfiles and rejecting links."""

import shutil
import stat
import sys
import zipfile
from pathlib import Path, PurePosixPath


def prepare(archive_path, destination):
    destination = Path(destination)
    if destination.exists() and any(destination.iterdir()):
        raise ValueError("Build context must be empty")
    with zipfile.ZipFile(archive_path) as archive:
        entries = []
        for entry in archive.infolist():
            path = PurePosixPath(entry.filename)
            if path.is_absolute() or ".." in path.parts or "\\" in entry.filename:
                raise ValueError("Unsafe archive path")
            if not path.parts or path.parts[0] != "churchcrm":
                raise ValueError("Expected churchcrm/ release prefix")
            mode = entry.external_attr >> 16
            if stat.S_IFMT(mode) not in (0, stat.S_IFREG, stat.S_IFDIR):
                raise ValueError("Archive links and special files are not allowed")
            if len(path.parts) > 1:
                entries.append((entry, Path(*path.parts[1:])))
        names = {str(path) for entry, path in entries if not entry.is_dir()}
        if not {"index.php", ".htaccess", "vendor/autoload.php"} <= names:
            raise ValueError("Release is missing required application files")
        if "Include/Config.php" in names or any(
            Path(name).name in (".env", ".npmrc", ".netrc", "auth.json")
            or Path(name).name.startswith(".env.")
            for name in names
        ):
            raise ValueError("Release contains deployment credentials")
        destination.mkdir(parents=True, exist_ok=True)
        for entry, path in entries:
            target = destination / path
            if entry.is_dir():
                target.mkdir(parents=True, exist_ok=True)
            else:
                target.parent.mkdir(parents=True, exist_ok=True)
                with archive.open(entry) as source, target.open("wb") as output:
                    shutil.copyfileobj(source, output)


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit("Usage: prepare-release-context.py ARCHIVE DESTINATION")
    prepare(sys.argv[1], sys.argv[2])
