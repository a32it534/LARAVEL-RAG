<?php

namespace App\Services;

use App\Models\DocumentChunk;

class VectorSearchService
{
    /**
     * تقسیم متن طولانی سند به چانک‌های معنایی با همپوشانی (Overlap)
     */
    public function chunkText(string $text, int $chunkSize = 600, int $overlap = 80): array
    {
        $cleanText = str_replace(["\r\n", "\r"], "\n", $text);
        $paragraphs = preg_split('/\n{2,}/', $cleanText);
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (empty($para)) continue;

            if (mb_strlen($current) + mb_strlen($para) > $chunkSize && !empty($current)) {
                $chunks[] = trim($current);
                // محاسبه همپوشانی
                $words = explode(' ', $current);
                $overlapWords = array_slice($words, -max(1, (int)($overlap / 6)));
                $current = implode(' ', $overlapWords) . "\n" . $para;
            } else {
                $current .= ($current ? "\n\n" : "") . $para;
            }
        }

        if (!empty(trim($current))) {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * محاسبه شباهت کسینوسی (Cosine Similarity) بین دو بردار
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        $count = min(count($vecA), count($vecB));
        if ($count === 0) return 0.0;

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = (float) $vecA[$i];
            $b = (float) $vecB[$i];
            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA == 0 || $normB == 0) return 0.0;

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * جستجوی مشابه‌ترین چانک‌ها در پایگاه داده
     */
    public function searchTopK(array $queryEmbedding, int $topK = 4, float $threshold = 0.15): array
    {
        $chunks = DocumentChunk::with('document')->get();
        $scored = [];

        foreach ($chunks as $chunk) {
            $chunkVector = $chunk->embedding;
            if (empty($chunkVector) || !is_array($chunkVector)) continue;

            $similarity = $this->cosineSimilarity($queryEmbedding, $chunkVector);

            if ($similarity >= $threshold) {
                $scored[] = [
                    'id' => $chunk->id,
                    'document_id' => $chunk->document_id,
                    'document_name' => $chunk->document->name ?? 'سند',
                    'page_number' => $chunk->page_number,
                    'text' => $chunk->content,
                    'similarity' => round($similarity, 4),
                ];
            }
        }

        // مرتب‌سازی نزولی بر اساس شباهت
        usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($scored, 0, $topK);
    }
}
