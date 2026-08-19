# 🚀 دليل التكامل والتطوير للمبرمج الأمامي (Frontend Notification Integration Guide)

يقدم هذا الدليل توضيحاً شاملاً ومفصلاً لمبرمجي التطبيقات (Flutter / React Native / Web) لكيفية الربط والتكامل مع **نظام الإشعارات الفورية والمخصصة** في الباك إند، سواء للإشعارات داخل التطبيق (In-App Database Notifications) أو خارج التطبيق (FCM Push Notifications).

---

## 📡 المفاهيم الأساسية وهيكلية النظام

النظام يدعم نوعين من التوصيل الفوري:
1. **خارج التطبيق (FCM Push Notification)**: إشعار منبثق يظهر في شريط التنبيهات بالجوال سواء كان التطبيق مفتوحاً، في الخلفية (Background)، أو مغلقاً (Terminated).
2. **داخل التطبيق (In-App API Notifications)**: إشعارات مخزنة في قاعدة البيانات يتم استعراضها في شاشة التنبيهات مع شارات الأعداد غير المقروءة (Badge Counts).

جميع الطلبات الموجهة للباك إند تطلب التوثيق عبر الهيدر القياسي:
`Authorization: Bearer <YOUR_SANCTUM_TOKEN>`
`Accept: application/json`

---

## 📲 1. إدارة توكنات FCM (Device Token Registration)

عند دخول المستخدم أو عند فتح التطبيق، قم بتسجيل توكن الجهاز `fcm_token` المستخرج من مكتبة Firebase Cloud Messaging لدى السيرفر.

### أ. تسجيل أو تحديث توكن الجهاز
- **Endpoint**: `POST /api/user/device-token`
- **Headers**:
  ```http
  Authorization: Bearer 1|abc...
  Content-Type: application/json
  ```
- **Request Body**:
  ```json
  {
    "fcm_token": "eXamPLe_FcM_tOkEn_sTrInG_123456789...",
    "device_id": "unique-device-identifier",  // مطلوب — معرّف ثابت للجهاز (مثال: identifierForVendor / Android ID)
    "device_name": "iPhone 15 Pro",           // اختياري
    "platform": "ios",                        // القيم المتاحة: "ios", "android", "web"
    "app_version": "1.4.2"                    // اختياري
  }
  ```
  > **مهم**: `device_id` حقل **مطلوب** (وليس اختيارياً). استخدم معرّفاً ثابتاً للجهاز نفسه (لا يتغيّر بين الجلسات)، حتى يستطيع الباك إند التعرّف على تحديث توكن FCM لنفس الجهاز (Token Refresh) بدل إنشاء صف مكرر. استدعِ هذا المسار عند كل تسجيل دخول وأيضاً عند كل مرة يصدر فيها Firebase SDK حدث `onTokenRefresh`.
- **Response Success (200 OK)**:
  ```json
  {
    "status": true,
    "message": "تم تسجيل رمز الإشعارات للجهاز بنجاح."
  }
  ```
- **Response Conflict (409)** — نادر جداً عملياً، يحدث فقط إذا وصل `fcm_token` مسجَّل حالياً لمستخدم آخر مع `device_id` مختلف عمّا كان مسجَّلاً لذلك التوكن (سياسة ملكية أمنية تمنع استيلاء حساب على جهاز حساب آخر). أعد المحاولة بعد تسجيل خروج/دخول جديد من التطبيق على هذا الجهاز تحديداً (لضمان توليد/إرسال fcm_token طازج):
  ```json
  {
    "status": false,
    "error_code": "DEVICE_TOKEN_CONFLICT",
    "message": "تعذر تسجيل هذا الجهاز، الرجاء إعادة تسجيل الدخول من التطبيق على هذا الجهاز والمحاولة مجدداً."
  }
  ```

### ب. إزالة توكن جهاز واحد عند تسجيل الخروج (Logout)
احرص عند تسجيل خروج المستخدم من التطبيق على إزالة توكن FCM لمنع استلام إشعارات الحساب على هذا الجهاز تحديداً بعد الخروج. أرسل `device_id` (المفضّل) أو `fcm_token` — أحدهما مطلوب.
- **Endpoint**: `DELETE /api/user/device-token`
- **Request Body**:
  ```json
  {
    "device_id": "unique-device-identifier"
  }
  ```
- **Response Success (200 OK)**:
  ```json
  {
    "status": true,
    "message": "تم إزالة رمز الإشعارات للجهاز بنجاح."
  }
  ```

### ج. تسجيل الخروج من كل الأجهزة (Logout All Devices)
استخدمه فقط في سيناريو صريح مثل "تسجيل الخروج من كل الأجهزة" من إعدادات الحساب — لا تستدعِه تلقائياً عند تسجيل الخروج العادي.
- **Endpoint**: `POST /api/user/device-token/logout-all`
- **Response Success (200 OK)**:
  ```json
  {
    "status": true,
    "message": "تم تسجيل الخروج من جميع الأجهزة بنجاح.",
    "removed": 3
  }
  ```

---

## 🔔 2. واجهات برمجية للإشعارات داخل التطبيق (In-App API Endpoints)

### 1. جلب قائمة الإشعارات (Paginated List)
عرض قائمة إشعارات المستخدم داخل شاشة الإشعارات.
- **Endpoint**: `GET /api/notifications?page=1&per_page=15`
- **Query Parameters (اختيارية)**:
  - `page` (int): رقم الصفحة (افتراضي: 1).
  - `per_page` (int): عدد العناصر في الصفحة (افتراضي: 15).
  - `unread_only` (bool): `true` لجلب غير المقروء فقط.
  - `type` (string): فلترة بحسب نوع الإشعار (مثال: `trip_started`).

- **Response Success (200 OK)**:
  ```json
  {
    "status": true,
    "data": {
      "notifications": [
        {
          "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d", // UUID الإشعار
          "type": "trip_started",                      // نوع الحدث
          "title": "بدء الرحلة 🚌",                     // عنوان الإشعار
          "message": "انطلقت الرحلة رقم #101، السائق في الطريق الآن.", // نص الإشعار
          "action_url": null,
          "entity_type": "trip",                       // نوع الكيان المرتبط (trip, contract, subscription_request...)
          "entity_id": "101",                         // معرف الكيان المرتبط (مثلاً trip_id)
          "screen": "TRIP_DETAILS",                   // رمز الشاشة المستهدفة بالتطبيق
          "action": "open_trip",                       // اسم الإجراء المقترح عند فتح الإشعار
          "payload": {
            "type": "trip_started",
            "entity_type": "trip",
            "entity_id": "101",
            "screen": "TRIP_DETAILS",
            "action": "open_trip"
          },
          "read_at": null,                             // null إذا كان غير مقروء
          "is_read": false,                            // حالة القراءة
          "created_at": "2026-07-26T14:00:00+02:00"
        }
      ],
      "pagination": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 15,
        "total": 42,
        "has_more": true
      },
      "unread_count": 5
    }
  }
  ```

---

### 2. جلب عدد الإشعارات غير المقروءة (Unread Count Badge)
استخدم هذا المسار لعرض الشارة الحمراء (Badge) فوق أيقونة الجرس في التاب بار.
- **Endpoint**: `GET /api/notifications/unread-count`
- **Response Success (200 OK)**:
  ```json
  {
    "status": true,
    "unread_count": 5
  }
  ```

---

### 3. تحديد إشعار محدد كمقروء (Mark as Read)
عند ضغط المستخدم على إشعار معين في القائمة.
- **Endpoint**: `PATCH /api/notifications/{id}/read`
- **Response Success (200 OK)**:
  ```json
  {
    "status": true,
    "message": "تم تمييز الإشعار كمقروء بنجاح."
  }
  ```

---

### 4. تحديد جميع الإشعارات كمقروءة (Mark All as Read)
عند ضغط خيار "تحديد الكل كمقروء".
- **Endpoint**: `POST /api/notifications/read-all`
- **Response Success (200 OK)**:
  ```json
  {
    "status": true,
    "message": "تم تمييز جميع الإشعارات كمقروءة بنجاح."
  }
  ```

---

### 5. حذف إشعار (Delete Notification)
- **Endpoint**: `DELETE /api/notifications/{id}`
- **Response Success (200 OK)**:
  ```json
  {
    "status": true,
    "message": "تم حذف الإشعار بنجاح."
  }
  ```

---

### 6. عقد حمولة الـ FCM Data Payload (Push Data Contract)

عند وصول push فعلي (foreground/background/terminated)، تصل بيانات الحدث ضمن حقل `data` الخاص بـ FCM. **كل القيم Strings حصراً** (متطلب Firebase نفسه). لاحظ أن اسم حقل نص الرسالة هنا هو `body` وليس `message` (تسمية FCM القياسية)، بينما بقية الحقول مطابقة تماماً لما تراه في `/api/notifications`:

```json
{
  "id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "type": "trip_started",
  "title": "بدء الرحلة 🚌",
  "body": "انطلقت الرحلة رقم #101، السائق في الطريق الآن.",
  "entity_type": "trip",
  "entity_id": "101",
  "screen": "TRIP_DETAILS",
  "action": "open_trip",
  "payload": "{\"type\":\"trip_started\",\"entity_type\":\"trip\",\"entity_id\":\"101\",\"screen\":\"TRIP_DETAILS\",\"action\":\"open_trip\"}"
}
```
> ملاحظة: `payload` هنا نص JSON مُرمّز (string) وليس كائناً متداخلاً — فكّه بـ `jsonDecode()` قبل الاستخدام. هذا الإشعار نفسه، بنفس `id`، موجود أيضاً في جدول قاعدة البيانات ويظهر عبر `GET /api/notifications` بنفس المعرّف — استخدم `id` للمطابقة بين النسختين (مثلاً لتفادي عرض تنبيه مكرر إن وصل الـ push والتطبيق يعرض القائمة في نفس اللحظة).

---

## 🛠️ 3. واجهات إشعارات لوحة تحكم الأدمن (Admin Panel Notifications)

نفس بنية إشعارات المستخدم تماماً، لكن مقيّدة بحسابات الإدارة فقط (`role_id` ضمن admin/supervisor). تُستخدم لأحداث مثل تسجيل سائق جديد أو تقديم شكوى جديدة.

| الإجراء | Endpoint |
| :--- | :--- |
| قائمة إشعارات الأدمن | `GET /api/admin/notifications` |
| عدد غير المقروء | `GET /api/admin/notifications/unread-count` |
| تحديد كمقروء | `PATCH /api/admin/notifications/{id}/read` |
| تحديد الكل كمقروء | `POST /api/admin/notifications/read-all` |
| حذف إشعار | `DELETE /api/admin/notifications/{id}` |

بنية الاستجابة مطابقة تماماً لواجهات `/api/notifications` أعلاه. حساب من غير صلاحيات الإدارة يحصل على استجابة:
```json
{
  "status": false,
  "error_code": "FORBIDDEN",
  "message": "هذا المسار متاح فقط لحسابات الإدارة."
}
```
مع كود حالة **403**.

---

## 🎯 4. التوجيه المباشر وشاشات التطبيق (Deep Linking & Navigation Matrix)

عند استلام إشعار FCM (Push) أو الضغط على إشعار في القائمة، استخدم قيمة `screen` و `entity_id` للتوجيه الشاشات المعنية في التطبيق:

| رمز الشاشة `screen` | اسم الشاشة في التطبيق | المعرف المرتبط `entity_id` | الوصف والهدف |
| :--- | :--- | :--- | :--- |
| `TRIP_LIVE` | شاشة التتبع الحي للمركبة | - | فتح الخريطة الحية لرصد صعود/نزول الطلاب |
| `TRIP_DETAILS` | تفاصيل الرحلة | `trip_id` | فتح صفحة تفاصيل رحلة معينة |
| `TRIP_TRACKING` | تتبع وصول السائق | `trip_id` | تتبع مكان الحافلة قبل الصعود |
| `TRIP_SUMMARY` | ملخص ختام الرحلة | `trip_id` | عرض ملخص وتقييم الرحلة بعد اكتمالها |
| `ATTENDANCE_LOG` | سجل الحضور والغياب | - | مراجعة سجل غياب الطالب |
| `CONTRACT_DETAILS` | تفاصيل العقد | `contract_number` | فتح العقد للتوقيع أو المراجعة |
| `SUBSCRIPTION_DETAILS` | تفاصيل طلب الاشتراك | `request_id` | مراجعة حالة طلب الاشتراك |
| `PAYMENT_SCREEN` | شاشة الدفع والسداد | `request_id` | فتح بوابة سداد الاشتراك |
| `WALLET` | المحفظة المالية | - | عرض رصيد المحفظة ومعاملات الشحن والسحب |
| `INVOICE_DETAILS` | عرض الفاتورة | `invoice_number` | فتح PDF الفاتورة أو تفاصيلها |
| `DRIVER_HOME` | الرئيسية للسائق | - | الانتقال للرئيسية بعد قبول الحساب |
| `DRIVER_PROFILE` | الملف الشخصي للسائق | - | تعديل المستندات المطلوبة |
| `HOME` | الرئيسية | - | الشاشة الرئيسية الافتراضية |

---

## 💻 5. مثال كود توضيحي بـ Flutter (Dart)

```dart
// 1. تسجيل FCM Token لدى الباك إند
// deviceId يجب أن يكون معرّفاً ثابتاً لنفس الجهاز (مثال: مكتبة device_info_plus)
Future<void> sendDeviceTokenToBackend(String fcmToken, String deviceId) async {
  final response = await http.post(
    Uri.parse('https://your-domain.com/api/user/device-token'),
    headers: {
      'Authorization': 'Bearer $userAuthToken',
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: jsonEncode({
      'fcm_token': fcmToken,
      'device_id': deviceId, // مطلوب
      'device_name': Platform.isIOS ? 'iPhone' : 'Android Phone',
      'platform': Platform.isIOS ? 'ios' : 'android',
    }),
  );
  print('Token Register Status: ${response.statusCode}');
}

// استدعِ sendDeviceTokenToBackend عند تسجيل الدخول، وأيضاً داخل
// FirebaseMessaging.instance.onTokenRefresh.listen((newToken) { ... })
// حتى يبقى الباك إند محدّثاً بأحدث توكن لهذا الجهاز.

// 2. التعامل مع النقر على الإشعار للتوجيه (Navigation Handler)
void handleNotificationTap(Map<String, dynamic> data) {
  final String? screen = data['screen'];
  final String? entityId = data['entity_id'];

  switch (screen) {
    case 'TRIP_LIVE':
      Navigator.pushNamed(context, '/trip-live');
      break;
    case 'TRIP_DETAILS':
      Navigator.pushNamed(context, '/trip-details', arguments: entityId);
      break;
    case 'CONTRACT_DETAILS':
      Navigator.pushNamed(context, '/contract-details', arguments: entityId);
      break;
    case 'WALLET':
      Navigator.pushNamed(context, '/wallet');
      break;
    default:
      Navigator.pushNamed(context, '/home');
      break;
  }
}
```
