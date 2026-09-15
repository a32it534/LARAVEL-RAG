<?php

namespace App\Services;

use Exception;

class DocumentParserService
{
    /**
     * استخراج متن کامل از فایل‌های txt
     */
    public function extractText(string $filePath, string $extension): array
    {
        $extension = strtolower($extension);

        if ($extension !== 'txt') {
            throw new Exception("فرمت فایل .{$extension} پشتیبانی نمی‌شود. تنها فرمت txt مجاز است.");
        }

        $content = file_get_contents($filePath);

        return [
            'full_text' => $content,
            'pages'     => [1 => $content],
        ];
    }
}
