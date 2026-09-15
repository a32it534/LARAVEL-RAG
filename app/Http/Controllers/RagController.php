<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DocumentParserService;
use App\Services\VectorSearchService;
use App\Services\GapGptService;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RagController extends Controller
{
    public function __construct(
        protected DocumentParserService $parser,
        protected VectorSearchService $vectorSearch,
        protected GapGptService $gapGpt
    ) {}

    /**
     * صفحه اصلی سیستم RAG
     */
    public function index()
    {
        $documents = Document::withCount('chunks')
            ->latest()
            ->get();

        return view('rag.index', compact('documents'));
    }

    /**
     * آپلود، پارس و ساخت Embedding اسناد
     *
     * فرمت‌های مجاز:
     * - TXT
     *
     * حداکثر حجم:
     * - 5MB
     */
    public function uploadDocument(Request $request)
    {
        /*
         * اعتبارسنجی فایل
         * max:5120 یعنی حداکثر 5120 کیلوبایت = 5 مگابایت
         */
        $request->validate(
            [
                'document' => [
                    'required',
                    'file',
                    'mimes:txt',
                    'max:5120',
                ],
            ],
            [
                'document.required' => 'لطفاً یک فایل انتخاب کنید.',
                'document.file'     => 'فایل انتخاب شده معتبر نیست.',
                'document.mimes'    => 'فقط فایل‌های TXT مجاز هستند.',
                'document.max'      => 'حداکثر حجم فایل مجاز 5 مگابایت است.',
            ]
        );

        $file     = $request->file('document');
        $fileName = $file->getClientOriginalName();
        $ext      = strtolower($file->getClientOriginalExtension());
        $size     = $file->getSize();

        $path     = null;
        $document = null;

        try {
            /*
             * ذخیره فایل روی دیسک local
             */
            $path = $file->store('documents', 'local');

            if (!$path) {
                throw new \RuntimeException('ذخیره فایل با خطا مواجه شد.');
            }

            /*
             * گرفتن مسیر واقعی فایل
             */
            $fullPath = Storage::disk('local')->path($path);

            if (!is_file($fullPath)) {
                throw new \RuntimeException("فایل در مسیر {$fullPath} یافت نشد.");
            }

            /*
             * استخراج متن از سند
             */
            $parsed   = $this->parser->extractText($fullPath, $ext);
            $fullText = $parsed['full_text'] ?? '';

            if (empty(trim($fullText))) {
                throw new \RuntimeException('متنی در فایل آپلود شده یافت نشد.');
            }

            /*
             * ساخت رکورد سند
             */
            $document = Document::create([
                'name'         => $fileName,
                'file_path'    => $path,
                'file_type'    => $ext,
                'file_size'    => $size,
                'status'       => 'processing',
                'total_chunks' => 0,
            ]);

            /*
             * تنظیمات Chunk
             */
            $chunkSize = (int) config('gapgpt.chunk_size', env('RAG_CHUNK_SIZE', 600));
            $overlap   = (int) config('gapgpt.chunk_overlap', env('RAG_CHUNK_OVERLAP', 80));

            /*
             * تقسیم متن به Chunk
             */
            $textChunks = $this->vectorSearch->chunkText(
                $fullText,
                $chunkSize,
                $overlap
            );

            if (empty($textChunks)) {
                throw new \RuntimeException('متن فایل برای ایجاد چانک کافی نیست.');
            }

            /*
             * دریافت Embedding از GapGPT
             * ارسال Chunkها به صورت Batch
             */
            $batchSize    = 10;
            $allEmbeddings = [];

            for ($i = 0; $i < count($textChunks); $i += $batchSize) {
                $batch      = array_slice($textChunks, $i, $batchSize);
                $embeddings = $this->gapGpt->getEmbedding($batch);

                if (!is_array($embeddings)) {
                    throw new \RuntimeException('خطا در دریافت پاسخ Embedding از GapGPT.');
                }

                $allEmbeddings = array_merge($allEmbeddings, $embeddings);
            }

            /*
             * بررسی تعداد Embeddingها
             */
            if (count($allEmbeddings) !== count($textChunks)) {
                throw new \RuntimeException('تعداد Embeddingهای دریافت شده با تعداد Chunkها برابر نیست.');
            }

            /*
             * ذخیره Chunkها
             */
            foreach ($textChunks as $index => $chunkText) {
                DocumentChunk::create([
                    'document_id' => $document->id,
                    'chunk_index' => $index,
                    'content'     => $chunkText,
                    'token_count' => (int) (mb_strlen($chunkText) / 3.5),
                    'embedding'   => $allEmbeddings[$index],
                ]);
            }

            /*
             * به‌روزرسانی وضعیت سند
             */
            $document->update([
                'total_chunks' => count($textChunks),
                'status'       => 'ready',
            ]);

            /*
             * پاسخ موفق
             */
            return response()->json([
                'success'  => true,
                'message'  => "سند '{$fileName}' با موفقیت پردازش و بردارسازی شد.",
                'document' => $document->load('chunks'),
            ]);

        } catch (Throwable $e) {
            /*
             * در صورت خطا، وضعیت سند failed شود
             */
            if ($document !== null) {
                $document->update([
                    'status' => 'failed',
                ]);
            }

            /*
             * حذف فایل در صورت خطا
             */
            if ($path !== null && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            /*
             * ثبت خطا در Laravel Log
             */
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'خطا در پردازش سند: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * پرسش از سیستم RAG
     */
    public function askQuestion(Request $request)
    {
        $request->validate(
            [
                'question' => 'required|string|min:2',
                'history'  => 'nullable|array',
            ],
            [
                'question.required' => 'لطفاً سؤال خود را وارد کنید.',
                'question.min'      => 'سؤال باید حداقل ۲ کاراکتر باشد.',
                'history.array'     => 'فرمت تاریخچه گفتگو صحیح نیست.',
            ]
        );

        $question = $request->input('question');
        $history  = $request->input('history', []);

        try {
            /*
             * ساخت Embedding برای سؤال
             */
            $queryEmbedding = $this->gapGpt->getEmbedding($question);

            if (!is_array($queryEmbedding) || empty($queryEmbedding)) {
                throw new \RuntimeException('ساخت Embedding برای سؤال با خطا مواجه شد.');
            }

            /*
             * تعداد نتایج مرتبط (Context بیشتر جهت تولید پاسخ مفصل‌تر)
             */
            $topK = 8;

            /*
             * جستجوی Semantic
             */
            $relevantChunks = $this->vectorSearch->searchTopK(
                $queryEmbedding,
                $topK
            );

            /*
             * تولید پاسخ توسط GapGPT
             */
            $answer = $this->gapGpt->generateAnswer(
                $question,
                $relevantChunks,
                $history
            );

            /*
             * پاسخ نهایی
             */
            return response()->json([
                'success' => true,
                'answer'  => $answer,
                'sources' => $relevantChunks,
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'خطا در پاسخ‌دهی RAG: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * حذف یک سند
     */
    public function deleteDocument($id)
    {
        try {
            /*
             * پیدا کردن سند
             */
            $doc = Document::findOrFail($id);

            /*
             * حذف فایل فیزیکی
             */
            if ($doc->file_path && Storage::disk('local')->exists($doc->file_path)) {
                Storage::disk('local')->delete($doc->file_path);
            }

            /*
             * حذف Chunkهای سند
             */
            DocumentChunk::where('document_id', $doc->id)->delete();

            /*
             * حذف رکورد سند
             */
            $doc->delete();

            /*
             * پاسخ موفق
             */
            return response()->json([
                'success' => true,
                'message' => 'سند مورد نظر با موفقیت حذف شد.',
            ]);

        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف سند: ' . $e->getMessage(),
            ], 500);
        }
    }
}
