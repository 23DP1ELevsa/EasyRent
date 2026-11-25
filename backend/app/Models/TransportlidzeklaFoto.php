<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

class TransportlidzeklaFoto extends Model
{
    protected $table = 'transportlidzekla_foto';
    protected $primaryKey = 'foto_id';
    protected $fillable = ['mime_type', 'attels', 'content_hash'];
    protected $hidden = ['attels', 'content_hash'];

    public const LIMIT = 4;
    public const MAX_KB = 1536;
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function scopeGalleryOrder(Builder $query): void
    {
        $query->orderBy('position')->orderBy('foto_id');
    }

    public function publicUrl(): string
    {
        return route('transport.public-photos.show', array_filter([
            $this->transportlidzeklis_id,
            $this->getKey(),
            'v' => $this->content_hash ? substr($this->content_hash, 0, 16) : null,
        ]), false);
    }

    public static function fileRules(): array
    {
        return [
            'bail', 'required', 'image', 'mimetypes:'.implode(',', self::IMAGE_TYPES), 'max:'.self::MAX_KB,
            function (string $attribute, UploadedFile $file, \Closure $fail) {
                $image = @getimagesize($file->getPathname());
                if (($image['mime'] ?? null) !== $file->getMimeType()) {
                    $fail(__('vehicle.photo_invalid'));
                }
            },
        ];
    }

    public static function uploadRules(): array
    {
        return [
            'photos' => ['required', 'array', 'list', 'min:1', 'max:'.self::LIMIT],
            'photos.*' => self::fileRules(),
        ];
    }

    public static function fileMessages(string $key): array
    {
        return [
            "$key.required" => __('vehicle.photo_required'),
            "$key.image" => __('vehicle.photo_invalid'),
            "$key.mimetypes" => __('vehicle.photo_invalid'),
            "$key.uploaded" => __('vehicle.photo_invalid'),
            "$key.max" => __('vehicle.photo_too_large'),
        ];
    }

    public static function uploadMessages(): array
    {
        return [
            'photos.required' => __('vehicle.photo_required'),
            'photos.min' => __('vehicle.photo_required'),
            'photos.max' => __('admin.photo_limit'),
        ] + self::fileMessages('photos.*');
    }

    public static function attributesFromUpload(UploadedFile $file): array
    {
        $contents = $file->getContent();

        return ['mime_type' => $file->getMimeType(), 'attels' => base64_encode($contents), 'content_hash' => hash('sha256', $contents)];
    }
}
