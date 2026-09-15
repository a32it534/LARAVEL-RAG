# 🤖 LARAVEL-RAG: AI Document Intelligence System

یک سیستم چت هوشمند و پرسش‌وپاسخ (RAG - Retrieval-Augmented Generation) که به شما امکان می‌دهد با اسناد خود (شامل `.txt`) به زبان طبیعی صحبت کنید. این پروژه با استفاده از **Laravel 12** و سرویس اختصاصی **GapGpt پیاده‌سازی شده است.

---

## ✨ ویژگی‌های اصلی (Key Features)

*   **RAG Pipeline:** پردازش و تکه‌بندی (Chunking) خودکار اسناد برای درک بهتر محتوا.
*   **Vector & Semantic Search:** جستجوی هوشمندانه در میان اسناد بر اساس معنای متن، نه فقط کلمات کلیدی.
*   **AI Integration:** اتصال به مدل هوش مصنوعی از طریق `GapGptService` برای تحلیل و پاسخ‌دهی.
*   **History Management:** حفظ تاریخچه چت در `sessionStorage` مرورگر کاربر.
*   **Document Management:** سیستم مدیریت فایل‌ها با استفاده از `DocumentParserService` و ذخیره‌سازی در `storage/app/private`.

---

## 🛠 معماری فنی (Architecture)

این پروژه بر اساس اصول Clean Architecture در لاراول طراحی شده است:

*   **Controllers:** `RagController` مدیریت تعاملات کاربری و دریافت پرسش‌ها.
*   **Services:**
    *   `GapGptService`: مدیریت ارتباط با API هوش مصنوعی.
    *   `DocumentParserService`: استخراج متن و پردازش اسناد آپلود شده.
    *   `VectorSearchService`: اجرای الگوریتم‌های جستجوی معنایی.
*   **Models:**
    *   `Document`: مدیریت اسناد اصلی.
    *   `DocumentChunk`: مدیریت بخش‌های کوچک شده متن جهت جستجوی بهینه.

---

## 🚀 راهنمای نصب و راه‌اندازی

### ۱. پیش‌نیازها
*   PHP >= 8.2
*   Composer
*   Node.js & NPM
*   ( MySQL)

### ۲. مراحل نصب
۱. مخزن را کلون کنید:
```bash
git clone https://github.com/a32it534/LARAVEL-RAG.git
cd LARAVEL-RAG
