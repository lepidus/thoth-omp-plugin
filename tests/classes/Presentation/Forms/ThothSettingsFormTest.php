<?php

require_once __DIR__ . '/../../../../vendor/autoload.php';

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.form.validation.FormValidatorCustom');

final class ThothSettingsFormTest extends PKPTestCase
{
    public function testLoadsAndSavesTheHistoricalSettingsContractThroughApplicationDependencies(): void
    {
        $repository = new SettingsRepositoryDouble(
            new ThothConfiguration(true, 'https://api.example.test/graphql', 'stored-token')
        );
        $form = new ThothSettingsForm(
            new SettingsPluginDouble(),
            23,
            $repository,
            new ConfigurationVerifierDouble(),
            new SaveThothConfiguration($repository)
        );

        $form->initData();

        $this->assertTrue($form->getData('customThothApi'));
        $this->assertSame('https://api.example.test/graphql', $form->getData('customThothApiUrl'));
        $this->assertSame('stored-token', $form->getData('token'));

        $form->setData('customThothApi', false);
        $form->setData('customThothApiUrl', '  https://ignored.example.test  ');
        $form->setData('token', '  replacement-token  ');
        $form->execute();

        $this->assertSame(23, $repository->savedContextId);
        $this->assertNotNull($repository->savedConfiguration);
        $this->assertFalse($repository->savedConfiguration->usesCustomApi());
        $this->assertSame('https://ignored.example.test', $repository->savedConfiguration->customApiUrl());
        $this->assertSame('replacement-token', $repository->savedConfiguration->token());
    }

    public function testUsesTheInjectedVerifierForUrlReachabilityAndCredentials(): void
    {
        $repository = new SettingsRepositoryDouble(new ThothConfiguration(false, '', ''));
        $verifier = new ConfigurationVerifierDouble();
        $form = new ThothSettingsForm(
            new SettingsPluginDouble(),
            7,
            $repository,
            $verifier,
            new SaveThothConfiguration($repository)
        );
        $form->setData('customThothApi', true);
        $form->setData('customThothApiUrl', ' https://api.example.test/graphql ');
        $form->setData('token', ' token ');

        $customChecks = array_values(array_filter(
            $form->_checks,
            fn (object $check): bool => $check instanceof FormValidatorCustom
        ));
        $results = array_map(fn (FormValidatorCustom $check): bool => $check->isValid(), $customChecks);

        $this->assertSame([true, true, true, true], $results);
        $this->assertSame(['https://api.example.test/graphql'], $verifier->safeUrls);
        $this->assertSame(['https://api.example.test/graphql'], $verifier->reachableUrls);
        $this->assertNotNull($verifier->verifiedConfiguration);
        $this->assertSame('token', $verifier->verifiedConfiguration->token());
    }
}

final class SettingsRepositoryDouble implements ThothConfigurationRepository
{
    public ?int $savedContextId = null;
    public ?ThothConfiguration $savedConfiguration = null;

    private ThothConfiguration $configuration;
    public function __construct(ThothConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function get(int $contextId): ThothConfiguration
    {
        return $this->configuration;
    }

    public function save(int $contextId, ThothConfiguration $configuration): void
    {
        $this->savedContextId = $contextId;
        $this->savedConfiguration = $configuration;
    }
}

final class ConfigurationVerifierDouble implements ConfigurationVerifier
{
    public array $safeUrls = [];
    public array $reachableUrls = [];
    public ?ThothConfiguration $verifiedConfiguration = null;

    public function isApiUrlSafe(string $url): bool
    {
        $this->safeUrls[] = $url;

        return true;
    }

    public function isApiReachable(string $url): bool
    {
        $this->reachableUrls[] = $url;

        return true;
    }

    public function hasValidCredentials(ThothConfiguration $configuration): bool
    {
        $this->verifiedConfiguration = $configuration;

        return true;
    }
}

final class SettingsPluginDouble
{
    public function getTemplateResource(string $template): string
    {
        return 'templates/' . $template;
    }

    public function getName(): string
    {
        return 'ThothPlugin';
    }
}
