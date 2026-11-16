<?php

namespace App\Services;

use App\Models\Patient;
use App\Rules\PhoneNumber;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

class PatientDuplicateService
{
    private const int PAGE_SIZE = 8;

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Patient>
     */
    public function candidates(int $clinicId, array $data): Collection
    {
        return $this->matchingQuery($clinicId, $data)->orderBy('id')->limit(self::PAGE_SIZE)
            ->get(['id', 'name', 'medical_record_number', 'birth_date', 'phone', 'is_active']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{candidates: Collection<int, Patient>, token: string, next_cursor: ?string}
     */
    public function candidatePage(int $clinicId, array $data, ?string $cursor = null): array
    {
        $scope = $this->reviewToken($clinicId, $data, new Collection);
        $position = null;
        $invalidCursor = 'Daftar kandidat sudah berubah, kedaluwarsa, atau tautan tidak valid. Periksa kembali kandidat.';

        if ($cursor !== null) {
            try {
                $position = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
            } catch (DecryptException|JsonException) {
                throw ValidationException::withMessages(['cursor' => $invalidCursor]);
            }

            if (! is_array($position) || ! is_int($position['after_id'] ?? null) || $position['after_id'] < 1
                || ! is_int($position['expires_at'] ?? null) || $position['expires_at'] <= now()->getTimestamp()
                || ! is_string($position['scope'] ?? null) || ! hash_equals($scope, $position['scope'])
                || ! is_string($position['version'] ?? null)) {
                throw ValidationException::withMessages(['cursor' => $invalidCursor]);
            }
        }

        $afterId = $position['after_id'] ?? 0;
        $foundAnchor = $afterId === 0;
        $firstPage = new Collection;
        $page = new Collection;
        $snapshot = hash_init('sha256');

        foreach ($this->matchingQuery($clinicId, $data)->orderBy('id')->cursor() as $patient) {
            hash_update($snapshot, json_encode($patient->getRawOriginal(), JSON_THROW_ON_ERROR)."\n");
            if ($firstPage->count() < self::PAGE_SIZE) {
                $firstPage->push($patient);
            }
            if ($patient->id === $afterId) {
                $foundAnchor = true;
            }
            if ($patient->id > $afterId && $page->count() <= self::PAGE_SIZE) {
                $page->push($patient);
            }
        }

        $version = hash_final($snapshot);
        if ($position !== null && (! $foundAnchor || ! hash_equals($version, $position['version']))) {
            throw ValidationException::withMessages(['cursor' => $invalidCursor]);
        }

        $hasMore = $page->count() > self::PAGE_SIZE;
        $page = $page->take(self::PAGE_SIZE)->values();

        return [
            'candidates' => $page,
            'token' => $this->reviewToken($clinicId, $data, $firstPage),
            'next_cursor' => $hasMore ? Crypt::encryptString(json_encode([
                'scope' => $scope,
                'version' => $version,
                'after_id' => $page->last()->id,
                'expires_at' => $position['expires_at'] ?? now()->addMinutes(15)->getTimestamp(),
            ], JSON_THROW_ON_ERROR)) : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function matchingQuery(int $clinicId, array $data): Builder
    {
        $name = mb_strtolower(trim($data['name'] ?? ''));
        $phone = ltrim(PhoneNumber::normalize($data['phone'] ?? ''), '+');
        $localPhone = str_starts_with($phone, '62') ? '0'.substr($phone, 2) : $phone;

        return Patient::query()->where('clinic_id', $clinicId)
            ->where(function (Builder $query) use ($data, $name, $localPhone): void {
                $query->whereRaw('1 = 0');
                if (filled($data['nik'] ?? null)) {
                    $query->orWhere('nik', trim($data['nik']));
                }
                if (mb_strlen($name) >= 2 && filled($data['birth_date'] ?? null)) {
                    $query->orWhere(fn (Builder $candidate) => $candidate
                        ->whereRaw('LOWER(TRIM(name)) = ?', [$name])->whereDate('birth_date', $data['birth_date']));
                }
                if (strlen($localPhone) >= 8) {
                    $internationalPhone = str_starts_with($localPhone, '0') ? '62'.substr($localPhone, 1) : $localPhone;
                    $expression = 'phone';
                    $separators = [...PhoneNumber::Separators, '+'];
                    foreach ($separators as $separator) {
                        $expression = "REPLACE({$expression}, ?, '')";
                    }
                    $query->orWhereRaw($expression.' IN (?, ?)', [...$separators, $localPhone, $internationalPhone]);
                }
            });
    }

    /** @param array<string, mixed> $data */
    public function reviewToken(int $clinicId, array $data, Collection $candidates): string
    {
        return hash_hmac('sha256', json_encode([
            $clinicId, $data['name'] ?? '', $data['birth_date'] ?? '', $data['nik'] ?? '', $data['phone'] ?? '', $candidates->modelKeys(),
        ], JSON_THROW_ON_ERROR), config('app.key'));
    }

    /** @param array<string, mixed> $data */
    public function assertReviewed(int $clinicId, array $data): void
    {
        $candidates = $this->candidates($clinicId, $data);
        if ($candidates->isNotEmpty() && (! filter_var($data['duplicate_reviewed'] ?? false, FILTER_VALIDATE_BOOL)
            || ! hash_equals($this->reviewToken($clinicId, $data, $candidates), $data['duplicate_review_token'] ?? ''))) {
            throw ValidationException::withMessages(['duplicate_reviewed' => 'Ada kandidat pasien terdaftar. Tinjau kecocokan identitas sebelum membuat pasien baru.']);
        }
    }
}
