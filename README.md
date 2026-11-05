# منصة العكريشي – دليل الإعداد

## 1) إنشاء المشروع (مرة واحدة)
1. أنشئ مشروعًا في https://console.firebase.google.com
2. فعّل: Authentication ← Email/Password و **Google** (Sign-in method ← Google ← Enable، واختر إيميلك كبريد الدعم)، و Firestore Database، و **Storage** (لرفع الفيديو من الجهاز)، و Hosting.
3. ارفع الخطة إلى Blaze (لازمة لدوال الدفع؛ تكلفتها غالبًا بسيطة في البداية).
4. من إعدادات المشروع ← Your apps ← Web، انسخ بيانات التهيئة والصقها في `public/index.html` داخل `CFG`، وضع رقم واتساب في `WHATSAPP`.

## 2) الإعداد على جهازك
```
npm i -g firebase-tools
firebase login
firebase use --add            # اختر مشروعك
# إعدادات المدير مضبوطة في functions/.env (ADMIN_EMAILS و ANY_ACCOUNT)
firebase functions:secrets:set ADMIN_CODE            # رمز المدير السري (اختره قويًا)
firebase functions:secrets:set GMAIL_APP_PASSWORD     # كلمة مرور التطبيقات لجيميل المدير (الخطوة 4)
cd functions && npm install && cd ..
firebase deploy
```

## رفع الفيديو من الجهاز
- في «الدروس والفيديوهات» اختر ملف فيديو من جهازك (حتى 2 جيجا) وسيُرفع إلى Firebase Storage ولا يشاهده إلا المشترك.
- التخزين والمشاهدة يُحسبان في فاتورة Firebase حسب الحجم وعدد المشاهدات، فراجع الأسعار. لعدد كبير من الطلاب يوتيوب (غير مدرج) أرخص.

## 3) الدفع بالتحويل إلى رقمك
- الطالب يحوّل سعر الكورس إلى الرقم المكتوب في `PAY_NUMBER` داخل `public/index.html` (حاليًا 01151045032)، ثم يكتب رقم الهاتف الذي حوّل منه ويضغط «أرسلت المبلغ».
- يظهر الطلب في لوحة المدير ← **الدفعات**. تتأكد من وصول المبلغ في محفظتك ثم تضغط «قبول» فيُفتح الكورس للطالب فورًا، أو «رفض» مع ذكر السبب.
- لتغيير الرقم عدّل `PAY_NUMBER` ثم `firebase deploy --only hosting`.
- الدفع هنا يدوي: لا يتحقق النظام من وصول المبلغ تلقائيًا، فراجع محفظتك قبل كل قبول.

## 4) دخولك كمدير (ثلاث خطوات أمان)
**إعداد مرة واحدة:** الرسائل تُرسل من جيميلك نفسه:
1. في حساب Google للمدير فعّل «التحقق بخطوتين» (Security ← 2-Step Verification).
2. ثم Security ← **App passwords** وأنشئ كلمة باسم «العكريشي»، فتحصل على 16 حرفًا.
3. نفّذ `firebase functions:secrets:set GMAIL_APP_PASSWORD` والصقها.

**كل مرة تدخل كمدير:**
1. سجّل دخولك بحساب المدير المذكور في `ADMIN_EMAILS` (حاليًا aldshash0@gmail.com). أي حساب آخر يُرفض.
2. اضغط «دخول المدير» أسفل الصفحة واكتب الرمز السري.
3. يصلك على بريد المدير رمز من 6 أرقام صالح 10 دقائق، اكتبه في المنصة.
بعدها يبقى التصريح 8 ساعات ثم يُطلب منك ما سبق. خمس محاولات خاطئة تغلق الدخول 15 دقيقة، ويُبطل الرمز بعد 5 إدخالات خاطئة.

لإضافة مساعد أضف إيميله في `ADMIN_EMAILS` مفصولًا بفاصلة ثم `firebase deploy --only functions`. (الرمز يصل دائمًا إلى `OTP_EMAIL`، أي لك أنت.)

## 5) الدومين
Firebase Console ← Hosting ← Add custom domain، ثم اتبع خطوات DNS عند مزوّد الدومين. وأضف الدومين أيضًا في Authentication ← Settings ← Authorized domains حتى يعمل الدخول بـ Google.

## قبل الإطلاق
- عدّل نصوص الشروط والخصوصية والاسترجاع في `public/index.html` (كائن `LEGAL`) لتناسب نشاطك.
- جرّب كل شيء بحساب طالب تجريبي.

## ملاحظات GitHub
- ارفع المشروع في مستودع **خاص (Private)**. لا ترفع `functions/.env` ولا أي كلمات مرور أو أسرار؛ الملف `.gitignore` يمنع ذلك.
- بعد النسخ من GitHub أنشئ `functions/.env` من `functions/.env.example` قبل `firebase deploy`.
- GitHub يحفظ الكود فقط ولا يشغّل المنصة؛ تشغيلها يتم عبر Firebase كما في الخطوات أعلاه.
