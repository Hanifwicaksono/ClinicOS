<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class PatientDuplicateService
{
    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Patient>
     */
    public function candidates(int $clinicId, array $data): Collection
    {
        $name = mb_strtolower(trim($data['name'] ?? ''));
        $phone = preg_replace('/\D/', '', $data['phone'] ?? '');
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
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') IN (?, ?)", [$localPhone, $internationalPhone]);
                }
            })->orderBy('id')->limit(8)->get(['id', 'name', 'medical_record_number', 'birth_date', 'phone', 'is_active']);
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
