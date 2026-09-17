<?php

/** CLI-only fixtures for the single registration scenario; never performs registration. */

use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\cliTool\CommandLineTool;
use PKP\db\DAORegistry;

if (PHP_SAPI !== 'cli' || getenv('THOTH_DISPOSABLE') !== '1') {
    exit(1);
}
define('INDEX_FILE_LOCATION', getcwd() . '/index.php');
require './lib/pkp/classes/cliTool/CommandLineTool.php';
require __DIR__ . '/../../tests/environment/cypress-client.php';

try {
    $credentials = thothCypressCredentials();
    $tool = new CommandLineTool();
    $context = app()->get('context')->get(1);
    if ($context?->getPath() !== 'publicknowledge') {
        throw new RuntimeException('Expected Public Knowledge Press dataset');
    }
    $command = $argv[1] ?? '';
    if ($command === 'configure') {
        $settings = DAORegistry::getDAO('PluginSettingsDAO');
        $settings->updateSetting(1, 'thothplugin', 'enabled', true, 'bool');
        $settings->updateSetting(1, 'thothplugin', 'token', Crypt::encrypt($credentials['token']), 'string');
        echo "Disposable plugin configured\n";
    } elseif ($command === 'create') {
        $key = bin2hex(random_bytes(16));
        $title = 'Cypress button registration ' . $key;
        $submission = Repo::submission()->newDataObject([
            'contextId' => 1, 'locale' => 'en', 'status' => Submission::STATUS_PUBLISHED,
            'stageId' => WORKFLOW_STAGE_ID_PRODUCTION, 'submissionProgress' => '',
            'workType' => Submission::WORK_TYPE_AUTHORED_WORK,
        ]);
        $publication = Repo::publication()->newDataObject([
            'locale' => 'en', 'title' => ['en' => $title],
            'urlPath' => 'thoth-cypress-' . $key,
            'status' => Submission::STATUS_PUBLISHED, 'datePublished' => '2020-01-01',
        ]);
        $id = Repo::submission()->add($submission, $publication, $context);
        $fixture = ['key' => $key, 'submissionId' => $id, 'title' => $title,
            'imprintId' => $credentials['imprintId']];
        if (!is_dir('/tmp/thoth-cypress-fixtures')) {
            mkdir('/tmp/thoth-cypress-fixtures', 0700);
        }
        file_put_contents('/tmp/thoth-cypress-fixtures/' . $key . '.json', json_encode($fixture));
        echo json_encode($fixture, JSON_THROW_ON_ERROR);
    } elseif ($command === 'verify') {
        $key = $argv[2] ?? '';
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) {
            throw new RuntimeException('Invalid fixture key');
        }
        $fixture = json_decode(file_get_contents('/tmp/thoth-cypress-fixtures/' . $key . '.json'), true);
        // CLI has no request context: read persistence independently of plugin schema hooks.
        $workId = DB::table('submission_settings')
            ->where('submission_id', $fixture['submissionId'])
            ->where('setting_name', 'thothWorkId')->value('setting_value');
        if (!$workId) {
            throw new RuntimeException('Registration did not persist its Thoth link');
        }
        $request = curl_init('http://api:8000/graphql');
        curl_setopt_array($request, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => [
                'Content-Type: application/json', 'Authorization: Bearer ' . $credentials['token'],
            ], CURLOPT_POSTFIELDS => json_encode([
                'query' => 'query($id: Uuid!) { work(workId: $id) { workId workStatus workType '
                    . 'imprint { imprintId } titles { title } } }',
                'variables' => ['id' => $workId],
            ])]);
        $response = json_decode(curl_exec($request), true);
        if (curl_getinfo($request, CURLINFO_RESPONSE_CODE) !== 200 || !empty($response['errors'])) {
            throw new RuntimeException('Thoth verification query failed');
        }
        $work = $response['data']['work'];
        echo json_encode(['workId' => $work['workId'], 'workStatus' => $work['workStatus'],
            'workType' => $work['workType'], 'title' => $work['titles'][0]['title'],
            'imprintId' => $work['imprint']['imprintId']], JSON_THROW_ON_ERROR);
    } else {
        throw new RuntimeException('Expected configure, create or verify');
    }
} catch (Throwable $error) {
    // Do not print exceptions from HTTP clients, which may contain credentials.
    fwrite(STDERR, 'Cypress fixture failed (' . get_class($error) . ') at '
        . basename($error->getFile()) . ':' . $error->getLine() . ": check disposable setup\n");
    exit(1);
}
