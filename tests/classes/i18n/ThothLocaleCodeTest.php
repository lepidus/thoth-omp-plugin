<?php

/**
 * @file plugins/generic/thoth/tests/classes/i18n/ThothLocaleCodeTest.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth Open Metadata
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThothLocaleCodeTest
 *
 * @ingroup plugins_generic_thoth_tests
 *
 * @see ThothLocaleCode
 *
 * @brief Test class for the ThothLocaleCode helper
 */

namespace APP\plugins\generic\thoth\tests\classes\i18n;

use APP\plugins\generic\thoth\classes\i18n\ThothLocaleCode;
use PKP\tests\PKPTestCase;

class ThothLocaleCodeTest extends PKPTestCase
{
    public function testFromPkpLocalePreservesSupportedLocaleGranularity()
    {
        $this->assertSame('EN_US', ThothLocaleCode::fromPkpLocale('en_US'));
        $this->assertSame('PT_BR', ThothLocaleCode::fromPkpLocale('pt-BR'));
        $this->assertSame('ZH_HANT_TW', ThothLocaleCode::fromPkpLocale('zh_Hant_TW'));
    }

    public function testFromPkpLocaleReturnsNullForUnsupportedLocale()
    {
        $this->assertNull(ThothLocaleCode::fromPkpLocale('zz_ZZ'));
        $this->assertNull(ThothLocaleCode::fromPkpLocale('und'));
        $this->assertNull(ThothLocaleCode::fromPkpLocale(null));
    }
}
