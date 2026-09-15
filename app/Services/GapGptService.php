<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;
use Illuminate\Support\Facades\Log;

class GapGptService
{
    protected Client $client;
    protected string $apiKey;
    protected string $baseUrl;
    protected string $chatModel;
    protected string $embeddingModel;

    public function __construct()
    {
        $this->apiKey = config('gapgpt.api_key');
        $this->baseUrl = rtrim(config('gapgpt.base_url', 'https://api.gapgpt.app/v1'), '/');
        $this->chatModel = config('gapgpt.chat_model', 'gpt-4o');
        $this->embeddingModel = config('gapgpt.embedding_model', 'text-embedding-3-large');

        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * دریافت بردار Embedding برای یک یا چند متن با مدل text-embedding-3-large
     */
    public function getEmbedding(string|array $input): array
    {
        try {
            $response = $this->client->post('embeddings', [
                'json' => [
                    'model' => $this->embeddingModel,
                    'input' => $input,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['data'][0]['embedding'])) {
                return is_array($input) 
                    ? array_column($body['data'], 'embedding')
                    : $body['data'][0]['embedding'];
            }

            throw new Exception('پاسخ دریافت Embedding نامعتبر است.');
        } catch (GuzzleException $e) {
            Log::error('GapGPT Embedding API Error: ' . $e->getMessage());
            throw new Exception('خطا در ارتباط با GapGPT برای ساخت Embedding: ' . $e->getMessage());
        }
    }

    /**
     * ارسال پیام و مستندات RAG به مدل Chat Completion (gpt-4o)
     */
    public function generateAnswer(string $question, array $relevantChunks, array $history = []): string
    {
        // آماده‌سازی زمینه (Context) برای پرامپت RAG
        $context = "";
        foreach ($relevantChunks as $index => $chunk) {
            $srcNum = $index + 1;
            $docName = $chunk['document_name'] ?? 'سند';
            $pageInfo = !empty($chunk['page_number']) ? " (بخش/صفحه {$chunk['page_number']})" : '';
            $context .= "[منبع {$srcNum}: {$docName}{$pageInfo}]\n" . $chunk['text'] . "\n\n";
        }

       $systemPrompt = "شما یک دستیار هوشمند و تحلیل‌گر ارشد اسناد هستید که از متد RAG (Retrieval-Augmented Generation) استفاده می‌کنید.\n"
    . "قوانین و شیوه پاسخ‌دهی:\n"
    . "1. پاسخ را به‌صورت کامل، جامع، تشریحی و با جزئیات فراوان بر اساس مستندات ارائه شده بنویسید (از پاسخ‌های کوتاه و تلگرافی خودداری کنید).\n"
    . "2. نکات مهم را دسته‌بندی کرده و با تیترها، بولت‌پوینت‌ها و توضیحات دقیق برای هر بخش بنویسید.\n"
    . "3. به منابع استناد کنید (مانند: طبق منبع ۱ یا [منبع ۱]).\n"
    . "4. اگر پاسخ در مستندات موجود نیست، صراحتاً اعلام کنید که در اسناد اشاره‌ای به این موضوع نشده است.\n\n"
    . "=== مستندات استخراج شده مرتبط ===\n"
    . $context;


        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // اضافه کردن تاریخچه مکالمه
        foreach (array_slice($history, -4) as $msg) {
            $messages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $question,
        ];

        try {
           $response = $this->client->post('chat/completions', [
        'json' => [
        'model' => $this->chatModel,
        'messages' => $messages,
        'temperature' => 0.4,       
        'max_tokens' => 4000,        
    ],
    ]);

            $body = json_decode($response->getBody()->getContents(), true);

            if (isset($body['choices'][0]['message']['content'])) {
                return $body['choices'][0]['message']['content'];
            }

            throw new Exception('فرمت پاسخ Chat دریافت شده از GapGPT معتبر نیست.');
        } catch (GuzzleException $e) {
            Log::error('GapGPT Chat API Error: ' . $e->getMessage());
            throw new Exception('خطا در ارتباط با مدل gpt-4o در GapGPT: ' . $e->getMessage());
        }
    }
}
