"""Validate resolved development boundaries without printing environment values."""

import json
import os
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path


class ComposeSecurityTest(unittest.TestCase):
    def render(self, files, profile=None, environment=None):
        with tempfile.TemporaryDirectory() as tmp:
            directory = Path(tmp)
            (directory / ".env").touch()
            command = ["docker", "compose"]
            for filename in files:
                source = Path(__file__).parent / filename
                target = directory / source.name
                shutil.copyfile(source, target)
                command.extend(["-f", str(target)])
            if profile:
                command.extend(["--profile", profile])
            command.extend(["config", "--format", "json"])
            env = {key: value for key, value in os.environ.items()
                   if not key.startswith(("DOCKER_BIND_", "MYSQL_", "DATABASE_", "WEBSERVER_", "MAILSERVER_"))}
            env.update(environment or {})
            return subprocess.run(command, env=env, capture_output=True, text=True)

    def test_development_ports_are_loopback_and_seeds_read_only(self):
        for files, profile in [(["docker-compose.yaml"], "test"),
                               (["docker-compose.dev.yaml"], None),
                               (["docker-compose.yaml", "docker-compose.parallel.yaml"], "ci-root"),
                               (["docker-compose.yaml", "docker-compose.parallel.yaml"], "ci-subdir"),
                               (["docker-compose.yaml", "docker-compose.parallel.yaml"], "ci-new-system")]:
            with self.subTest(profile=profile, files=files):
                result = self.render(files, profile)
                self.assertEqual(result.returncode, 0, "Compose validation failed")
                services = json.loads(result.stdout)["services"]
                self.assertTrue(any(service.get("ports") for service in services.values()))
                for service in services.values():
                    for port in service.get("ports", []):
                        self.assertEqual(port["host_ip"], "127.0.0.1")
                    for volume in service.get("volumes", []):
                        if volume["target"] == "/docker-entrypoint-initdb.d":
                            self.assertTrue(volume["read_only"])

    def test_explicit_interface_override(self):
        result = self.render(["docker-compose.dev.yaml"], environment={"DOCKER_BIND_ADDRESS": "192.0.2.10"})
        self.assertEqual(result.returncode, 0)
        for service in json.loads(result.stdout)["services"].values():
            for port in service.get("ports", []):
                self.assertEqual(port["host_ip"], "192.0.2.10")

    def test_production_examples_require_both_passwords(self):
        for filename in ("examples/docker-compose.nginx.yaml", "examples/docker-compose.frankenphp.yaml"):
            for environment in ({}, {"MYSQL_ROOT_PASSWORD": "test-root"}, {"MYSQL_PASSWORD": "test-app"}):
                self.assertNotEqual(self.render([filename], environment=environment).returncode, 0)
            self.assertEqual(self.render([filename], environment={
                "MYSQL_ROOT_PASSWORD": "test-root", "MYSQL_PASSWORD": "test-app"
            }).returncode, 0)


if __name__ == "__main__":
    unittest.main()
