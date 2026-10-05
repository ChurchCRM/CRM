import importlib.util
import stat
import sys
import tempfile
import unittest
import zipfile
from pathlib import Path

sys.dont_write_bytecode = True

spec = importlib.util.spec_from_file_location(
    "context", Path(__file__).with_name("prepare-release-context.py")
)
context = importlib.util.module_from_spec(spec)
spec.loader.exec_module(context)


class ReleaseContextTest(unittest.TestCase):
    def archive(self, root, extra=None):
        path = root / "release.zip"
        with zipfile.ZipFile(path, "w") as archive:
            for name in ("index.php", ".htaccess", "vendor/autoload.php"):
                archive.writestr("churchcrm/" + name, name)
            if extra:
                archive.writestr(*extra)
        return path

    def test_preserves_hidden_files_without_wrapper_directory(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            context.prepare(self.archive(root), root / "context")
            self.assertEqual((root / "context/.htaccess").read_text(), ".htaccess")
            self.assertFalse((root / "context/churchcrm").exists())

    def test_rejects_unsafe_paths_and_credentials_before_extraction(self):
        for name in ("churchcrm/../escaped", "/absolute", "other/index.php",
                     "churchcrm/Include/Config.php", "churchcrm/nested/.npmrc"):
            with self.subTest(name=name), tempfile.TemporaryDirectory() as tmp:
                root = Path(tmp)
                with self.assertRaises(ValueError):
                    context.prepare(self.archive(root, (name, "secret")), root / "context")
                self.assertFalse((root / "context").exists())

    def test_rejects_symlinks(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            link = zipfile.ZipInfo("churchcrm/link")
            link.create_system = 3
            link.external_attr = (stat.S_IFLNK | 0o777) << 16
            with self.assertRaises(ValueError):
                context.prepare(self.archive(root, (link, "/etc/passwd")), root / "context")

    def test_rejects_incomplete_archive(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            archive = root / "release.zip"
            with zipfile.ZipFile(archive, "w") as output:
                output.writestr("churchcrm/index.php", "test")
            with self.assertRaises(ValueError):
                context.prepare(archive, root / "context")

    def test_refuses_to_overlay_existing_context(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            with self.assertRaises(ValueError):
                context.prepare(self.archive(root), root)


if __name__ == "__main__":
    unittest.main()
