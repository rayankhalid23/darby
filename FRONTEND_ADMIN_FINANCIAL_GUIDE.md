# 💰 دليل ومواصفات مركز الإدارة المالية الموحد للآدمن (Admin Unified Financial Hub)

يقدم هذا الملف المواصفات المعمارية والبرمجية الكاملة لتصميم وبناء **واجهة مالية مركزية موحدة (Single Page Financial Control Center)** في لوحة تحكم مدير النظام (Admin Dashboard)، مع التوثيق الشامل لجميع الـ Endpoints، ونماذج البيانات (Request/Response)، وقواعد تجربة المستخدم (UX/UI)، وإدارة الحالة (State Management).

---

## 🧭 رؤية الواجهة الموحدة (Single-Page UX Vision)
الهدف هو تصميم **شاشة مالية واحدة وشاملة** مريحة للعين، تعتمد على التبويبات العلوية السلسة (**Tabs**)، البطاقات الإحصائية الواضحة (**KPI Cards**)، النوافذ المنبثقة التفاعلية للمعاينة (**Preview Modals**)، والتحديث اللحظي للبيانات دون الحاجة لإعادة تحميل الصفحة.

### الهيكل البصري للصفحة (Page Layout Architecture)
1. **الشريط العلوي (Header & Action Bar):**
   - عنوان الصفحة مع مؤشر الاتساق المالي (**Solvency Health Badge: 100% متسق**).
   - زر التحديث الفوري (Refresh Data).
   - زر الفحص السريع للسلامة المالية (Trigger Solvency Check).
   - زر التحرير التلقائي للأمانات الجاهزة (Release Escrows).
   - زر تصدير التقارير (Export CSV / PDF).
2. **شريط مؤشرات الأداء المالي (KPI Metric Cards Bar):**
   - إجمالي أمانات أولياء الأمور (`parents_escrow_pool`).
   - أرباح السائقين المعلقة (`driver_pending_pool`).
   - أرباح السائقين المتاحة للسحب (`driver_available_pool`).
   - إيرادات وعمولات المنصة المحققة (`platform_revenue_pool`).
   - صندوق الغرامات والتعويضات (`penalty_pool`).
3. **شريط التبويبات الموحد (Unified Tabs Bar):**
   - **التبويب 1: طلبات السحب (Withdrawals)** [مع شارة Badge بعدد الطلبات المعلقة].
   - **التبويب 2: شحنات السائقين (Driver Recharges)** [مع شارة Badge بالمعلق].
   - **التبويب 3: شحنات أولياء الأمور (Parent Recharges)**.
   - **التبويب 4: النزاعات والاعتراضات (Disputes & Holds)** [مع شارة Badge بالنزاعات المفتوحة].
   - **التبويب 5: تسويات العقود والإلغاء (Contracts & Settlements)**.
   - **التبويب 6: السجل المالي العام (Immutable Financial Ledger)**.
   - **التبويب 7: إعدادات التسعير وطرق الدفع (Pricing & Payment Methods)**.

---

## 📌 الإعدادات العامة للربط (API Base Configuration)
- **Base URL:** `https://your-domain.com/api` (أو `http://127.0.0.1:8000/api`)
- **Headers الإلزامية:**
```http
Authorization: Bearer <ADMIN_SANCTUM_TOKEN>
Accept: application/json
Content-Type: application/json
```

---

## 📑 تفاصيل التبويبات والدوال البرمجية (Tabs & Functional Endpoints)

---

### 1️⃣ الملخص وشريط المؤشرات المالية (Dashboard KPIs & Solvency)

#### 🔹 دالة جلب ملخص الأرصدة والعدادات
- **الرابط:** `GET /admin/financial/summary`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "parents_escrow_pool": 12500.00,
    "driver_pending_pool": 3400.00,
    "driver_available_pool": 8900.00,
    "platform_revenue_pool": 1500.00,
    "penalty_pool": 120.00,
    "pending_withdrawals_count": 3,
    "pending_recharges_count": 2,
    "pending_disputes_count": 1,
    "pending_escrows_count": 5
  }
}
```

#### 🔹 دالة فحص السلامة المالية (Daily Solvency Check)
- **الرابط:** `GET /admin/financial/solvency-check`
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "النظام متسق مالياً بنسبة 100%.",
  "data": {
    "is_solvent": true,
    "vault_total": 26420.00,
    "calculated_total": 26420.00,
    "discrepancy": 0.00
  }
}
```

#### 🔹 دالة تحرير أمانات الرحلات المكتملة بعد 24 ساعة (Release Escrows)
- **الرابط:** `POST /admin/financial/release-escrows`
- **Body:** `{}`
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "تم تحويل أرباح 4 رحلة مكتملة إلى رصيد السائقين المتاح.",
  "data": { "released_count": 4 }
}
```

---

### 2️⃣ التبويب الأول: طلبات سحب السائقين (Driver Withdrawals)

#### 🔹 جلب قائمة طلبات السحب
- **الرابط:** `GET /admin/financial/withdrawals?status=pending&page=1&per_page=15`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "driver_id": 3,
      "driver": { "user": { "full_name": "سالم التاورغي", "phone_number": "0912345678" } },
      "amount": 250.00,
      "wallet_balance_at_request": 300.00,
      "status": "pending",
      "payment_method_details": {
        "bank_name": "مصرف الجمهورية",
        "account_number": "123456789",
        "account_name": "سالم التاورغي"
      },
      "created_at": "2026-08-29T10:00:00.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1 }
}
```

#### 🔹 تفاصيل طلب سحب محدد
- **الرابط:** `GET /admin/financial/withdrawals/{id}`

#### 🔹 معالجة طلب السحب (قبول / رفض مسبب)
- **الرابط:** `POST /admin/financial/withdrawals/{id}/process`
- **Body للموافقة:**
```json
{
  "action": "approve"
}
```
- **Body للرفض:**
```json
{
  "action": "reject",
  "rejection_reason": "رقم الحساب المصرفي لا يطابق اسم صاحب الحساب المسجل لدينا."
}
```

---

### 3️⃣ التبويب الثاني: طلبات شحن السائقين (Driver Recharges)

#### 🔹 جلب طلبات شحن السائقين
- **الرابط:** `GET /admin/driver-recharges?status=pending`
- **Response (200 OK):**
```json
{
  "status": true,
  "data": [
    {
      "id": 10,
      "driver_id": 2,
      "amount": 100.00,
      "status": "pending",
      "reference_number": "DEP-987654",
      "proof_image_url": "http://domain.com/storage/recharge_proofs/receipt.jpg",
      "notes": "تحويل عبر تطبيق مصرفي",
      "created_at": "2026-08-29T09:00:00.000000Z"
    }
  ],
  "pagination": { "current_page": 1, "last_page": 1, "total": 1, "per_page": 15 }
}
```

#### 🔹 الموافقة على شحن السائق
- **الرابط:** `POST /admin/driver-recharges/{id}/approve`
- **Body:** `{ "notes": "تم التأكد من قيد المبلغ في كشف الحساب." }`

#### 🔹 رفض طلب شحن السائق
- **الرابط:** `POST /admin/driver-recharges/{id}/reject`
- **Body:** `{ "rejection_reason": "صورة الإيصال غير واضحة ورقم المعاملة غير صالح." }`

---

### 4️⃣ التبويب الثالث: طلبات شحن أولياء الأمور (Parent Recharges)

#### 🔹 قائمة طلبات شحن أولياء الأمور
- **الرابط:** `GET /admin/financial/recharges?status=pending`
- **تفاصيل الشحنة:** `GET /admin/financial/recharges/{id}`
- **معالجة طلب الشحن (تأكيد يدوي أو رفض):** `POST /admin/financial/recharges/{id}/process`
  - **Body للتأكيد:** `{ "action": "complete" }`
  - **Body للرفض:** `{ "action": "fail", "reason": "فشل التحقق من السداد." }`

---

### 5️⃣ التبويب الرابع: النزاعات والاعتراضات المالية (Trip Disputes)

#### 🔹 جلب قائمة النزاعات
- **الرابط:** `GET /admin/financial/disputes?status=open`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "trip_id": 105,
      "parent": { "id": 4, "user": { "full_name": "أحمد محمود", "phone_number": "0912345678" } },
      "driver": { "id": 2, "user": { "full_name": "طارق علي", "phone_number": "0923456789" } },
      "amount": 25.00,
      "reason": "السائق لم يوصل الطالب إلى المدرسة في الموعد",
      "status": "open",
      "created_at": "2026-08-29T08:30:00.000000Z"
    }
  ]
}
```

#### 🔹 تفاصيل النزاع
- **الرابط:** `GET /admin/financial/disputes/{id}`

#### 🔹 حل النزاع المالي (Resolution Modal)
- **الرابط:** `POST /admin/financial/disputes/{disputeId}/resolve`
- **Body (إعادة المبلغ لولي الأمر):**
```json
{
  "resolution": "resolve_parent_refunded",
  "notes": "تم فحص مسار الرحلة وتأكيد تأخر السائق وعدم استكمال الرحلة."
}
```
- **Body (صرف المبلغ للسائق):**
```json
{
  "resolution": "resolve_driver_paid",
  "notes": "تبين صعود الطالب ووصوله بنجاح وفق بصمة الـ QR."
}
```

---

### 6️⃣ التبويب الخامس: تسويات العقود والاشتراكات الشهرية والإلغاء (Contracts & Settlements)

#### 🔹 قائمة العقود الجاهزة للتسوية
- **الرابط:** `GET /admin/financial/contracts/pending-settlements`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "contract_id": 15,
      "contract_number": "REQ-15",
      "parent": "علي بن مصطفى",
      "driver": "سالم التاورغي",
      "total_amount": 400.00,
      "executed_amount": 400.00,
      "pending_amount": 0.00,
      "completed_trips": 20,
      "settlement_status": "pending_settlement"
    }
  ]
}
```

#### 🔹 تنفيذ التسوية الشهرية الإغلاقية
- **الرابط:** `POST /admin/financial/contracts/{contractId}/settle-monthly`
- **Body:** `{}`

#### 🔹 معاينة حسابات الإلغاء المبكر (Preview Termination Modal)
- **الرابط:** `GET /admin/financial/contracts/{contractId}/termination-preview?terminated_by=parent&is_arbitrary_parent=true`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "total_contract_value": 400.00,
    "completed_trips_cost": 100.00,
    "remaining_escrow": 300.00,
    "penalty_fee": 30.00,
    "refund_to_parent": 270.00,
    "payout_to_driver": 100.00
  }
}
```

#### 🔹 تنفيذ الإلغاء المبكر للاشتراك
- **الرابط:** `POST /admin/financial/contracts/{contractId}/terminate-mid-month`
- **Body:**
```json
{
  "terminated_by": "parent",
  "is_arbitrary_parent": true
}
```

#### 🔹 معاينة وإلغاء رحلة مفردة بجدول الغرامات (Trip Cancellation Matrix)
- **معاينة:** `GET /admin/financial/trips/{tripId}/cancel-preview?cancelled_by=driver`
- **تنفيذ الإلغاء:** `POST /admin/financial/trips/{tripId}/cancel-with-matrix`
  - **Body:** `{ "cancelled_by": "driver" }` *(أو parent أو no_show)*

---

### 7️⃣ التبويب السادس: السجل المالي العام (Immutable Financial Ledger)

#### 🔹 سجل المعاملات المالية المفلتر
- **الرابط:** `GET /admin/financial/ledger?page=1&per_page=20&type=trip_payment&search=TXN-123&date_from=2026-08-01&date_to=2026-08-31`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "reference_number": "TXN-65A1B2C3",
      "type": "trip_payment",
      "amount": 25.00,
      "source_account": "parent_escrow:12",
      "destination_account": "driver_pending:5",
      "status": "completed",
      "created_at": "2026-08-29T12:00:00.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 5, "total": 95, "per_page": 20 }
}
```

---

### 8️⃣ التبويب السابع: إعدادات التسعير وطرق الدفع (Settings & Payment Methods)

#### 🔹 إدارة إعدادات التسعير وعمولة المنصة
- **جلب الإعدادات:** `GET /admin/financial/pricing-settings`
- **تحديث الإعدادات:** `POST` أو `PUT /admin/financial/pricing-settings`
- **Body:**
```json
{
  "discount_one_child": 0.00,
  "discount_two_children": 10.00,
  "discount_three_plus_children": 15.00,
  "platform_commission_rate": 8.00,
  "price_per_km_ac": 2.50,
  "price_per_km_non_ac": 2.00
}
```

#### 🔹 إدارة طرق الدفع (Payment Methods)
- **جلب كل طرق الدفع:** `GET /admin/payment-methods`
- **إنشاء طريقة جديدة:** `POST /admin/payment-methods`
- **تعديل طريقة:** `PUT /admin/payment-methods/{id}`
- **تبديل حالة التفعيل (Toggle):** `PATCH /admin/payment-methods/{id}/toggle-status`
- **حذف طريقة دفع:** `DELETE /admin/payment-methods/{id}`

---

## 📊 تصدير التقارير المالية
- **الرابط:** `GET /admin/reports/export?type=financial&format=csv&period=month`
- **ينتج ملف CSV مباشر للتحميل.**
