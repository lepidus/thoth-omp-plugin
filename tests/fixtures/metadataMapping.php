<?php

return [
    'book' => [
        'source' => [
            'submissionId' => 17,
            'publicationId' => 23,
            'workType' => 'AUTHORED_WORK',
            'title' => 'Open publishing infrastructures',
            'subtitle' => 'A practical guide',
            'doi' => '10.1234/open-book',
            'publicationDate' => '2026-08-12',
            'landingPage' => 'https://press.example/catalog/book/17',
        ],
        'expected' => [
            'workType' => 'MONOGRAPH',
            'workStatus' => 'FORTHCOMING',
            'doi' => 'https://doi.org/10.1234/open-book',
            'publicationDate' => '2026-08-12',
            'landingPage' => 'https://press.example/catalog/book/17',
        ],
    ],
    'doi' => [
        'source' => [
            'bare' => '10.1234/open-book',
            'url' => 'https://doi.org/10.1234/open-book',
        ],
        'expected' => [
            'bare' => 'https://doi.org/10.1234/open-book',
            'url' => 'https://doi.org/10.1234/open-book',
        ],
    ],
    'authors' => [
        'source' => [[
            'authorId' => 31,
            'givenName' => 'Ada',
            'familyName' => 'Lovelace',
            'orcid' => 'https://orcid.org/0000-0002-1825-0097',
            'role' => 'AUTHOR',
            'sequence' => 0,
            'primaryContact' => true,
        ]],
        'expected' => [[
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'fullName' => 'Ada Lovelace',
            'orcid' => 'https://orcid.org/0000-0002-1825-0097',
            'contributionType' => 'AUTHOR',
            'contributionOrdinal' => 1,
            'mainContribution' => true,
        ]],
    ],
    'formats' => [
        'source' => [[
            'formatId' => 41,
            'entryKey' => 'DA',
            'name' => 'PDF',
            'isbn' => '978-3-16-148410-0',
        ]],
        'expected' => [[
            'publicationType' => 'PDF',
            'isbn' => '978-3-16-148410-0',
        ]],
    ],
    'chapters' => [
        'source' => [[
            'chapterId' => 51,
            'title' => 'Sustainable metadata',
            'subtitle' => 'From OMP to Thoth',
            'doi' => '10.1234/open-book.chapter-1',
            'pages' => '1-20',
            'publicationDate' => '2026-08-12',
        ]],
        'expected' => [[
            'workType' => 'BOOK_CHAPTER',
            'workStatus' => 'FORTHCOMING',
            'doi' => 'https://doi.org/10.1234/open-book.chapter-1',
            'pageInterval' => '1-20',
            'firstPage' => '1',
            'lastPage' => '20',
        ]],
    ],
    'subjects' => [
        'source' => [
            ['type' => 'BISAC', 'code' => 'EDU000000'],
            ['type' => 'THEMA', 'code' => 'MFGV'],
            ['type' => 'KEYWORD', 'code' => 'Open access'],
        ],
        'expected' => [
            ['subjectType' => 'BISAC', 'subjectCode' => 'EDU000000', 'subjectOrdinal' => 1],
            ['subjectType' => 'THEMA', 'subjectCode' => 'MFGV', 'subjectOrdinal' => 2],
            ['subjectType' => 'KEYWORD', 'subjectCode' => 'Open access', 'subjectOrdinal' => 3],
        ],
    ],
    'references' => [
        'source' => [
            [
                'citation' => 'Example reference. doi:10.1234/reference-one',
                'doi' => '10.1234/reference-one',
            ],
            [
                'citation' => 'Reference without a DOI.',
                'doi' => null,
            ],
        ],
        'expected' => [
            [
                'referenceOrdinal' => 1,
                'unstructuredCitation' => 'Example reference. doi:10.1234/reference-one',
                'doi' => 'https://doi.org/10.1234/reference-one',
            ],
            [
                'referenceOrdinal' => 2,
                'unstructuredCitation' => 'Reference without a DOI.',
            ],
        ],
    ],
];
