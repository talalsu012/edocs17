<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['letter_id', 'file_name', 'file_path', 'mime_type', 'size', 'uploaded_by'])]
class Attachment extends Model
{
    public const PREVIEW = [
        'pdf' => 'application/pdf',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
    ];

    public function extension(): string
    {
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION) ?: pathinfo($this->file_path, PATHINFO_EXTENSION));
    }

    /** يُعرض داخل النظام: PDF والصور فقط (الامتداد لا نوع MIME المرسل من المتصفح). */
    public function isPreviewable(): bool
    {
        return isset(self::PREVIEW[$this->extension()]);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->previewMime() ?? '', 'image/');
    }

    public function previewMime(): ?string
    {
        return self::PREVIEW[$this->extension()] ?? null;
    }

    public function icon(): string
    {
        return match (true) {
            $this->extension() === 'pdf' => '📄',
            $this->isImage() => '🖼',
            in_array($this->extension(), ['doc', 'docx']) => '📝',
            in_array($this->extension(), ['xls', 'xlsx']) => '📊',
            default => '📎',
        };
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
