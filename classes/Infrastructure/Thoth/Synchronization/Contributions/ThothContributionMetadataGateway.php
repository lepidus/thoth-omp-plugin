<?php

namespace APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\Contributions;

use APP\plugins\generic\thoth\classes\Application\Synchronization\Port\ContributionMetadataGateway;
use APP\plugins\generic\thoth\classes\Domain\Work\WorkId;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Client\ThothRemoteGateway;
use APP\plugins\generic\thoth\classes\Infrastructure\Thoth\Synchronization\MarkupFormatDetector;
use RuntimeException;
use ThothApi\GraphQL\Enums\LocaleCode;
use ThothApi\GraphQL\Inputs\NewAffiliation;
use ThothApi\GraphQL\Inputs\NewBiography;
use ThothApi\GraphQL\Inputs\NewContribution;
use ThothApi\GraphQL\Inputs\NewContributor;
use ThothApi\GraphQL\Inputs\PatchAffiliation;
use ThothApi\GraphQL\Inputs\PatchBiography;
use ThothApi\GraphQL\Inputs\PatchContribution;
use ThothApi\GraphQL\Inputs\PatchContributor;

final class ThothContributionMetadataGateway implements ContributionMetadataGateway
{
    private const CONTRIBUTION_FIELDS = [
        'contributionType' => true,
        'mainContribution' => true,
        'contributionOrdinal' => true,
        'firstName' => true,
        'lastName' => true,
        'fullName' => true,
    ];
    private const CONTRIBUTIONS_SELECTION = [
        'contributions' => [
            'contributionId', 'contributorId', 'contributionType', 'mainContribution',
            'contributionOrdinal', 'firstName', 'lastName', 'fullName',
            'contributor' => ['contributorId', 'firstName', 'lastName', 'fullName', 'orcid', 'website'],
            'biographies' => ['biographyId', 'contributionId', 'localeCode', 'content', 'canonical'],
            'affiliations' => ['affiliationId', 'contributionId', 'institutionId', 'affiliationOrdinal'],
        ],
    ];

    private MarkupFormatDetector $markupFormat;

    public function __construct(
        private ThothRemoteGateway $remote,
        ?MarkupFormatDetector $markupFormat = null
    ) {
        $this->markupFormat = $markupFormat ?? new MarkupFormatDetector();
    }

    public function snapshot(WorkId $workId): array
    {
        $work = $this->remote->call(
            'synchronizeContributions',
            'work',
            [$workId->toString(), self::CONTRIBUTIONS_SELECTION]
        );

        return array_map(fn (object $item): array => $item->toArray(), $work->getContributions() ?? []);
    }

    public function create(WorkId $workId, array $metadata): void
    {
        $author = $this->author($metadata);
        $contributorId = $this->upsertContributor($author);
        $contribution = $this->remote->call('synchronizeContributions', 'createContribution', [
            new NewContribution($this->contributionData($metadata, $workId, $contributorId)),
            ['contributionId'],
        ]);
        $contributionId = $this->requiredId($contribution->getContributionId(), 'contribution');
        $this->synchronizeBiographies($author, $contributionId, []);
        $this->synchronizeAffiliations($author, $contributionId, []);
    }

    public function update(
        WorkId $workId,
        string $contributionId,
        array $metadata,
        array $remoteContribution,
        bool $metadataChanged
    ): void {
        $author = $this->author($metadata);
        $contributorId = $this->existingContributorId($remoteContribution);
        if ($contributorId === null) {
            $contributorId = $this->upsertContributor($author);
        } else {
            $this->updateContributor($author, $contributorId);
        }
        if ($metadataChanged) {
            $data = $this->contributionData($metadata, $workId, $contributorId);
            $data['contributionId'] = $contributionId;
            $this->remote->call('synchronizeContributions', 'updateContribution', [
                new PatchContribution($data),
                ['contributionId'],
            ]);
        }
        $this->synchronizeBiographies($author, $contributionId, $remoteContribution['biographies'] ?? []);
        $this->synchronizeAffiliations($author, $contributionId, $remoteContribution['affiliations'] ?? []);
    }

    public function delete(string $contributionId): void
    {
        $this->remote->call('synchronizeContributions', 'deleteContribution', [$contributionId]);
    }

    private function author(array $metadata): object
    {
        if (!isset($metadata['author']) || !is_object($metadata['author'])) {
            throw new RuntimeException('Contribution metadata does not contain a PKP author');
        }
        return $metadata['author'];
    }

    private function contributionData(array $metadata, WorkId $workId, string $contributorId): array
    {
        $data = array_intersect_key($metadata, self::CONTRIBUTION_FIELDS);
        $data['workId'] = $workId->toString();
        $data['contributorId'] = $contributorId;
        return $data;
    }

    private function upsertContributor(object $author): string
    {
        $data = $this->contributorData($author);
        $filter = $data['orcid'] ?? $data['fullName'];
        $contributors = $this->remote->call(
            'synchronizeContributions',
            'contributors',
            [1, null, $filter, null, ['contributorId']]
        );
        $contributor = is_array($contributors) ? ($contributors[0] ?? null) : null;
        if ($contributor === null) {
            $created = $this->remote->call('synchronizeContributions', 'createContributor', [
                new NewContributor($data),
                ['contributorId'],
            ]);
            return $this->requiredId($created->getContributorId(), 'contributor');
        }
        $contributorId = $this->requiredId($contributor->getContributorId(), 'contributor');
        $this->updateContributor($author, $contributorId);
        return $contributorId;
    }

    private function updateContributor(object $author, string $contributorId): void
    {
        $data = $this->contributorData($author);
        $data['contributorId'] = $contributorId;
        $this->remote->call('synchronizeContributions', 'updateContributor', [
            new PatchContributor($data),
            ['contributorId'],
        ]);
    }

    private function contributorData(object $author): array
    {
        $data = [
            'lastName' => $author->getLocalizedFamilyName(),
            'fullName' => $author->getFullName(false),
        ];
        foreach (['firstName' => $author->getLocalizedGivenName(), 'orcid' => $author->getOrcid(),
            'website' => $author->getUrl()] as $field => $value) {
            if ($value !== null && $value !== '') {
                $data[$field] = $value;
            }
        }
        return $data;
    }

    private function synchronizeBiographies(object $author, string $contributionId, array $remote): void
    {
        $remaining = [];
        foreach ($remote as $biography) {
            if (isset($biography['biographyId'])) {
                $remaining[$biography['localeCode'] ?? ''] = $biography;
            }
        }
        foreach ($this->biographies($author, $contributionId) as $locale => $data) {
            $existing = $remaining[$locale] ?? null;
            if ($existing === null) {
                $this->remote->call('synchronizeContributions', 'createBiography', [
                    $this->markupFormat->fromContent($data['content']), new NewBiography($data), ['biographyId'],
                ]);
                continue;
            }
            $data['biographyId'] = $existing['biographyId'];
            $this->remote->call('synchronizeContributions', 'updateBiography', [
                $this->markupFormat->fromContent($data['content']), new PatchBiography($data), ['biographyId'],
            ]);
            unset($remaining[$locale]);
        }
        foreach ($remaining as $biography) {
            $this->remote->call('synchronizeContributions', 'deleteBiography', [$biography['biographyId']]);
        }
    }

    private function biographies(object $author, string $contributionId): array
    {
        $values = $author->getData('biography');
        $locale = $author->getData('locale');
        if (!is_array($values)) {
            $values = $values && $locale ? [$locale => $values] : [];
        }
        $result = [];
        $canonical = isset($values[$locale]) ? $locale : array_key_first($values);
        foreach (array_filter($values) as $sourceLocale => $content) {
            $localeCode = $this->localeCode((string) $sourceLocale);
            if ($localeCode === null) {
                continue;
            }
            $result[$localeCode] = [
                'contributionId' => $contributionId,
                'localeCode' => $localeCode,
                'content' => trim((string) $content),
                'canonical' => $sourceLocale === $canonical,
            ];
        }
        return $result;
    }

    private function synchronizeAffiliations(object $author, string $contributionId, array $remote): void
    {
        $remaining = array_values($remote);
        foreach (array_values($author->getAffiliations()) as $index => $affiliation) {
            $ror = $affiliation->getRor();
            if (!$ror) {
                continue;
            }
            $institutions = $this->remote->call(
                'synchronizeContributions',
                'institutions',
                [1, null, $ror, null, ['institutionId']]
            );
            $institution = is_array($institutions) ? ($institutions[0] ?? null) : null;
            if ($institution === null) {
                continue;
            }
            $institutionId = $this->requiredId($institution->getInstitutionId(), 'institution');
            $match = $this->affiliationKey($remaining, $institutionId);
            $data = ['contributionId' => $contributionId, 'institutionId' => $institutionId,
                'affiliationOrdinal' => $index + 1];
            if ($match === null) {
                $this->remote->call('synchronizeContributions', 'createAffiliation', [
                    new NewAffiliation($data), ['affiliationId'],
                ]);
                continue;
            }
            $data['affiliationId'] = $remaining[$match]['affiliationId'];
            $this->remote->call('synchronizeContributions', 'updateAffiliation', [
                new PatchAffiliation($data), ['affiliationId'],
            ]);
            unset($remaining[$match]);
        }
        foreach ($remaining as $affiliation) {
            $this->remote->call('synchronizeContributions', 'deleteAffiliation', [$affiliation['affiliationId']]);
        }
    }

    private function affiliationKey(array $affiliations, string $institutionId): ?int
    {
        foreach ($affiliations as $key => $affiliation) {
            if (($affiliation['institutionId'] ?? null) === $institutionId) {
                return $key;
            }
        }
        return null;
    }

    private function existingContributorId(array $contribution): ?string
    {
        $id = $contribution['contributorId'] ?? ($contribution['contributor']['contributorId'] ?? null);
        return is_string($id) && $id !== '' ? $id : null;
    }

    private function localeCode(string $locale): ?string
    {
        $code = strtoupper(str_replace(['-', '@'], '_', $locale));
        return defined(LocaleCode::class . '::' . $code) ? $code : null;
    }

    private function requiredId($id, string $entity): string
    {
        if (!is_string($id) || $id === '') {
            throw new RuntimeException("Thoth did not return a {$entity} ID");
        }
        return $id;
    }
}
