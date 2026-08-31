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
| 8 | خطوات ومراحل تقدم الطفل في الرحلة (Child Progress) | `GET` | `/api/parent/trips/{tripId}/children/{childId}/progress` |

---

## 📑 التفاصيل الكاملة وبيانات الإدخال والإخراج (JSON Payloads)

### 1️⃣ جلب الرحلات الجارية حالياً (Get Active Trips)
تستخدم في الشاشة الرئيسية لولي الأمر لمتابعة حالة الحافلة والأطفال في الرحلات القائمة الآن.

* **Method:** `GET`
* **URL:** `http://localhost:8000/api/parent/trips/active`
* **Query Parameters (اختياري):**
  - `date` (string, صيغة `YYYY-MM-DD` مثال: `2026-08-30`) - لتمرير التاريخ المحلي من فلاتر وتجنب تباين التوقيت، وفي حال عدم إرساله يتم اعتماد تاريخ اليوم تلقائياً.

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "data": [
    {
      "trip_id": 24,
      "trip_type": "Morning",
      "direction": "to_school",
      "status": "started",
      "started_at": "2026-08-01T07:05:00.000000Z",
      "driver": {
        "id": 36,
        "name": "عبد السلام المصراتي",
        "phone": "0921111111",
        "photo": "http://localhost:8000/storage/avatars/driver.jpg"
      },
      "vehicle": {
        "info": "تويوتا كوستر 2022"
      },
      "children": [
        {
          "child_id": 49,
          "child_name": "سند طه القمودي",
          "child_photo": "http://localhost:8000/assets/images/default-child.png",
          "child_status": "waiting",
          "destination": {
            "name": "مدرسة الجيل الجديد الدولية",
            "type": "school",
            "lat": 32.890000,
            "lng": 13.180000
          }
        },
        {
          "child_id": 50,
          "child_name": "مروة طه القمودي",
          "child_photo": "http://localhost:8000/assets/images/default-child.png",
          "child_status": "picked_up",
          "destination": {
            "name": "مدرسة الجيل الجديد الدولية",
            "type": "school",
            "lat": 32.890000,
            "lng": 13.180000
          }
        }
      ],
      "destination": {
        "name": "مدرسة الجيل الجديد الدولية",
        "type": "school",
        "lat": 32.890000,
        "lng": 13.180000
      }
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
* **Query Parameters (اختياري):**
  - `date` (string, صيغة `YYYY-MM-DD` مثال: `2026-08-30`) - لتمرير التاريخ المحلي من فلاتر وتجنب تباين التوقيت، وفي حال عدم إرساله يتم اعتماد تاريخ اليوم تلقائياً.

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "data": [
    {
      "trip_id": 204,
      "trip_type": "afternoon",
      "title": "رحلة العودة من المدرسة",
      "scheduled_for": "2026-08-30 01:30 PM",
      "total_children": 2,
      "driver": {
        "name": "محمود علي"
      },
      "children": [
        {
          "child_id": 12,
          "child_name": "أحمد محمد",
          "school_name": "مدرسة المستقبل الواعد",
          "child_photo": "http://localhost:8000/assets/images/default-child.png",
          "cost_per_child": "9.00",
          "home_location": {
            "title": "المنزل - حي الأندلس",
            "lat": 32.875200,
            "lng": 13.165400
          },
          "school_location": {
            "id": 5,
            "name": "مدرسة المستقبل الواعد",
            "address": "طرابلس - حي الأندلس",
            "lat": 32.887000,
            "lng": 13.189000
          }
        },
        {
          "child_id": 15,
          "child_name": "سارة محمد",
          "school_name": "مدرسة النخبة الدولية",
          "child_photo": "http://localhost:8000/assets/images/default-child.png",
          "cost_per_child": "5.40",
          "home_location": {
            "title": "المنزل - حي الأندلس",
            "lat": 32.875200,
            "lng": 13.165400
          },
          "school_location": {
            "id": 6,
            "name": "مدرسة النخبة الدولية",
            "address": "طرابلس - طريق الشط",
            "lat": 32.895000,
            "lng": 13.195000
          }
        }
      ],
      "pricing": {
        "total_trip_cost": "14.40",
        "cost_per_child": "7.20",
        "currency": "LYD"
      }
    }
  ]
}
```

---

### 4️⃣ أرشيف وسجل الرحلات السابقة (Trip History Log)
تستخدم لشاشة سجل وتاريخ الرحلات مجمعة على مستوى الرحلة الواحدة مع دعم الـ Pagination (صفحات النتائج) والفلترة بالتاريخ.

* **Method:** `GET`
* **URL:** `http://localhost:8000/api/parent/trips/history`
* **Query Parameters (اختياري):**
  - `page` (int) - رقم الصفحة (الافتراضي: 1)
  - `per_page` (int) - عدد العناصر بالصفحة (الافتراضي: 15)
  - `date` (string, صيغة `YYYY-MM-DD`) - فلترة السجل بتاريخ محدد (اختياري)

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "data": {
    "current_page": 1,
    "per_page": 15,
    "total": 2,
    "data": [
      {
        "trip_id": 2,
        "trip_type": "Morning",
        "trip_date": "2026-08-28",
        "status": "in_progress",
        "driver": {
          "id": 1,
          "name": "عبد السلام يوسف المصراتي",
          "phone": "0921000001",
          "photo": "https://domain.com/assets/images/default-driver.png"
        },
        "total_children": 2,
        "action_type": "picked_up",
        "scanned_at": "2026-08-28 13:21:02",
        "children": [
          {
            "child_id": 1,
            "child_name": "سند طه القموضي",
            "child_photo": "https://domain.com/assets/images/default-child.png",
            "school_name": "مدرسة الجيل الجديد الدولية",
            "trip_cost": "3.60",
            "cost_per_child": "3.60",
            "status": "boarded",
            "pickup_time": "07:00 AM",
            "dropoff_time": "02:00 PM",
            "home_location": {
              "title": "المنزل الرئيسي - حي الأندلس خلف مركز المقارحة",
              "address": "المنزل الرئيسي - حي الأندلس خلف مركز المقارحة",
              "lat": 32.8925,
              "lng": 13.1752
            },
            "school_location": {
              "id": 1,
              "name": "مدرسة الجيل الجديد الدولية",
              "address": "حي الأندلس - بالقرب من جامع الأندلس الكبير",
              "lat": 32.892,
              "lng": 13.168
            }
          },
          {
            "child_id": 2,
            "child_name": "مروة طه القموضي",
            "child_photo": "https://domain.com/assets/images/default-child.png",
            "school_name": "مدرسة الجيل الجديد الدولية",
            "trip_cost": "6.14",
            "cost_per_child": "6.14",
            "status": "completed",
            "pickup_time": "07:00 AM",
            "dropoff_time": "01:45 PM",
            "home_location": {
              "title": "المنزل الرئيسي - حي الأندلس خلف مركز المقارحة",
              "address": "المنزل الرئيسي - حي الأندلس خلف مركز المقارحة",
              "lat": 32.8925,
              "lng": 13.1752
            },
            "school_location": {
              "id": 1,
              "name": "مدرسة الجيل الجديد الدولية",
              "address": "حي الأندلس - بالقرب من جامع الأندلس الكبير",
              "lat": 32.892,
              "lng": 13.168
            }
          }
        ],
        "pricing": {
          "total_trip_cost": "9.74",
          "cost_per_child": "4.87",
          "currency": "LYD"
        }
      }
    ]
  }
}
```

---

### 4️⃣.1️⃣ تفاصيل رحلة معينة (Get Trip Details)
تستخدم لعرض تفاصيل شاملة لرحلة معينة مع بيانات السائق والمركبة وموقع مدرسة ومنزل كل طفل.

* **Method:** `GET`
* **URL:** `http://localhost:8000/api/parent/trips/{tripId}`
* **Path Parameters:**
  - `tripId` (int, مطلوب) - مثال: `2`
* **Input (Query/Body):** لا يوجد (None)

#### 🟢 Response النجاح (200 OK):
```json
{
  "status": "success",
  "data": {
    "trip_id": 2,
    "trip_type": "Morning",
    "direction": "to_school",
    "status": "in_progress",
    "driver": {
      "id": 1,
      "name": "عبد السلام يوسف المصراتي",
      "phone": "0921000001",
      "photo": "https://domain.com/assets/images/default-driver.png"
    },
    "vehicle": {
      "info": "تويوتا كوستر Coaster (5-112233)"
    },
    "children": [
      {
        "child_id": 1,
        "child_name": "سند طه القموضي",
        "child_photo": "https://domain.com/assets/images/default-child.png",
        "child_status": "boarded",
        "school_name": "مدرسة الجيل الجديد الدولية",
        "direction": "to_school",
        "home_location": {
          "title": "المنزل الرئيسي - حي الأندلس خلف مركز المقارحة",
          "address": "المنزل الرئيسي - حي الأندلس خلف مركز المقارحة",
          "lat": 32.8925,
          "lng": 13.1752
        },
        "school_location": {
          "id": 1,
          "name": "مدرسة الجيل الجديد الدولية",
          "address": "حي الأندلس - بالقرب من جامع الأندلس الكبير",
          "lat": 32.892,
          "lng": 13.168
        },
        "destination": {
          "name": "مدرسة الجيل الجديد الدولية",
          "type": "school",
          "lat": 32.892,
          "lng": 13.168
        }
      },
      {
        "child_id": 2,
        "child_name": "مروة طه القموضي",
        "child_photo": "https://domain.com/assets/images/default-child.png",
        "child_status": "waiting",
        "school_name": "مدرسة الجيل الجديد الدولية",
        "direction": "to_school",
        "home_location": {
          "title": "المنزل الرئيسي - حي الأندلس خلف مركز المقارحة",
          "address": "المنزل الرئيسي - حي الأندلس خلف مركز المقارحة",
          "lat": 32.8925,
          "lng": 13.1752
        },
        "school_location": {
          "id": 1,
          "name": "مدرسة الجيل الجديد الدولية",
          "address": "حي الأندلس - بالقرب من جامع الأندلس الكبير",
          "lat": 32.892,
          "lng": 13.168
        },
        "destination": {
          "name": "مدرسة الجيل الجديد الدولية",
          "type": "school",
          "lat": 32.892,
          "lng": 13.168
        }
      }
    ],
    "destination": {
      "name": "مدرسة الجيل الجديد الدولية",
      "type": "school",
      "lat": 32.892,
      "lng": 13.168
    },
    "destinations": [
      {
        "name": "مدرسة الجيل الجديد الدولية",
        "type": "school",
        "lat": 32.892,
        "lng": 13.168
      }
    ],
    "is_multi_school": false,
    "started_at": "2026-08-28T13:21:02+02:00",
    "finished_at": null
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

---

### 8️⃣ فحص حالة وتأكيد تعديل البريد الإلكتروني (Parent Email Change & Status Check)
تستخدم للتحقق من حالة طلب تغيير البريد الإلكتروني أو الاستجابة عند الموافقة عبر الرابط الموقع.

* **1. فحص حالة تغيير الإيميل للفرونت إند (Email Status Check):**
  - **Method:** `GET`
  - **URL:** `http://localhost:8000/api/parent/profile/email-status`
  - **Headers:** `Authorization: Bearer <SANCTUM_TOKEN>`

  - **🟢 Response النجاح (200 OK):**
    ```json
    {
      "status": true,
      "has_pending_change": false,
      "pending_email": null,
      "email_changed": true,
      "current_email": "parent.new@darby.ly",
      "message": "تم موافقة وتحديث البريد الإلكتروني بنجاح."
    }
    ```

* **2. رابط موافقة التعديل الموقع (Signed Approval URL):**
  - **Method:** `GET`
  - **URL:** `http://localhost:8000/api/parent/profile/email/approve/{id}?signature=...`
  - **🟢 Response النجاح (200 OK):**
    ```json
    {
      "status": true,
      "email_changed": true,
      "message": "تم تأكيد وتحديث البريد الإلكتروني بنجاح."
    }
    ```
