<?php

namespace App\Services\Hr;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HrFileStorage
{
    public const DISK = 'hr_private';

    public function storeEmployeeDocument(UploadedFile $file, int $employeeId): array
    {
        $dir = "employees/{$employeeId}/documents";
        $safeName = Str::uuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs($dir, $safeName, self::DISK);

        return [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_disk' => self::DISK,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    public function storeCandidateDocument(UploadedFile $file, int $candidateId): array
    {
        $dir = "candidates/{$candidateId}/documents";
        $safeName = Str::uuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs($dir, $safeName, self::DISK);

        return [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_disk' => self::DISK,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    public function storeContractFile(UploadedFile $file, int $employeeId, int $contractId): array
    {
        $dir = "employees/{$employeeId}/contracts/{$contractId}";
        $safeName = Str::uuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs($dir, $safeName, self::DISK);

        return [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_disk' => self::DISK,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    public function storeEmployeePhoto(UploadedFile $file, int $employeeId): string
    {
        $dir = "employees/{$employeeId}/photo";
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $path = $file->storeAs($dir, "avatar.{$ext}", self::DISK);

        return $path;
    }

    public function delete(?string $path, ?string $disk = self::DISK): void
    {
        if ($path && Storage::disk($disk ?? self::DISK)->exists($path)) {
            Storage::disk($disk ?? self::DISK)->delete($path);
        }
    }

    public function downloadResponse(string $path, string $fileName, ?string $disk = self::DISK)
    {
        return Storage::disk($disk ?? self::DISK)->download($path, $fileName);
    }
}
