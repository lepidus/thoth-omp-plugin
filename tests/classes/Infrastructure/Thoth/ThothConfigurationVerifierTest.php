<?php

import('lib.pkp.tests.PKPTestCase');
import('plugins.generic.thoth.classes.Domain.Configuration.ThothConfiguration');
import('plugins.generic.thoth.classes.Infrastructure.Thoth.ThothConfigurationVerifier');
import('plugins.generic.thoth.classes.security.ThothApiUrlValidator');

class ThothConfigurationVerifierTest extends PKPTestCase
{
    public function testChecksSafeEndpointAndCredentialsWithoutRedirects()
    {
        $clientConfigs = [];
        $client = new class () {
            public $token;
            public $publisherCountCalled = false;
            public $meCalled = false;

            public function publisherCount()
            {
                $this->publisherCountCalled = true;
            }

            public function setToken($token)
            {
                $this->token = $token;
                return $this;
            }

            public function me()
            {
                $this->meCalled = true;
            }
        };
        $verifier = new ThothConfigurationVerifier(
            new ThothApiUrlValidator(function () {
                return ['93.184.216.34'];
            }),
            function (array $config) use (&$clientConfigs, $client) {
                $clientConfigs[] = $config;
                return $client;
            }
        );

        $this->assertTrue($verifier->isApiReachable('https://api.example.test/graphql'));
        $this->assertTrue($verifier->hasValidCredentials(
            new ThothConfiguration(true, 'https://api.example.test/graphql', 'plain-token')
        ));
        $this->assertSame([
            ['base_uri' => 'https://api.example.test/graphql', 'allow_redirects' => false],
            ['base_uri' => 'https://api.example.test/graphql', 'allow_redirects' => false],
        ], $clientConfigs);
        $this->assertTrue($client->publisherCountCalled);
        $this->assertSame('plain-token', $client->token);
        $this->assertTrue($client->meCalled);
    }

    public function testRejectsUnsafeEndpointBeforeCreatingClient()
    {
        $clientCreated = false;
        $verifier = new ThothConfigurationVerifier(
            new ThothApiUrlValidator(function () {
                return ['127.0.0.1'];
            }),
            function () use (&$clientCreated) {
                $clientCreated = true;
            }
        );

        $this->assertFalse($verifier->isApiReachable('https://127.0.0.1/graphql'));
        $this->assertFalse($verifier->hasValidCredentials(
            new ThothConfiguration(true, 'https://127.0.0.1/graphql', 'plain-token')
        ));
        $this->assertFalse($clientCreated);
    }
}
