@extends('layouts.app')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[calc(100vh-8.5rem)]">
    
    <!-- پنل سمت راست: مدیریت و آپلود فایل‌ها -->
    <div class="lg:col-span-4 flex flex-col bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl overflow-hidden">
        <h2 class="text-base font-bold text-slate-200 mb-4 flex items-center justify-between">
            <span>مدیریت اسناد و دانش‌نامه</span>
            <span class="text-xs font-normal text-slate-400" id="docCount">{{ $documents->count() }} سند</span>
        </h2>

        <!-- کادر درگ و دراپ آپلود -->
        <div id="dropzone" class="border-2 border-dashed border-slate-700 hover:border-emerald-500/50 rounded-xl p-5 text-center cursor-pointer transition-colors bg-slate-950/50 group mb-4">
            <input type="file" id="fileInput" class="hidden" accept=".txt" />
            <div class="flex flex-col items-center justify-center space-y-2">
                <svg class="w-8 h-8 text-slate-400 group-hover:text-emerald-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-sm font-medium text-slate-300">برای آپلود فایل کلیک کنید یا فایل را بکشید</p>
                <p class="text-xs text-slate-500">پشتیبانی از  TXT</p>
            </div>
            <div id="uploadProgress" class="hidden mt-3 text-xs text-emerald-400 font-mono animate-pulse">
               در حال استخراج متن و ساخت Embedding 
            </div>
        </div>

        <!-- لیست اسناد آپلود شده -->
        <div class="flex-1 overflow-y-auto space-y-2.5 pr-1" id="documentList">
            @forelse($documents as $doc)
                <div class="p-3 bg-slate-950/60 border border-slate-800/80 rounded-xl flex items-center justify-between group hover:border-slate-700 transition-colors" id="doc-{{ $doc->id }}">
                    <div class="flex items-center space-x-3 space-x-reverse truncate">
                        <span class="px-2 py-1 bg-slate-800 text-xs font-mono rounded text-slate-300 uppercase font-semibold">
                            {{ $doc->file_type }}
                        </span>
                        <div class="truncate">
                            <p class="text-sm font-medium text-slate-200 truncate">{{ $doc->name }}</p>
                            <p class="text-xs text-slate-500">{{ $doc->chunks_count }} قطعه (چانک)</p>
                        </div>
                    </div>
                    <button onclick="deleteDoc({{ $doc->id }})" class="text-slate-500 hover:text-rose-400 p-1 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            @empty
                <div id="emptyDocs" class="text-center py-8 text-slate-500 text-sm">
                   هنوز سندی آپلود نشده است. برای شروع یک فایل TXT  اضافه کنید.
                </div>
            @endforelse
        </div>
    </div>

    <!-- پنل سمت چپ: چت تعاملی و پرسش و پاسخ  -->
    <div class="lg:col-span-8 flex flex-col bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        
        <!-- پیام‌های چت -->
        <div id="chatMessages" class="flex-1 overflow-y-auto p-6 space-y-6">
            <div class="flex items-start space-x-3 space-x-reverse">
                <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center text-xs font-bold text-white">
                    AI
                </div>
                <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl rounded-tr-none p-4 max-w-2xl text-sm leading-relaxed text-slate-200">
                   سلام! من دستیار هوشمند هستم. شما می‌توانید هر سوالی درباره اسناد آپلود شده بپرسید تا با استناد دقیق به صفحات و متون سند به شما پاسخ دهم.
                </div>
            </div>
        </div>

        <!-- نوار ارسال پیام -->
        <div class="p-4 bg-slate-950/80 border-t border-slate-800">
            <form id="ragForm" onsubmit="handleSend(event)" class="flex items-center gap-2">
                <input 
                    type="text" 
                    id="questionInput" 
                    placeholder="سوال خود را درباره اسناد بنویسید " 
                    class="flex-1 bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                    autocomplete="off"
                />
                <button 
                    type="submit" 
                    id="sendBtn"
                    class="px-5 py-3 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white text-sm font-medium rounded-xl transition-all shadow-lg shadow-emerald-600/20 flex items-center gap-2"
                >
                    <span>ارسال</span>
                    <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
