<?php

namespace APP\plugins\generic\thoth\tests\classes\Domain\Registration;

use APP\plugins\generic\thoth\classes\Domain\Registration\BookRegistrationPolicy;
use PHPUnit\Framework\TestCase;

class BookRegistrationPolicyTest extends TestCase
{
    public function testEligibleRegistrationDoesNotDependOnPublicationState(): void
    {
        $eligibility = (new BookRegistrationPolicy())->evaluate(
            true,
            'f740cf4e-16d1-487c-9a92-615882a591e9',
            null,
            []
        );

        $this->assertTrue($eligibility->isEligible());
    }

    public function testRegistrationMustBeConfirmed(): void
    {
        $eligibility = (new BookRegistrationPolicy())->evaluate(
            'false',
            'f740cf4e-16d1-487c-9a92-615882a591e9',
            null,
            []
        );

        $this->assertFalse($eligibility->isRequested());
        $this->assertFalse($eligibility->isEligible());
    }

    public function testImprintLinkAndMetadataDetermineEligibility(): void
    {
        $eligibility = (new BookRegistrationPolicy())->evaluate(true, null, 'work-id', ['invalid metadata']);

        $this->assertTrue($eligibility->isImprintMissing());
        $this->assertTrue($eligibility->isAlreadyRegistered());
        $this->assertSame(['invalid metadata'], $eligibility->getMetadataErrors());
        $this->assertFalse($eligibility->isEligible());
    }

    public function testNewWorksAreForthcomingAndExistingActiveWorksRemainActive(): void
    {
        $policy = new BookRegistrationPolicy();

        $this->assertSame('FORTHCOMING', $policy->initialWorkStatus());
        $this->assertSame('FORTHCOMING', $policy->statusForExistingWork('FORTHCOMING'));
        $this->assertSame('ACTIVE', $policy->statusForExistingWork('ACTIVE'));
    }
}
