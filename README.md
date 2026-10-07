<div dir="rtl">

# منصة Charity

منصة رقمية لإدارة العمل الخيري وربط **المتبرعين** و**المستفيدين** و**المتطوعين** والجهة المشرفة ضمن نظام واحد. توفر المنصة دورة متكاملة تبدأ من إنشاء الحساب وإكمال الملف الشخصي، مرورًا بالحملات والتبرعات والكفالات وطلبات المساعدة، ووصولًا إلى الإشعارات والتقارير والإيصالات الرقمية.

</div>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Database-MySQL-4479A1?logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Frontend-Vite%20%2B%20Tailwind%20CSS-646CFF?logo=vite&logoColor=white" alt="Vite and Tailwind CSS">
  <img src="https://img.shields.io/badge/License-MIT-green" alt="MIT License">
</p>

<div dir="rtl">

## فهرس المحتويات

- [عن المشروع](#عن-المشروع)
- [الوظائف الرئيسية](#الوظائف-الرئيسية)
- [الأدوار](#الأدوار)
- [التقنيات والتكاملات](#التقنيات-والتكاملات)
- [متطلبات التشغيل](#متطلبات-التشغيل)
- [التثبيت والتشغيل محليًا](#التثبيت-والتشغيل-محليًا)
- [إعداد الخدمات الخارجية](#إعداد-الخدمات-الخارجية)
- [الأوامر المهمة](#الأوامر-المهمة)
- [واجهة الـ API](#واجهة-الـ-api)
- [هيكل المشروع](#هيكل-المشروع)
- [الاختبارات](#الاختبارات)
- [الأمان](#الأمان)
- [المساهمة](#المساهمة)
- [الترخيص](#الترخيص)

## عن المشروع

Charity هو نظام إدارة خيري مبني بأسلوب API-first باستخدام Laravel. يركز النظام على:

- تمكين المتبرع من اكتشاف الحملات والتبرع لمرة واحدة أو بشكل متكرر.
- تنظيم طلبات المساعدة والزيارات الخاصة بالمستفيدين.
- إدارة فرص التطوع والمهام والتقييمات والشهادات والنقاط.
- إدارة الكفالات والمدفوعات والرسائل بين الكافل والمستفيد.
- تزويد المشرفين بإدارة مركزية للمستخدمين والحملات والتبرعات والبلاغات.
- توفير إشعارات فورية، محادثات، تقارير، وإيصالات تبرع بصيغة PDF.

## الوظائف الرئيسية

### التبرعات والحملات

- إنشاء الحملات وتعديلها وعرضها وتصنيفها.
- إبراز الحملات العاجلة والمميزة.
- التبرع لمرة واحدة، والتبرع بالهدية، والتبرع الدوري.
- سلة تبرعات وسجل تفصيلي لتبرعات المستخدم.
- إحصائيات التبرعات وإيصال قابل للعرض والتنزيل بصيغة PDF.
- تصدير بيانات التبرعات إلى ملفات Excel.
- دعم الدفع عبر Stripe، مع تهيئة تكاملات الدفع الأخرى حسب إعدادات البيئة.

### المستفيدون وطلبات المساعدة

- إنشاء ملف المستفيد وإكمال بياناته وتحديثها.
- تقديم طلبات مساعدة ومتابعة حالتها وإحصائياتها.
- إدارة الزيارات ومواعيدها وتفاصيلها.
- تصنيف الاحتياجات والمدن والبيانات المرجعية.
- إدارة المستفيدين وحالات القبول من خلال مسارات الإدارة.

### التطوع

- ملفات المتطوعين ومجالاتهم ومهاراتهم ولغاتهم وأيام التوفر.
- إنشاء مهام تطوعية وإسنادها ومتابعة طلبات البدء والإنهاء.
- تسجيل الحضور وتقييم المهام.
- نقاط المتطوعين ولوحة ترتيب وشهادات وشارات إنجاز.

### الكفالات والتواصل

- عرض المستفيدين المتاحين للكفالة وإنشاء الكفالات وإدارتها.
- إدارة دفعات الكفالة.
- رسائل مباشرة بين الكافل والمستفيد.
- محادثات فردية أو جماعية مع دعم حالة القراءة ومؤشر الكتابة.

### الإشعارات والتقارير

- إشعارات داخلية مع عداد لغير المقروء وإمكانية تعليم الإشعارات كمقروءة.
- تفضيلات الإشعارات وتسجيل رموز Firebase Cloud Messaging.
- تقارير عامة للتبرعات، مصادر الدفع، الفئات، كبار المتبرعين، المستفيدين والمتطوعين.
- سجلات تدقيق وسجلات دخول لمتابعة الأنشطة المهمة.

## الأدوار

يدعم النظام عدة أنواع من المستخدمين، ولكل نوع مسارات وعمليات مناسبة:

| الدور | المسؤوليات الأساسية |
| --- | --- |
| المتبرع `Donor` | تصفح الحملات، التبرع، متابعة السجل والإيصالات، وإدارة الكفالات |
| المستفيد `Beneficiary` | إكمال الملف، تقديم طلبات المساعدة، وإدارة الزيارات |
| المتطوع `volunteer` | استعراض المهام، طلب تنفيذها، تسجيل الحضور، واستلام التقييمات والشهادات |
| موظف الإدارة | إدارة المستخدمين والحملات والتبرعات والمستفيدين والمهام |
| مدير / محاسب / مشاهد | أدوار إدارية متخصصة بحسب الصلاحيات التشغيلية |

## التقنيات والتكاملات

- **Backend:** PHP 8.2+ وLaravel 12.
- **Authentication:** Laravel Sanctum وواجهات تسجيل الدخول والتحقق عبر OTP.
- **Database:** MySQL في بيئة التشغيل المحلية، مع SQLite داخل إعداد الاختبارات.
- **Frontend assets:** Vite وTailwind CSS وAxios.
- **Payments:** Stripe، مع حزم جاهزة لتكامل PayerURL والدفع بالعملات الرقمية.
- **Notifications:** Firebase Cloud Messaging.
- **Realtime:** Pusher وبث أحداث الرسائل وحالة القراءة والكتابة.
- **Documents:** Dompdf وmPDF لإنتاج إيصالات PDF.
- **Exports:** Laravel Excel لتصدير البيانات.
- **Social login:** Laravel Socialite مع Google وFacebook عند تفعيل مفاتيح الخدمات.

## متطلبات التشغيل

تأكد من تثبيت ما يلي:

- PHP `8.2` أو أحدث مع الامتدادات `gd` و`pdo_mysql` و`zip`.
- Composer 2.
- Node.js وnpm.
- MySQL 8 أو MariaDB متوافق.
- بيانات اعتماد الخدمات الخارجية عند تفعيل الدفع أو الإشعارات أو تسجيل الدخول الاجتماعي.

## التثبيت والتشغيل محليًا

### 1. جلب المشروع

```bash
git clone https://github.com/ThaerGhaderi/Project-Charity.git
cd Project-Charity
```

### 2. تثبيت الاعتماديات

```bash
composer install
npm install
```

### 3. إنشاء ملف البيئة

```bash
cp .env.example .env
php artisan key:generate
```

في Windows يمكن نسخ الملف يدويًا أو تنفيذ:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

### 4. إعداد قاعدة البيانات

أنشئ قاعدة بيانات باسم `project_charity` أو اختر اسمًا آخر، ثم حدّث قيم `DB_*` في `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=project_charity
DB_USERNAME=root
DB_PASSWORD=
```

بعد ذلك شغّل الترحيلات:

```bash
php artisan migrate
```

لإنشاء بيانات تجريبية محلية:

```bash
php artisan db:seed
```

> لا تستخدم `migrate:fresh --seed` على قاعدة بيانات تحتوي بيانات مهمة؛ فهذا الأمر يحذف الجداول ويعيد إنشاءها.

### 5. ربط التخزين وبناء ملفات الواجهة

```bash
php artisan storage:link
npm run build
```

### 6. تشغيل التطبيق

للتشغيل السريع:

```bash
php artisan serve
```

ثم افتح `http://127.0.0.1:8000`.

أثناء التطوير يمكن تشغيل الخادم، وطابور المهام، وسجل Laravel، وVite معًا:

```bash
composer run dev
```

## إعداد الخدمات الخارجية

ضع المفاتيح في `.env` فقط، ولا ترفعها إلى GitHub:

### Stripe

```dotenv
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=usd
```

وجّه Webhook الخاص بـ Stripe إلى:

```text
POST /api/stripe/webhook
```

### Google وFacebook OAuth

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI=
```

### Firebase وPusher

يحتاج Firebase إلى بيانات اعتماد حساب الخدمة المشار إليها في إعدادات Firebase، بينما يحتاج Pusher إلى مفاتيح التطبيق والـ cluster:

```dotenv
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
```

راجع ملفات الإعداد داخل `config/` لمعرفة أسماء المتغيرات الإضافية الخاصة بالبريد وPayerURL والتخزين السحابي.

## الأوامر المهمة

| الأمر | الاستخدام |
| --- | --- |
| `composer run setup` | تثبيت الاعتماديات، إنشاء البيئة، الترحيلات، وبناء الواجهة |
| `composer run dev` | تشغيل بيئة التطوير المتكاملة |
| `php artisan migrate` | تطبيق ترحيلات قاعدة البيانات |
| `php artisan db:seed` | إدخال البيانات التجريبية |
| `php artisan route:list` | عرض جميع مسارات التطبيق |
| `php artisan config:clear` | مسح الإعدادات المخزنة مؤقتًا |
| `npm run dev` | تشغيل Vite بوضع المراقبة |
| `npm run build` | بناء أصول الواجهة للإنتاج |
| `composer test` | تشغيل اختبارات PHPUnit عبر Laravel |

## واجهة الـ API

توجد مسارات الـ API في [`routes/api.php`](routes/api.php)، ويضيف Laravel البادئة `/api` تلقائيًا. أمثلة على مجموعات المسارات:

| المجموعة | أمثلة |
| --- | --- |
| المصادقة | `/api/auth/register`، `/api/auth/login`، `/api/auth/verify-otp` |
| المستفيد | `/api/beneficiary/profile`، `/api/beneficiary/aid-applications` |
| المتبرع | `/api/donor/campaigns`، `/api/donor/donations` |
| الكفالات | `/api/sponsorships`، `/api/sponsorships/{id}/payments` |
| الإشعارات | `/api/notifications` |
| المحادثات | `/api/chat/conversations` |
| التقارير | `/api/reports/general`، `/api/reports/donations` |
| الإدارة | إدارة الحملات والمستفيدين والمتطوعين والمهام والتبرعات |

المسارات المحمية تستخدم Laravel Sanctum. بعد تسجيل الدخول أرسل التوكن في الطلبات اللاحقة:

```http
Authorization: Bearer <token>
Accept: application/json
```

للحصول على قائمة دقيقة بالمسارات والـ middleware والـ HTTP methods:

```bash
php artisan route:list --path=api
```

## هيكل المشروع

```text
app/
├── Http/Controllers/   # منطق استقبال طلبات الويب والـ API
├── Http/Requests/       # التحقق من المدخلات
├── Models/              # نماذج Eloquent والعلاقات
├── Services/            # تكاملات وخدمات الأعمال
├── Events/              # أحداث البث والتواصل الفوري
└── Exports/             # تصدير البيانات
database/
├── migrations/          # مخطط قاعدة البيانات
├── factories/           # مصانع الاختبارات
└── seeders/             # بيانات التشغيل التجريبية
routes/
├── api.php              # واجهة REST API
├── web.php              # صفحات الويب ونتائج الدفع
└── console.php          # أوامر Artisan
resources/
├── views/               # قوالب Blade وصفحات الدفع والإيصالات
├── css/                 # أنماط Tailwind
└── js/                  # نقطة دخول Vite وAxios
config/                  # إعدادات Laravel والتكاملات الخارجية
tests/                   # اختبارات Unit وFeature
```

## الاختبارات

يستخدم المشروع PHPUnit، ويجهز `phpunit.xml` قاعدة SQLite داخل الذاكرة للاختبارات:

```bash
php artisan test
```

أو:

```bash
composer test
```

قبل فتح Pull Request، شغّل الاختبارات وبناء الواجهة وتحقق من أن الترحيلات تعمل على نسخة نظيفة من قاعدة البيانات.

## الأمان

- لا ترفع `.env` أو مفاتيح Stripe أو Firebase أو OAuth أو SMTP إلى المستودع.
- استخدم أسرارًا جديدة في بيئة الإنتاج إذا سبق مشاركة أي مفاتيح خارج مدير أسرار آمن.
- اضبط `APP_DEBUG=false` في الإنتاج.
- استخدم HTTPS، وحدّث `APP_URL` و`FRONTEND_URL` وبيانات CORS بما يناسب النشر.
- احمِ Webhooks وتحقق من توقيعات Stripe وPayerURL قبل معالجة المدفوعات.
- أبلغ عن الثغرات الأمنية بشكل خاص إلى مالكي المشروع بدل نشر تفاصيلها في Issue عامة.

## المساهمة

المساهمات مرحب بها:

1. أنشئ Fork للمشروع.
2. أنشئ فرعًا واضح الاسم مثل `feature/recurring-donations`.
3. نفّذ التغيير مع اختبار مناسب.
4. شغّل `php artisan test` و`npm run build`.
5. افتح Pull Request يوضح المشكلة والحل والتغييرات المؤثرة على الإعدادات.

## الترخيص

هذا المشروع مرخص بموجب **MIT License** وفق بيانات الحزمة الحالية. أضف ملف `LICENSE` رسميًا إلى المستودع عند نشر النسخة العامة إذا لم يكن موجودًا.

<div dir="rtl">

### شكرًا لمساهمتك في بناء أثر خيري أكبر

</div>
