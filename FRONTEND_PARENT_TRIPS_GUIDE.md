# 🚌 دليل ربط إيندبوينت رحلات ولي الأمر (Parent Trips API Documentation)

هذا الملف مخصص لفريق الـ Frontend (Flutter / Mobile / Web) لربط كافة الاندبوينتس الخاصة بـ **رحلات ولي الأمر ومتابعة الأطفال** في تطبيق **Darby**.

---

## 🔒 متطلبات التوثيق والهيدرز العامة (General Headers)
جميع المسارات أدناه محمية عبر **Sanctum Middleware** وتتطلب إرسال التوكن:

```http
Authorization: Bearer {your_sanctum_token}
Accept: application/json
Content-Type: application/json
```

---

## 📍 قائمة الاندبوينتس (Endpoints Overview)

| # | اسم الوظيفة | HTTP Method | URL Path |
|---|---|---|---|
| 1 | جلب الرحلات المفعلة/الجارية حالياً | `GET` | `/api/parent/trips/active` |
| 2 | التتبع اللحظي لموقع الحافلة | `GET` | `/api/parent/trips/{tripId}/track` |
| 3 | جلب الرحلات القادمة للأطفال | `GET` | `/api/parent/trips/upcoming` |
| 4 | أرشيف وسجل جميع الرحلات السابقة | `GET` | `/api/parent/trips/history` |
| 5 | جدولة وتسجيل غياب طفل | `POST` | `/api/parent/children/{childId}/set-absence` |
| 6 | إلغاء غياب طفل | `POST` | `/api/parent/children/{childId}/cancel-absence` |
| 7 | تأكيد صعود الطفل يدوياً (بديل QR) | `POST` | `/api/parent/children/{childId}/confirm-pickup/{tripId}` |

---

## 📑 التفاصيل الكاملة وبيانات الإدخال والإخراج (JSON Payloads)

### 1️⃣ جلب الرحلات الجارية حالياً (Get Active Trips)
تستخدم في الشاشة الرئيسية لولي الأمر لمتابعة حالة الحافلة والأطفال في الرحلات القائمة الآن.

* **Method:** `GET`
* **URL:** `http://localhost:8000/api/parent/trips/active`
* **Input (Query/Body):** لا يوجد (None)

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "data": [
    {
      "trip_id": 24,
      "trip_type": "Morning",
      "status": "started",
      "driver_name": "عبد السلام المصراتي",
      "driver_phone": "0921111111",
      "vehicle_info": "حافلة مدرسية",
      "child_id": 49,
      "child_name": "سند طه القمودي",
      "child_status": "waiting",
      "waiting_timer": null,
      "started_at": "2026-07-27T17:00:06.000000Z"
    },
    {
      "trip_id": 24,
      "trip_type": "Morning",
      "status": "started",
      "driver_name": "عبد السلام المصراتي",
      "driver_phone": "0921111111",
      "vehicle_info": "حافلة مدرسية",
      "child_id": 50,
      "child_name": "مروة طه القمودي",
      "child_status": "picked_up",
      "waiting_timer": null,
      "started_at": "2026-07-27T17:00:06.000000Z"
    }
  ]
}
```

> **ملاحظة للفرونت:** 
> - `child_status` الممكنة: `waiting` (ينتظر الحافلة)، `picked_up` (صعد الحافلة)، `dropped_off` (تم التوصيل بنجاح)، `skipped` (تم تخطي المحطة)، `absent` (غائب اليوم).
> - `waiting_timer`: يحتوي بيانات عداد الانتظار عند وصول السائق أمام منزل الطفل.

---

### 2️⃣ التتبع اللحظي لرحلة معينة (Live Tracking)
تستخدم لشاشة الخريطة المباشرة للتتبع الحي لموقع الحافلة أثناء الرحلة.

* **Method:** `GET`
* **URL:** `http://localhost:8000/api/parent/trips/{tripId}/track`
* **Path Parameters:**
  - `tripId` (int, مطلوب) - مثال: `24`
* **Input (Query/Body):** لا يوجد (None)

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "data": {
    "trip_id": 24,
    "status": "started",
    "driver_lat": 32.89000000,
    "driver_lng": 13.18000000,
    "last_updated": "2026-07-27T19:01:10+02:00"
  }
}
```

---

### 3️⃣ جلب الرحلات القادمة (Get Upcoming Trips)
تستخدم لعرض جدول الرحلات القادمة اليوم (رحلة الذهاب صباحاً أو رحلة العودة ظهراً).

* **Method:** `GET`
* **URL:** `http://localhost:8000/api/parent/trips/upcoming`
* **Input (Query/Body):** لا يوجد (None)

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "data": [
    {
      "child_name": "سند طه القمودي",
      "trip_type": "Afternoon",
      "title": "رحلة العودة للمنزل",
      "scheduled_for": "اليوم ظهراً",
      "driver_name": "عبد السلام المصراتي",
      "school_name": "مدرسة الجيل الجديد الدولية"
    },
    {
      "child_name": "مروة طه القمودي",
      "trip_type": "Afternoon",
      "title": "رحلة العودة للمنزل",
      "scheduled_for": "اليوم ظهراً",
      "driver_name": "عبد السلام المصراتي",
      "school_name": "مدرسة الجيل الجديد الدولية"
    }
  ]
}
```

---

### 4️⃣ أرشيف وسجل الرحلات السابقة (Trip History Log)
تستخدم لشاشة سجل وتاريخ الرحلات مع دعم الـ Pagination (صفحات النتائج).

* **Method:** `GET`
* **URL:** `http://localhost:8000/api/parent/trips/history`
* **Query Parameters (اختياري):**
  - `page` (int) - رقم الصفحة (الافتراضي: 1)
  - `per_page` (int) - عدد العناصر بالصفحة (الافتراضي: 15)

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "data": [
      {
        "trip_id": 24,
        "trip_type": "Morning",
        "trip_date": "2026-07-27",
        "child_name": "سند طه القمودي",
        "driver_name": "عبد السلام المصراتي",
        "action_type": "picked_up",
        "scanned_at": "2026-07-27 19:00:07",
        "trip_cost": "15.00"
      },
      {
        "trip_id": 21,
        "trip_type": "Morning",
        "trip_date": "2026-07-27",
        "child_name": "سند طه القمودي",
        "driver_name": "عبد السلام المصراتي",
        "action_type": "dropped_off",
        "scanned_at": "2026-07-27 07:25:00",
        "trip_cost": "15.00"
      }
    ],
    "first_page_url": "http://localhost:8000/api/parent/trips/history?page=1",
    "from": 1,
    "last_page": 1,
    "last_page_url": "http://localhost:8000/api/parent/trips/history?page=1",
    "next_page_url": null,
    "path": "http://localhost:8000/api/parent/trips/history",
    "per_page": 15,
    "prev_page_url": null,
    "to": 2,
    "total": 2
  }
}
```

---

### 5️⃣ جدولة غياب طفل (Set Child Absence)
تستخدم عندما يريد ولي الأمر إبلاغ النظام بتغيب طفله عن الحافلة في أيام محددة.

* **Method:** `POST`
* **URL Path:** `http://localhost:8000/api/parent/children/{childId}/set-absence`  
  *(يدعم أيضاً المسار البديل: `/api/parent/children/{childId}/absence`)*
* **Path Parameters:**
  - `childId` (int, مطلوب) - مثال: `49`

#### 📥 Body (JSON):
```json
{
  "dates": [
    "2026-07-28",
    "2026-07-29"
  ]
}
```

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "message": "تم جدولة غياب الطفل بنجاح وتحديث المسارات الجارية إن وجدت"
}
```

#### 🔴 Response الخطأ (400 Bad Request):
```json
{
  "status": "error",
  "message": "عذراً، هذا الطفل غير موجود أو لا يتبع لحسابك."
}
```

---

### 6️⃣ إلغاء غياب طفل (Cancel Child Absence)
تستخدم لإلغاء غياب تم تسجيله سابقاً وإعادة الطفل للمسار التشغيلي للحافلة.

* **Method:** `POST` *(يدعم أيضاً `DELETE`)*
* **URL Path:** `http://localhost:8000/api/parent/children/{childId}/cancel-absence`  
  *(يدعم أيضاً المسار البديل: `DELETE /api/parent/children/{childId}/absence`)*
* **Path Parameters:**
  - `childId` (int, مطلوب) - مثال: `49`

#### 📥 Body (JSON):
```json
{
  "dates": [
    "2026-07-28"
  ]
}
```

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "message": "تم إلغاء الغياب وإعادة الطفل للمسارات التشغيلية"
}
```

---

### 7️⃣ تأكيد صعود الطفل يدوياً (Confirm Manual Pickup)
تستخدم من قبل ولي الأمر لتأكيد ركوب طفله الحافلة يدوياً (كإثبات أمان بديل لمسح كود الـ QR).

* **Method:** `POST`
* **URL Path:** `http://localhost:8000/api/parent/children/{childId}/confirm-pickup/{tripId}`  
  *(يدعم أيضاً المسار البديل: `/api/parent/trips/{tripId}/children/{childId}/manual-pickup`)*
* **Path Parameters:**
  - `childId` (int, مطلوب) - مثال: `49`
  - `tripId` (int, مطلوب) - مثال: `24`
* **Input Body:** لا يوجد (None)

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "message": "قمتِ بتأكيد ركوب طفلك يدوياً، تم إخطار السائق"
}
```

#### 🔴 Response الخطأ (400 Bad Request):
```json
{
  "status": "error",
  "message": "عذراً، قام السائق بتجاوز هذه المحطة بالفعل."
}
```
