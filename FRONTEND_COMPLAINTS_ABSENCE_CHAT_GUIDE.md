# دليل واجهة برمجة التطبيقات (API Documentation)
## موديولات: الشكاوى، غياب الطفل، والمحادثات (Complaints, Absence & Chat)

يقدم هذا الملف التوثيق الكامل والمحدث الخاص بفريق الفرونت-إند (Frontend Team) لربط دوال **الشكاوى (Complaints)**، **تسجيل وإلغاء غياب الطفل (Absence Management)**، و**قوائم المحادثات (Chat Rooms)** لكل من ولي الأمر، السائق، والإدارة.

---

### 🌐 الـ Headers المطلوبة في جميع الطلبات (Global Headers)

| הـ Header | القيمة | الوصف |
| :--- | :--- | :--- |
| `Accept` | `application/json` | إجباري لإخبار الخادم بإرجاع الرد بصيغة JSON. |
| `Accept-Language` | `ar` | لضمان الحصول على رسائل الخطأ والتأكيدات باللغة العربية. |
| `Authorization` | `Bearer {TOKEN}` | إجباري في جميع المسارات المحمية بعد تسجيل الدخول. |
| `Content-Type` | `application/json` | إجباري عند إرسال بيانات بالـ Body بصيغة JSON. |

---

## 1️⃣ موديول الشكاوى (Complaints Module)

### أ. مسارات ولي الأمر (Parent Endpoints)

#### **1. تقديم شكوى جديدة ضد سائق**
* **المسار:** `POST /api/parent/complaints`
* **الوصف:** يتيح لولي الأمر تقديم شكوى جديدة ضد سائق مشترك معه.
* **بيانات الإدخال (Request Body):**
```json
{
  "driver_id": 12,
  "trip_id": 5,
  "description": "السائق تأخر عن موعد وصول الطفل للمدرسة وقام بالسير بسرعة عالية"
}
```
* **قواعد التحقق (Validation Rules):**
  * `driver_id` *(integer, إجباري)*: المعرف الخاص بالسائق (يجب أن يكون لولي الأمر اشتراك مع هذا السائق).
  * `trip_id` *(integer, اختياري/nullable)*: معرف الرحلة المرتبطة بالشكوى إن وجدت.
  * `description` *(string, إجباري)*: نص ووصف الشكوى (لا يقل عن 10 أحرف ولا يزيد عن 5000 حرف).
* **مثال الرد الناجح (201 Created):**
```json
{
  "success": true,
  "message": "تم تقديم الشكوى بنجاح، بانتظار مراجعة الإدارة.",
  "data": {
    "id": 1,
    "description": "السائق تأخر عن موعد وصول الطفل للمدرسة وقام بالسير بسرعة عالية",
    "status": "pending",
    "action_taken": "none",
    "action_details": null,
    "created_at": "2026-07-26 23:45:00",
    "resolved_at": null,
    "driver": {
      "id": 12,
      "name": "محمد الفيتوري"
    },
    "trip": {
      "id": 5,
      "trip_date": "2026-07-26",
      "trip_type": "Morning",
      "status": "completed"
    },
    "resolved_by": null
  }
}
```

---

#### **2. عرض قائمة شكاوى ولي الأمر**
* **المسار:** `GET /api/parent/complaints`
* **محددات الاستعلام (Query Parameters - اختياري):**
  * `type`: `"pending"` (عرض الشكاوى قيد الانتظار) أو `"resolved"` (عرض الشكاوى المعالجة).
  * `status`: `"pending"`, `"completed"`, `"dismissed"`.
* **مثال الرد الناجح (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "description": "السائق تأخر عن موعد وصول الطفل...",
      "status": "pending",
      "action_taken": "none",
      "action_details": null,
      "created_at": "2026-07-26 23:45:00",
      "resolved_at": null,
      "driver": {
        "id": 12,
        "name": "محمد الفيتوري"
      },
      "trip": null,
      "resolved_by": null
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "total": 1,
    "per_page": 15
  }
}
```

---

#### **3. عرض تفاصيل شكوى واحدة**
* **المسار:** `GET /api/parent/complaints/{id}`
* **مثال الرد الناجح (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "description": "السائق تأخر عن موعد وصول الطفل...",
    "status": "pending",
    "action_taken": "none",
    "action_details": null,
    "created_at": "2026-07-26 23:45:00",
    "resolved_at": null,
    "driver": {
      "id": 12,
      "name": "محمد الفيتوري"
    },
    "trip": null,
    "resolved_by": null
  }
}
```

---

#### **4. تعديل شكوى معلقة**
* **المسار:** `POST /api/parent/complaints/{id}`
* **بيانات الإدخال (Request Body):**
```json
{
  "description": "تعديل نص الشكوى: السائق تأخر لمدة 45 دقيقة كاملة وقام بالسير بسرعة عالية"
}
```
* **مثال الرد الناجح (200 OK):**
```json
{
  "success": true,
  "message": "تم تحديث الشكوى بنجاح.",
  "data": {
    "id": 1,
    "description": "تعديل نص الشكوى: السائق تأخر لمدة 45 دقيقة كاملة...",
    "status": "pending"
  }
}
```

---

#### **5. حذف شكوى معلقة**
* **المسار:** `DELETE /api/parent/complaints/{id}`
* **مثال الرد الناجح (200 OK):**
```json
{
  "success": true,
  "message": "تم حذف الشكوى بنجاح."
}
```

---

#### **6. جلب رحلات السائق المتاحة لربطها بالشكوى**
* **المسار:** `GET /api/parent/driver/{driverId}/trips`
* **مثال الرد الناجح (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "driver_id": 12,
      "trip_type": "Morning",
      "status": "completed",
      "trip_date": "2026-07-26",
      "created_at": "2026-07-26 07:00:00"
    }
  ]
}
```

---

### ب. مسارات الإدارة والأدمن (Admin Endpoints)

#### **1. عرض جميع الشكاوى في لوحة الأدمن**
* **المسار:** `GET /api/admin/complaints`
* **محددات الاستعلام (Query Parameters):** `status` (`pending`, `completed`, `dismissed`), `driver_id` (تصفية بسائق).

#### **2. عرض تفاصيل شكوى للأدمن**
* **المسار:** `GET /api/admin/complaints/{id}`

#### **3. جلب سجل الشكاوى الخاص بسائق معين**
* **المسار:** `GET /api/admin/complaints/driver/{driverId}`

#### **4. اتخاذ قرار ومعالجة الشكوى (Review & Action)**
* **المسار:** `POST /api/admin/complaints/{id}/review`
* **بيانات الإدخال (Request Body):**
```json
{
  "action": "warning", 
  "action_details": "تم توجيه إنذار رسمي للسائق والتنبيه عليه بالالتزام بالمواعيد"
}
```
* **خيارات حقل `action` الإجبارية:**
  * `"warning"`: إنذار السائق وحفظ القرار.
  * `"suspension"`: إيقاف حساب السائق فوراًَ وحفظ القرار.
  * `"dismiss"`: تجاهل وإغلاق الشكوى.
* **مثال الرد الناجح (200 OK):**
```json
{
  "status": true,
  "message": "تم إرسال إنذار للسائق وحفظ القرار.",
  "data": {
    "id": 1,
    "description": "السائق تأخر عن موعد وصول الطفل...",
    "status": "completed",
    "action_taken": "warning",
    "action_details": "تم توجيه إنذار رسمي للسائق والتنبيه عليه بالالتزام بالمواعيد",
    "resolved_at": "2026-07-26 23:50:00",
    "resolved_by": {
      "id": 1,
      "name": "مدير النظام"
    }
  }
}
```

---

## 2️⃣ موديول غياب الطفل وإلغائه (Child Absence Management)

#### **1. تسجيل وجدولة غياب طفل**
* **المسار المفضل:** `POST /api/parent/children/{childId}/set-absence`  
*(أو المسار البديل: `POST /api/parent/children/{childId}/absence`)*
* **بيانات الإدخال (Request Body):**
```json
{
  "dates": [
    "2026-07-27",
    "2026-07-28"
  ]
}
```
* **قواعد التحقق (Validation):**
  * `dates` *(array, إجباري, min: 1)*: مصفوفة تحتوي على تاريخ واحد على الأقل.
  * `dates.*` *(date, إجباري)*: يجب أن يكون التاريخ اليوم أو في المستقبل (`after_or_equal:today`).
* **مثال الرد الناجح (200 OK):**
```json
{
  "status": "success",
  "message": "تم جدولة غياب الطفل بنجاح وتحديث المسارات الجارية إن وجدت"
}
```
* **مثال رد الخطأ (عند محاولة إضافة غياب لطفل لا يخص الحساب أو تاريخ قديم - 400 Bad Request):**
```json
{
  "status": "error",
  "message": "عذراً، هذا الطفل غير موجود أو لا يتبع لحسابك."
}
```

---

#### **2. إلغاء غياب مجدول لطفل**
* **المسار المفضل:** `POST /api/parent/children/{childId}/cancel-absence`  
*(أو المسار البديل: `DELETE /api/parent/children/{childId}/absence`)*
* **بيانات الإدخال (Request Body):**
```json
{
  "dates": [
    "2026-07-27"
  ]
}
```
* **مثال الرد الناجح (200 OK):**
```json
{
  "status": "success",
  "message": "تم إلغاء الغياب وإعادة الطفل للمسارات التشغيلية"
}
```

---

## 3️⃣ موديول المحادثات وغرف الشات (Chat Rooms Module)

#### **1. جلب قائمة المحادثات لولي الأمر**
* **المسار:** `GET /api/parent/chats`
* **الوصف:** يرجع قائمة السائقين المشترك معهم ولي الأمر مع حالة الاشتراك وإمكانية فتح المحادثة.
* **بيانات الإدخال:** لا يوجد.
* **مثال الرد الناجح (200 OK):**
```json
{
  "success": true,
  "message": "تم جلب قائمة المحادثات بنجاح.",
  "data": [
    {
      "chat_room_id": "parent_8_driver_12",
      "driver_id": 12,
      "driver_user_id": 29,
      "driver_name": "محمد الفيتوري السائق",
      "driver_phone": "0921234567",
      "driver_photo": "https://example.com/storage/avatars/driver.jpg",
      "can_chat": true,
      "subscription_status": "active"
    }
  ]
}
```

---

#### **2. جلب قائمة المحادثات للسائق**
* **المسار:** `GET /api/driver/chats`
* **الوصف:** يرجع قائمة أولياء الأمور المشتركين مع السائق مع حالة الاشتراك وإمكانية المحادثة.
* **بيانات الإدخال:** لا يوجد.
* **مثال الرد الناجح (200 OK):**
```json
{
  "success": true,
  "message": "تم جلب قائمة المحادثات بنجاح.",
  "data": [
    {
      "chat_room_id": "parent_8_driver_12",
      "parent_id": 8,
      "parent_user_id": 28,
      "parent_name": "أحمد علي ولي الأمر",
      "parent_phone": "0911234567",
      "parent_photo": "https://example.com/storage/avatars/parent.jpg",
      "can_chat": true,
      "subscription_status": "active"
    }
  ]
}
```
