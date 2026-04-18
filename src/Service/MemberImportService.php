<?php

namespace App\Service;

use App\Entity\Member;
use App\Entity\Secteur;
use App\Repository\MemberRepository;
use App\Repository\SecteurRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Reader\XLSX\Reader;

final class MemberImportService
{
    /**
     * @var array<string, list<string>>
     */
    private const HEADER_ALIASES = [
        'rgpdConsent' => ['rgpd'],
        'shortTitle' => ['appellation courte', 'appellation'],
        'lastNameOrCompany' => ['nom compagnie', 'nom'],
        'birthName' => ['nom de naissance'],
        'firstNameOrService' => ['prenom service', 'prenom'],
        'address' => ['adresse'],
        'postalCode' => ['code p', 'cp'],
        'city' => ['ville'],
        'homePhone' => ['tel domicile'],
        'mobilePhone' => ['tel mobile'],
        'preferredEmail' => ['courriel prefere', 'courriel'],
        'birthOrFoundedAt' => ['date nais fondee le', 'date de naissance'],
        'baptismAt' => ['date de bapteme'],
        'modificationToApply' => ['modification a apporter'],
        'remarks' => ['remarques'],
        'lastContactName' => ['nom du dernier contact'],
        'contactChannel' => ['telephone ou visite'],
        'lastContactAt' => ['date du dernier contact'],
        'sectorName' => ['secteur pasteur', 'secteur'],
        'coordinates' => ['latitude longitude'],
    ];

    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly SecteurRepository $secteurRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SectorNameNormalizer $sectorNameNormalizer
    ) {
    }

    public function importFromXlsx(string $filePath): MemberImportReport
    {
        $report = new MemberImportReport();
        $sectorLookup = $this->buildSectorLookup();
        $reader = new Reader();
        $reader->open($filePath);

        try {
            [$headerMap, $rows] = $this->selectBestSheetData($reader);
            if ([] === $headerMap || [] === $rows) {
                return $report;
            }

            foreach ($rows as $values) {
                if ($this->isRowEmpty($values)) {
                    ++$report->skipped;
                    continue;
                }

                $memberData = $this->extractMemberData($values, $headerMap);

                $member = $this->resolveMember($memberData);
                $isNew = null === $member;
                if ($isNew) {
                    $member = new Member();
                    $this->entityManager->persist($member);
                    ++$report->created;
                } else {
                    ++$report->updated;
                }

                $this->hydrateMember($member, $memberData, $report, $sectorLookup);
            }

            $this->entityManager->flush();
        } finally {
            $reader->close();
        }

        return $report;
    }

    /**
     * @return array{0: array<string,int>, 1: array<int, list<mixed>>}
     */
    private function selectBestSheetData(Reader $reader): array
    {
        $bestHeaderMap = [];
        $bestRows = [];
        $bestScore = -1;

        foreach ($reader->getSheetIterator() as $sheet) {
            $headerMap = [];
            $rows = [];

            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $values = $row->toArray();

                if ($rowIndex === 1) {
                    $headerMap = $this->buildHeaderMap($values);
                    continue;
                }

                $rows[] = $values;
            }

            $score = $this->scoreHeaderMap($headerMap);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestHeaderMap = $headerMap;
                $bestRows = $rows;
            }
        }

        return [$bestHeaderMap, $bestRows];
    }

    /**
     * @param array<string, int> $headerMap
     */
    private function scoreHeaderMap(array $headerMap): int
    {
        $score = 0;

        foreach (self::HEADER_ALIASES as $aliases) {
            foreach ($aliases as $alias) {
                if (isset($headerMap[$alias])) {
                    ++$score;
                    break;
                }
            }
        }

        return $score;
    }

    /**
     * @param list<mixed> $headers
     * @return array<string, int>
     */
    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $key = $this->normalizeHeader((string) $header);
            if ($key !== '') {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    /**
     * @param list<mixed> $values
     * @param array<string, int> $headerMap
     * @return array<string, mixed>
     */
    private function extractMemberData(array $values, array $headerMap): array
    {
        [$latitude, $longitude] = $this->parseCoordinates(
            $this->cellAny($values, $headerMap, self::HEADER_ALIASES['coordinates'])
        );

        return [
            'rgpdConsent' => $this->parseBool($this->cellAny($values, $headerMap, self::HEADER_ALIASES['rgpdConsent'])),
            'shortTitle' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['shortTitle'])),
            'lastNameOrCompany' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['lastNameOrCompany'])),
            'birthName' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['birthName'])),
            'firstNameOrService' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['firstNameOrService'])),
            'address' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['address']), true),
            'postalCode' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['postalCode'])),
            'city' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['city'])),
            'homePhone' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['homePhone'])),
            'mobilePhone' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['mobilePhone'])),
            'preferredEmail' => $this->normalizeEmail($this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['preferredEmail']))),
            'birthOrFoundedAt' => $this->parseDate($this->cellAny($values, $headerMap, self::HEADER_ALIASES['birthOrFoundedAt'])),
            'baptismAt' => $this->parseDate($this->cellAny($values, $headerMap, self::HEADER_ALIASES['baptismAt'])),
            'modificationToApply' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['modificationToApply']), true),
            'remarks' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['remarks']), true),
            'lastContactName' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['lastContactName'])),
            'contactChannel' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['contactChannel'])),
            'lastContactAt' => $this->parseDate($this->cellAny($values, $headerMap, self::HEADER_ALIASES['lastContactAt'])),
            'sectorName' => $this->parseString($this->cellAny($values, $headerMap, self::HEADER_ALIASES['sectorName'])),
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * @param array<string, mixed> $memberData
     */
    private function resolveMember(array $memberData): ?Member
    {
        if (!empty($memberData['preferredEmail'])) {
            $found = $this->memberRepository->findOneByPreferredEmail((string) $memberData['preferredEmail']);
            if ($found) {
                return $found;
            }
        }

        return $this->memberRepository->findOneByIdentity(
            $memberData['lastNameOrCompany'] ?? null,
            $memberData['firstNameOrService'] ?? null,
            $memberData['city'] ?? null
        );
    }

    /**
     * @param array<string, mixed> $memberData
     * @param array<string, Secteur> $sectorLookup
     */
    private function hydrateMember(Member $member, array $memberData, MemberImportReport $report, array &$sectorLookup): void
    {
        $member
            ->setRgpdConsent($memberData['rgpdConsent'])
            ->setShortTitle($memberData['shortTitle'])
            ->setLastNameOrCompany($memberData['lastNameOrCompany'])
            ->setBirthName($memberData['birthName'])
            ->setFirstNameOrService($memberData['firstNameOrService'])
            ->setAddress($memberData['address'])
            ->setPostalCode($memberData['postalCode'])
            ->setCity($memberData['city'])
            ->setHomePhone($memberData['homePhone'])
            ->setMobilePhone($memberData['mobilePhone'])
            ->setPreferredEmail($memberData['preferredEmail'])
            ->setBirthOrFoundedAt($memberData['birthOrFoundedAt'])
            ->setBaptismAt($memberData['baptismAt'])
            ->setModificationToApply($memberData['modificationToApply'])
            ->setRemarks($memberData['remarks'])
            ->setLastContactName($memberData['lastContactName'])
            ->setContactChannel($memberData['contactChannel'])
            ->setLastContactAt($memberData['lastContactAt'])
            ->setLatitude($memberData['latitude'])
            ->setLongitude($memberData['longitude']);

        $sectorName = $memberData['sectorName'] ?? null;
        if ($sectorName) {
            $key = $this->sectorNameNormalizer->normalize($sectorName);
            $sector = $sectorLookup[$key] ?? null;
            if (!$sector && $key !== '') {
                $sector = (new Secteur())->setName($sectorName);
                $this->entityManager->persist($sector);
                $sectorLookup[$key] = $sector;
                ++$report->createdSectors;
            }
            $member->setSector($sector);
        } else {
            $member->setSector(null);
        }
    }

    /**
     * @return array<string, Secteur>
     */
    private function buildSectorLookup(): array
    {
        $lookup = [];
        foreach ($this->secteurRepository->findAll() as $sector) {
            $key = $this->sectorNameNormalizer->normalize($sector->getName());
            if ($key !== '' && !isset($lookup[$key])) {
                $lookup[$key] = $sector;
            }
        }

        return $lookup;
    }

    /**
     * @param list<mixed> $values
     * @param array<string, int> $headerMap
     * @param list<string> $headers
     */
    private function cellAny(array $values, array $headerMap, array $headers): mixed
    {
        foreach ($headers as $header) {
            $index = $headerMap[$header] ?? null;
            if (null === $index) {
                continue;
            }

            return $values[$index] ?? null;
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     */
    private function isRowEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if ($value instanceof \DateTimeInterface) {
                return false;
            }

            if (null !== $value && '' !== trim((string) $value)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = str_replace('_x000D_', ' ', $header);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header);
        $ascii = $ascii !== false ? $ascii : $header;
        $ascii = mb_strtolower($ascii);
        $ascii = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '';

        return trim($ascii);
    }

    private function parseString(mixed $value, bool $keepLineBreaks = false): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if ($keepLineBreaks) {
            $text = str_replace('_x000D_', PHP_EOL, $text);
            $lines = preg_split('/\R/u', $text) ?: [];
            $lines = array_map(static fn (string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? $line), $lines);
            $lines = array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));

            return $lines !== [] ? implode(PHP_EOL, $lines) : null;
        }

        $text = str_replace('_x000D_', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text) ?: null;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        $email = mb_strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function parseBool(mixed $value): ?bool
    {
        $text = mb_strtolower(trim((string) $value));
        if ($text === '') {
            return null;
        }

        if (in_array($text, ['1', 'true', 'oui', 'yes', 'y'], true)) {
            return true;
        }

        if (in_array($text, ['0', 'false', 'non', 'no', 'n'], true)) {
            return false;
        }

        return null;
    }

    private function parseDate(mixed $value): ?\DateTime
    {
        if ($value instanceof \DateTimeInterface) {
            $date = \DateTime::createFromInterface($value);
            $date->setTime(0, 0);

            return $date;
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $number = (float) $value;
            if ($number > 59) {
                $unix = (int) round(($number - 25569) * 86400);
                $date = new \DateTime('@'.$unix);
                $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                $date->setTime(0, 0);

                return $date;
            }
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $text);
            if ($date instanceof \DateTime) {
                $date->setTime(0, 0);

                return $date;
            }
        }

        return null;
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function parseCoordinates(mixed $value): array
    {
        $text = trim((string) $value);
        if ($text === '') {
            return [null, null];
        }

        preg_match_all('/-?\d+(?:[.,]\d+)?/', $text, $matches);
        $numbers = $matches[0] ?? [];
        if (count($numbers) < 2) {
            return [null, null];
        }

        $a = $this->parseCoordinateValue($numbers[0]);
        $b = $this->parseCoordinateValue($numbers[1]);
        if (null === $a || null === $b) {
            return [null, null];
        }

        $latitude = $a;
        $longitude = $b;

        if (!$this->isLatitude($latitude) && $this->isLatitude($longitude) && $this->isLongitude($latitude)) {
            [$latitude, $longitude] = [$longitude, $latitude];
        }

        if (!$this->isLatitude($latitude) || !$this->isLongitude($longitude)) {
            return [null, null];
        }

        return [round($latitude, 7), round($longitude, 7)];
    }

    private function parseCoordinateValue(string $value): ?float
    {
        $normalized = str_replace(',', '.', trim($value));
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function isLatitude(float $value): bool
    {
        return $value >= -90 && $value <= 90;
    }

    private function isLongitude(float $value): bool
    {
        return $value >= -180 && $value <= 180;
    }
}
