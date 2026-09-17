"""Focused checks for safe planning and error handling, without Docker."""
import pathlib
import shutil
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
from urllib.error import HTTPError

from common import graphql, request


class EnvironmentTests(unittest.TestCase):
    def test_plan_does_not_create_state_or_call_docker(self):
        with tempfile.TemporaryDirectory() as temp:
            script = pathlib.Path(temp) / 'environment.py'
            shutil.copyfile(pathlib.Path(__file__).with_name('environment.py'), script)
            for action in ['up', 'down', 'smoke']:
                result = subprocess.run([sys.executable, str(script), action],
                                        env={'PATH': ''}, capture_output=True, text=True)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertIn('Plan:', result.stdout)
            self.assertFalse((pathlib.Path(temp) / '.state').exists())

    def test_cypress_plan_requires_dataset_and_never_calls_docker(self):
        with tempfile.TemporaryDirectory() as temp:
            root = pathlib.Path(temp)
            script = root / 'environment.py'
            shutil.copyfile(pathlib.Path(__file__).with_name('environment.py'), script)
            command = [sys.executable, str(script), 'cypress', '--dataset', str(root)]
            invalid = subprocess.run(command, env={'PATH': ''}, capture_output=True, text=True)
            self.assertNotEqual(invalid.returncode, 0)
            (root / 'database.sql').touch()
            (root / 'files').mkdir()
            (root / 'public').mkdir()
            plan = subprocess.run(command, env={'PATH': ''}, capture_output=True, text=True)
            self.assertEqual(plan.returncode, 0, plan.stderr)
            self.assertIn('omp-db/thoth_cypress only', plan.stdout)
            self.assertFalse((root / '.state').exists())

    def test_invalid_port_is_rejected_before_mutation(self):
        result = subprocess.run([sys.executable, str(pathlib.Path(__file__).with_name('environment.py')),
                                 'up', '--apply', '--port', '80'], capture_output=True, text=True)
        self.assertNotEqual(result.returncode, 0)
        self.assertIn('--port must be', result.stderr)

    def test_http_error_reports_status_without_response_secrets(self):
        error = HTTPError('http://zitadel:8080/path', 403, 'secret-response', {}, None)
        with patch('urllib.request.urlopen', side_effect=error):
            with self.assertRaisesRegex(RuntimeError, 'HTTP 403 from GET zitadel:8080/path') as raised:
                request('http://zitadel:8080/path', 'secret-token')
        self.assertNotIn('secret', str(raised.exception))

    def test_graphql_errors_are_not_mistaken_for_success(self):
        with patch('common.request', return_value={'data': {'createWork': None}, 'errors': [{'message': 'secret'}]}):
            with self.assertRaisesRegex(RuntimeError, 'GraphQL operation failed'):
                graphql('http://api:8000', 'token', '{ me { userId } }')


if __name__ == '__main__':
    unittest.main()
