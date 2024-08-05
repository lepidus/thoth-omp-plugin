<?php

/**
 * @file plugins/generic/thoth/thoth/ThothClient.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Copyright (c) 2024 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothClient
 * @ingroup plugins_generic_thoth
 *
 * @brief Client for Thoth's API
 */

import('plugins.generic.thoth.thoth.ThothAuthenticator');
import('plugins.generic.thoth.thoth.ThothGraphQL');
import('plugins.generic.thoth.thoth.ThothMutation');
import('plugins.generic.thoth.thoth.ThothQuery');

class ThothClient
{
    private $token;

    private $endpoint;

    private $httpClient;

    public const THOTH_ENDPOINT = 'https://api.thoth.pub/';

    public function __construct($endpoint = self::THOTH_ENDPOINT, $httpClient = null)
    {
        $this->endpoint = $endpoint;
        $this->httpClient = $httpClient ?? Application::get()->getHttpClient();
    }

    public function login($email, $password)
    {
        $authenticator = new ThothAuthenticator($this->endpoint, $this->httpClient, $email, $password);
        $this->token = $authenticator->getToken();
    }

    public function createWork($work)
    {
        return $this->mutation('createWork', $work);
    }

    public function createContributor($contributor)
    {
        return $this->mutation('createContributor', $contributor);
    }

    public function createContribution($contribution)
    {
        return $this->mutation('createContribution', $contribution);
    }

    public function createWorkRelation($workRelation)
    {
        return $this->mutation('createWorkRelation', $workRelation);
    }

    public function createPublication($publication)
    {
        return $this->mutation('createPublication', $publication);
    }

    public function createLocation($location)
    {
        return $this->mutation('createLocation', $location);
    }

    public function createSubject($subject)
    {
        return $this->mutation('createSubject', $subject);
    }

    public function createLanguage($language)
    {
        return $this->mutation('createLanguage', $language);
    }

    public function createReference($reference)
    {
        return $this->mutation('createReference', $reference);
    }

    private function mutation($name, $data)
    {
        $mutation = new ThothMutation($name, $data);
        $graphql = new ThothGraphQL($this->endpoint, $this->httpClient, $this->token);
        return $mutation->run($graphql);
    }

    private function query($name, $params, $queryClass)
    {
        $query = new ThothQuery($name, $params, $queryClass);
        $graphql = new ThothGraphQL($this->endpoint, $this->httpClient);
        return $query->run($graphql);
    }

    private function addParameter(&$params, $key, $value, $enclosed = false)
    {
        if ($value == '' || (is_array($value) && empty($value))) {
            return;
        }

        $params = $params ?? [];

        if (is_array($value)) {
            $params[] = implode(',', array_map(function ($subKey, $subValue) {
                return sprintf('%s:%s', $subKey, $subValue);
            }, array_keys($value), array_values($value)));
            return;
        }

        $params[] = sprintf('%s:%s', $key, $enclosed ? json_encode($value) : $value);
        return;
    }
}
