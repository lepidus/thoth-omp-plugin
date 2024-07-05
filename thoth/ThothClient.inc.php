<?php

/**
 * @file plugins/generic/thoth/thoth/ThothClient.inc.php
 *
 * Copyright (c) 2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothClient
 * @ingroup plugins_generic_thoth
 *
 * @brief Client for Thoth's API
 */

import('plugins.generic.thoth.thoth.ThothAuthenticator');

class ThothClient
{
    private $token;

    public const THOTH_API_URL = 'https://api.thoth.pub/';

    public function login($email, $password)
    {
        $authenticator = new ThothAuthenticator(self::THOTH_API_URL, $email, $password);
        $this->token = $authenticator->getToken();
    }
}
