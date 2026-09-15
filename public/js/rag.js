// CSRF Token Setup
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

// Chat history with localStorage persistence
const STORAGE_KEY = 'rag_chat_history';
let chatHistory = [];

// Load history from localStorage
try {
    const savedHistory = localStorage.getItem(STORAGE_KEY);
    if (savedHistory) {
        chatHistory = JSON.parse(savedHistory);
    }
} catch (error) {
    console.error('خطا در بازیابی تاریخچه:', error);
    chatHistory = [];
}

// Save history to localStorage
function saveChatHistory() {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(chatHistory));
}

// Function to clear history (Global access)
function clearChatHistory() {
    if (confirm('آیا مطمئن هستید که می‌خواهید تمام تاریخچه گفتگو را پاک کنید؟')) {
        chatHistory = [];
        localStorage.removeItem(STORAGE_KEY);
        const chatContainer = document.getElementById('chatMessages');
        if (chatContainer) {
            chatContainer.innerHTML = '';
        }
    }
}

// Restore chat messages on page load
document.addEventListener('DOMContentLoaded', () => {
    const chatContainer = document.getElementById('chatMessages');
    if (!chatContainer) return;

    chatHistory.forEach((msg) => {
        if (msg.role === 'user') {
            renderUserMessage(msg.content);
        } else if (msg.role === 'assistant') {
            renderAssistantMessage(msg.content, msg.sources || []);
        }
    });

    chatContainer.scrollTop = chatContainer.scrollHeight;
});

// Drag & Drop Setup
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');
const uploadProgress = document.getElementById('uploadProgress');

if (dropzone && fileInput) {
    dropzone.addEventListener('click', () => fileInput.click());

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('border-emerald-500');
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.classList.remove('border-emerald-500');
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('border-emerald-500');
        if (e.dataTransfer.files.length > 0) {
            uploadFile(e.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            uploadFile(fileInput.files[0]);
        }
    });
}

// Upload file function
async function uploadFile(file) {
    const formData = new FormData();
    formData.append('document', file);

    uploadProgress?.classList.remove('hidden');

    try {
        const response = await fetch('/api/documents/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const data = await response.json();
        uploadProgress?.classList.add('hidden');

        if (data.success) {
            const list = document.getElementById('documentList');
            const empty = document.getElementById('emptyDocs');
            if (empty) empty.remove();

            const doc = data.document;
            const item = document.createElement('div');
            item.id = `doc-${doc.id}`;
            item.className = 'p-3 bg-slate-950/60 border border-slate-800/80 rounded-xl flex items-center justify-between group hover:border-slate-700 transition-colors animate-fadeIn';
            item.innerHTML = `
                <div class="flex items-center space-x-3 space-x-reverse truncate">
                    <span class="px-2 py-1 bg-slate-800 text-xs font-mono rounded text-slate-300 uppercase font-semibold">${escapeHtml(doc.file_type)}</span>
                    <div class="truncate">
                        <p class="text-sm font-medium text-slate-200 truncate">${escapeHtml(doc.name)}</p>
                        <p class="text-xs text-slate-500">${doc.total_chunks} قطعه (چانک)</p>
                    </div>
                </div>
                <button onclick="deleteDoc(${doc.id})" class="text-slate-500 hover:text-rose-400 p-1 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            `;
            list.prepend(item);
            fileInput.value = '';
            alert('سند با موفقیت افزوده شد!');
        } else {
            alert(data.message || 'خطا در آپلود سند');
        }
    } catch (err) {
        uploadProgress?.classList.add('hidden');
        alert('خطای ارتباط با سرور: ' + err.message);
    }
}

// Delete Document
async function deleteDoc(id) {
    if (!confirm('آیا از حذف این سند اطمینان دارید؟')) return;

    try {
        const res = await fetch(`/api/documents/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            }
        });

        const data = await res.json();
        if (data.success) {
            document.getElementById(`doc-${id}`)?.remove();
        }
    } catch (e) {
        alert('خطا در حذف سند');
    }
}

// Render user message
function renderUserMessage(question) {
    const chatContainer = document.getElementById('chatMessages');
    if (!chatContainer) return;

    const userMsg = document.createElement('div');
    userMsg.className = 'flex items-start justify-end space-x-3 space-x-reverse';
    userMsg.innerHTML = `
        <div class="bg-emerald-600 text-white rounded-2xl rounded-tl-none p-4 max-w-2xl text-sm leading-relaxed shadow-md">
            ${escapeHtml(question)}
        </div>
        <div class="w-8 h-8 rounded-lg bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-300">
            شما
        </div>
    `;
    chatContainer.appendChild(userMsg);
}

// Render assistant message
function renderAssistantMessage(answer, sources = []) {
    const chatContainer = document.getElementById('chatMessages');
    if (!chatContainer) return;

    const aiMsg = document.createElement('div');
    aiMsg.className = 'flex items-start space-x-3 space-x-reverse';

    let sourcesHtml = '';
    if (sources && sources.length > 0) {
        sourcesHtml = `
            <div class="mt-4 pt-3 border-t border-slate-700/60">
                <p class="text-xs font-semibold text-slate-400 mb-2 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    منابع استخراج شده (Top-K):
                </p>
                <div class="space-y-1.5">
                    ${sources.map((s, idx) => `
                        <div class="text-xs bg-slate-900/90 border border-slate-700/50 rounded-lg p-2 text-slate-300">
                            <div class="flex items-center justify-between text-slate-400 mb-1">
                                <span class="font-medium text-emerald-400">منبع ${idx + 1}: ${escapeHtml(s.document_name || 'بدون نام')}</span>
                                <span class="font-mono text-[10px] bg-slate-800 px-1.5 py-0.5 rounded">
                                    شباهت: ${typeof s.similarity === 'number' ? Math.round(s.similarity * 100) : 0}%
                                </span>
                            </div>
                            <p class="line-clamp-2 text-slate-400">${escapeHtml(s.text || '')}</p>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    aiMsg.innerHTML = `
        <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center text-xs font-bold text-white shrink-0">AI</div>
        <div class="bg-slate-800/90 border border-slate-700/60 rounded-2xl rounded-tr-none p-4 max-w-2xl text-sm leading-relaxed text-slate-100 shadow-md">
            <div class="whitespace-pre-wrap">${escapeHtml(answer)}</div>
            ${sourcesHtml}
        </div>
    `;

    chatContainer.appendChild(aiMsg);
}

// Send Question
async function handleSend(e) {
    e.preventDefault();

    const input = document.getElementById('questionInput');
    const chatContainer = document.getElementById('chatMessages');
    if (!input || !chatContainer) return;

    const question = input.value.trim();
    if (!question) return;

    input.value = '';

    // Render user message immediately
    renderUserMessage(question);
    chatContainer.scrollTop = chatContainer.scrollHeight;

    // Loading placeholder
    const loadingMsg = document.createElement('div');
    loadingMsg.className = 'flex items-start space-x-3 space-x-reverse';
    loadingMsg.innerHTML = `
        <div class="w-8 h-8 rounded-lg bg-emerald-600 flex items-center justify-center text-xs font-bold text-white">AI</div>
        <div class="bg-slate-800/80 border border-slate-700/60 rounded-2xl rounded-tr-none p-4 max-w-2xl text-sm text-slate-400 animate-pulse flex items-center gap-2">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            در حال بازیابی برداری اسناد و پاسخ‌دهی
        </div>
    `;
    chatContainer.appendChild(loadingMsg);
    chatContainer.scrollTop = chatContainer.scrollHeight;

    try {
        const response = await fetch('/api/rag/ask', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                question: question,
                history: chatHistory,
            })
        });

        const data = await response.json();
        loadingMsg.remove();

        if (data.success) {
            // Save both user and assistant messages in localStorage
            chatHistory.push({
                role: 'user',
                content: question,
            });

            chatHistory.push({
                role: 'assistant',
                content: data.answer,
                sources: data.sources || [],
            });

            saveChatHistory();

            // Render assistant message
            renderAssistantMessage(data.answer, data.sources || []);
        } else {
            const errDiv = document.createElement('div');
            errDiv.className = 'text-center text-rose-400 text-xs py-2';
            errDiv.textContent = data.message || 'خطا در دریافت پاسخ';
            chatContainer.appendChild(errDiv);
        }
    } catch (err) {
        loadingMsg.remove();
        alert('خطای اتصال به سرور: ' + err.message);
    }

    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}
