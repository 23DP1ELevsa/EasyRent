<?php

namespace Tests\Fixtures;

use Illuminate\Http\UploadedFile;

class VehicleImages
{
    private const CONTENT = [
        'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAIAAAB7QOjdAAAAD0lEQVR4nGNkaGhgYGAAAASOAQIcpy90AAAAAElFTkSuQmCC',
        'jpg' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAIBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAAAP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AP//Z',
        'webp' => 'UklGRjgAAABXRUJQVlA4ICwAAACQAQCdASoCAAEAAUAmJaACdLoAA5gA/vBTH+j64RZGX/7Gb/56P/z0f6hgAA==',
    ];

    public static function file(string $format = 'png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent("vehicle.$format", base64_decode(self::CONTENT[$format]));
    }
}
