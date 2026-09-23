<?php

namespace App\Services;

use App\Models\BuildingInfo\Owner;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class OwnerPiiExportService
{
    private const HEADERS = [
        'bin',
        'owner_name',
        'owner_contact',
        'owner_gender',
        'nid',
        'status',
    ];

    private PiiEncryptionService $encryption;

    public function __construct(PiiEncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    public function headers(): array
    {
        return self::HEADERS;
    }

    public function parseBins(UploadedFile $file): array
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            throw ValidationException::withMessages([
                'bin_file' => __('The BIN list must be a CSV file.'),
            ]);
        }

        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'bin_file' => __('The uploaded BIN list could not be read.'),
            ]);
        }

        try {
            return $this->parseCsvHandle($handle);
        } finally {
            fclose($handle);
        }
    }

    public function parseCsvText(string $csv): array
    {
        $maximumBytes = max(
            1,
            (int) config('pii.export.max_file_kb', 2048)
        ) * 1024;

        if ($csv === '' || strlen($csv) > $maximumBytes) {
            throw ValidationException::withMessages([
                'bin_file' => __('The BIN list CSV is empty or too large.'),
            ]);
        }

        $handle = fopen('php://temp', 'w+b');

        if ($handle === false || fwrite($handle, $csv) === false) {
            throw ValidationException::withMessages([
                'bin_file' => __('The BIN list CSV could not be read.'),
            ]);
        }

        rewind($handle);

        try {
            return $this->parseCsvHandle($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     */
    private function parseCsvHandle($handle): array
    {
        $header = fgetcsv($handle);

        if (!is_array($header)) {
            throw ValidationException::withMessages([
                'bin_file' => __('The BIN list is empty.'),
            ]);
        }

        $header = array_map(function ($value) {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);

            return strtolower(trim($value));
        }, $header);
        $binIndex = array_search('bin', $header, true);

        if ($binIndex === false) {
            throw ValidationException::withMessages([
                'bin_file' => __('The CSV must contain a bin header.'),
            ]);
        }

        $bins = [];
        $rowNumber = 1;
        $maximumBins = max(
            1,
            (int) config('pii.export.max_bins', 5000)
        );

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $bin = strtoupper(trim((string) ($row[$binIndex] ?? '')));

            if ($bin === '' && $this->rowIsEmpty($row)) {
                continue;
            }

            if ($bin === ''
                || strlen($bin) > 100
                || preg_match('/^[A-Z0-9_-]+$/', $bin) !== 1) {
                throw ValidationException::withMessages([
                    'bin_file' => __(sprintf(
                        'The BIN value on CSV row %d is invalid.',
                        $rowNumber
                    )),
                ]);
            }

            $bins[$bin] = true;

            if (count($bins) > $maximumBins) {
                throw ValidationException::withMessages([
                    'bin_file' => __(sprintf(
                        'The CSV may contain no more than %d unique BIN values.',
                        $maximumBins
                    )),
                ]);
            }
        }

        if ($bins === []) {
            throw ValidationException::withMessages([
                'bin_file' => __('The CSV does not contain any BIN values.'),
            ]);
        }

        return array_keys($bins);
    }

    public function rowsForBins(array $bins): array
    {
        $owners = Owner::query()
            ->select([
                'bin',
                'owner_name',
                'owner_contact',
                'owner_gender',
                'nid',
            ])
            ->whereNull('deleted_at')
            ->whereIn('bin', $bins)
            ->get()
            ->keyBy(function (Owner $owner) {
                return strtoupper((string) $owner->bin);
            });

        $rows = [];
        $exportedCount = 0;

        foreach ($bins as $bin) {
            /** @var Owner|null $owner */
            $owner = $owners->get($bin);

            if (!$owner) {
                $rows[] = [$bin, '', '', '', '', 'not_found'];
                continue;
            }

            $attributes = $owner->getAttributes();
            $rows[] = [
                $this->escapeSpreadsheetValue($bin),
                $this->escapeSpreadsheetValue(
                    $this->encryption->decrypt($attributes['owner_name'] ?? null)
                ),
                $this->escapeSpreadsheetValue(
                    $this->encryption->decrypt($attributes['owner_contact'] ?? null)
                ),
                $this->escapeSpreadsheetValue(
                    $this->encryption->decrypt($attributes['owner_gender'] ?? null)
                ),
                $this->escapeSpreadsheetValue(
                    $this->encryption->decrypt($attributes['nid'] ?? null)
                ),
                'found',
            ];
            $exportedCount++;
        }

        return [
            'rows' => $rows,
            'requested_count' => count($bins),
            'exported_count' => $exportedCount,
            'missing_count' => count($bins) - $exportedCount,
        ];
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function escapeSpreadsheetValue(?string $value): string
    {
        $value = $value ?? '';

        if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'" . $value;
        }

        return $value;
    }
}
