# 📘 دليل الربط الشامل للواجهة الأمامية (Frontend RBAC Integration Guide)
## نظام الأدوار والصلاحيات وإدارة المشرفين — منصة دربي (Darby)

---

## 1. نظرة عامة لمهندس الواجهة الأمامية (Frontend Overview)
يعتمد النظام على **Role-Based Access Control (RBAC)** مع دعم **الصلاحيات المخصصة الفردية (Custom Overrides)**:
- عند تسجيل دخول المشرف أو جلب ملفه الشخصي (`/api/admin/profile`)، يُرجع الباك إند مصفوفة الصلاحيات المتاحة له `permissions: string[]`.
- إذا كان المشرف **مدير نظام عام (`super_admin`)**، فإن مصفوفة الصلاحيات تحتوي على `["*"]` ويملك صلاحية كل شيء.
- إذا كان المشرف متخصصاً (مالي، أسطول، عمليات، شكاوى، جغرافيا)، تحتوي المصفوفة على مفاتيح الصلاحيات الخاصة به (مثل `["financial.view_summary", "financial.manage_withdrawals"]`).
- يُسمح بمنح المشرف صلاحيات مخصصة فردية `custom_permissions` تُدمج تلقائياً مع صلاحيات دوره.

---

## 2. ترويسات المصادقة المطلوبة (Headers)
يجب إرسال الترويسات التالية مع **كافة الطلبات**:
```http
Authorization: Bearer <SANCTUM_TOKEN>
Accept: application/json
Content-Type: application/json
```

---

## 3. التحقق من الصلاحيات في الفرونت إند (Frontend Permission Helpers)

### كود مساعد للتحقق (JavaScript / TypeScript / Dart / React / Vue):
```typescript
// دالة فحص امتلاك صلاحية معينة
export function hasPermission(userPermissions: string[], requiredPermission: string): boolean {
  if (!userPermissions || userPermissions.length === 0) return false;
  
  // إذا كان مديراً عاماً يملك الوصول الكامل
  if (userPermissions.includes('*')) return true;
  
  // تطابق تام مع الصلاحية
  if (userPermissions.includes(requiredPermission)) return true;
  
  // تطابق شامل للقطاع (Wildcard) مثل financial.*
  const [category] = requiredPermission.split('.');
  if (userPermissions.includes(`${category}.*`)) return true;
  
  return false;
}

// دالة فحص امتلاك أي صلاحية من مجموعة صلاحيات (للقوائم الرئيسية)
export function hasAnyPermission(userPermissions: string[], requiredPermissions: string[]): boolean {
  if (!userPermissions || userPermissions.length === 0) return false;
  if (userPermissions.includes('*')) return true;
  return requiredPermissions.some(perm => hasPermission(userPermissions, perm));
}
```

---

## 4. التحكم في ظهور عناصر القائمة الجانبية (Sidebar Navigation)

قم بإخفاء أو إظهار التبويبات بناءً على الصلاحيات التالية:

```javascript
const menuItems = [
  {
    title: 'الرئيسية والإحصائيات',
    route: '/admin/dashboard',
    permission: 'dashboard.view_stats'
  },
  {
    title: 'رادار التتبع المباشر',
    route: '/admin/radar',
    permission: 'dashboard.view_radar'
  },
  {
    title: 'أسطول السائقين',
    route: '/admin/drivers',
    permission: 'drivers.view',
    badgeKey: 'pending_drivers_count'
  },
  {
    title: 'طلبات تعديل المركبات المعلقة',
    route: '/admin/drivers/pending-changes',
    permission: 'drivers.review_changes'
  },
  {
    title: 'الشكاوى والدعم الفني',
    route: '/admin/complaints',
    permission: 'complaints.view'
  },
  {
    title: 'تقييمات السائقين',
    route: '/admin/driver-reviews',
    permission: 'driver_reviews.manage'
  },
  {
    title: 'الإدارة المالية والخزينة',
    route: '/admin/financial',
    permission: 'financial.view_summary',
    subItems: [
      { title: 'طلبات السحب', route: '/admin/financial/withdrawals', permission: 'financial.manage_withdrawals' },
      { title: 'طلبات الشحن', route: '/admin/financial/recharges', permission: 'financial.manage_recharges' },
      { title: 'الأمانات المعلقة', route: '/admin/financial/escrows', permission: 'financial.release_escrows' },
      { title: 'النزاعات المالية', route: '/admin/financial/disputes', permission: 'financial.resolve_disputes' },
      { title: 'دفتر القيود المحاسبية', route: '/admin/financial/ledger', permission: 'financial.view_ledger' },
      { title: 'إعدادات التسعير', route: '/admin/financial/pricing', permission: 'financial.manage_pricing' },
      { title: 'طرق الدفع', route: '/admin/financial/payment-methods', permission: 'financial.manage_payment_methods' }
    ]
  },
  {
    title: 'المدارس',
    route: '/admin/schools',
    permission: 'schools.manage'
  },
  {
    title: 'البلديات والمناطق الجغرافية',
    route: '/admin/geography',
    permission: 'geography.manage'
  },
  {
    title: 'إدارة المشرفين والصلاحيات',
    route: '/admin/supervisors',
    permission: 'admins.manage'
  },
  {
    title: 'سجل تدقيق الإجراءات (Audit Logs)',
    route: '/admin/audit-logs',
    permission: 'audit_logs.view'
  },
  {
    title: 'التقارير والإحصائيات',
    route: '/admin/reports',
    permission: 'reports.view'
  }
];
```

---

## 5. شاشة إضافة / تعديل مشرف (Supervisor Management Form)

### الخطوة 1: استدعاء شجرة الأدوار والصلاحيات عند فتح الشاشة
* **Endpoint:** `GET /api/admin/roles-permissions`
* **Response (200 OK):**
```json
{
  "status": true,
  "success": true,
  "data": {
    "roles": [
      { "id": 1, "key": "super_admin", "name": "مدير النظام العام", "description": "صلاحيات كاملة وغير مقيدة", "permissions": ["*"] },
      { "id": 2, "key": "operations_supervisor", "name": "مشرف العمليات والتشغيل الميداني", "permissions": ["dashboard.view_stats", "dashboard.view_radar", "trips.generate_daily", "trips.emergency_cancel", "schools.manage", "geography.manage", "reports.view"] },
      { "id": 5, "key": "fleet_supervisor", "name": "مشرف شؤون وأسطول السائقين", "permissions": ["drivers.view", "drivers.review_initial", "drivers.review_changes", "drivers.edit_data", "drivers.suspend"] },
      { "id": 6, "key": "support_supervisor", "name": "مشرف الدعم والشكاوى والجودة", "permissions": ["complaints.view", "complaints.resolve", "driver_reviews.manage", "notifications.broadcast"] },
      { "id": 7, "key": "finance_officer", "name": "المشرف المالي ومسؤول الخزينة", "permissions": ["financial.view_summary", "financial.view_ledger", "financial.manage_withdrawals", "financial.manage_recharges", "financial.release_escrows", "financial.resolve_disputes", "financial.manage_settlements", "financial.manage_pricing", "financial.manage_payment_methods", "reports.view"] },
      { "id": 8, "key": "geography_supervisor", "name": "مشرف التخطيط الجغرافي والمدارس", "permissions": ["schools.manage", "geography.manage"] }
    ],
    "permissions_tree": [
      {
        "group_key": "dashboard_operations",
        "group_name": "لوحة التحكم والعمليات التشغيلية",
        "permissions": [
          { "key": "dashboard.view_stats", "name": "عرض إحصائيات الداشبورد الرئيسية" },
          { "key": "dashboard.view_radar", "name": "عرض رادار تتبع الرحلات المباشر" },
          { "key": "trips.generate_daily", "name": "توليد الرحلات اليومية يدوياً" },
          { "key": "trips.emergency_cancel", "name": "إلغاء الرحلات الاضطراري وتطبيق مصفوفة الغرامات" }
        ]
      },
      {
        "group_key": "drivers_fleet",
        "group_name": "إدارة أسطول السائقين والمركبات",
        "permissions": [
          { "key": "drivers.view", "name": "استعراض قائمة وتفاصيل السائقين" },
          { "key": "drivers.review_initial", "name": "مراجعة وقبول/رفض طلبات تسجيل السائقين الجديدة" },
          { "key": "drivers.review_changes", "name": "مراجعة وتطبيق طلبات تعديل البيانات والمركبات المعلقة" },
          { "key": "drivers.edit_data", "name": "تعديل بيانات السائق مباشرة من الإدارة" },
          { "key": "drivers.suspend", "name": "إيقاف أو تجميد حساب السائق" }
        ]
      },
      {
        "group_key": "complaints_quality",
        "group_name": "الشكاوى وخدمة العملاء والجودة",
        "permissions": [
          { "key": "complaints.view", "name": "عرض قائمة وتفاصيل شكاوى أولياء الأمور والسائقين" },
          { "key": "complaints.resolve", "name": "معالجة وإغلاق الشكاوى واتخاذ القرار" },
          { "key": "driver_reviews.manage", "name": "مراقبة وإدارة تقييمات السائقين وحذف المسيء منها" },
          { "key": "notifications.broadcast", "name": "إرسال إشعارات جماعية وإدارية" }
        ]
      },
      {
        "group_key": "financial_management",
        "group_name": "الإدارة المالية والخزينة",
        "permissions": [
          { "key": "financial.view_summary", "name": "عرض ملخص الإيرادات والسلامة المالية" },
          { "key": "financial.view_ledger", "name": "عرض سجل القيود المحاسبية وتدقيق المعاملات" },
          { "key": "financial.manage_withdrawals", "name": "مراجعة ومعالجة طلبات سحب أرباح السائقين" },
          { "key": "financial.manage_recharges", "name": "مراجعة وتأكيد طلبات شحن المحافظ" },
          { "key": "financial.release_escrows", "name": "متابعة وتحرير الأمانات المالية المعلقة" },
          { "key": "financial.resolve_disputes", "name": "البت في النزاعات المالية والمطالبات" },
          { "key": "financial.manage_settlements", "name": "إجراء التسويات الشهرية وعقود الإلغاء المبكر" },
          { "key": "financial.manage_pricing", "name": "تعديل إعدادات التسعير وعمولات المنصة" },
          { "key": "financial.manage_payment_methods", "name": "إدارة وتفعيل طرق الدفع الإلكتروني واليدوي" }
        ]
      },
      {
        "group_key": "schools_geography",
        "group_name": "المدارس والتخطيط الجغرافي",
        "permissions": [
          { "key": "schools.manage", "name": "إضافة وتعديل وحذف المدارس" },
          { "key": "geography.manage", "name": "إدارة البلديات والمحلات والمناطق الجغرافية" }
        ]
      },
      {
        "group_key": "admins_audit",
        "group_name": "إدارة المشرفين وسجلات التدقيق",
        "permissions": [
          { "key": "admins.manage", "name": "إدارة حسابات المشرفين وتعيين الصلاحيات" },
          { "key": "audit_logs.view", "name": "استعراض سجل تدقيق عمليات المدراء والمشرفين" }
        ]
      },
      {
        "group_key": "reports_analytics",
        "group_name": "التقارير والإحصائيات التحليلية",
        "permissions": [
          { "key": "reports.view", "name": "استعراض تقارير الأداء ومؤشرات KPI" },
          { "key": "reports.export", "name": "تصدير التقارير والبيانات (Excel / CSV / PDF)" }
        ]
      }
    ]
  }
}
```

---

### الخطوة 2: إرسال طلب إنشاء المشرف (Create Supervisor)
* **Endpoint:** `POST /api/admin/admins`
* **Request Body (JSON أو FormData عند رفع الصورة):**
```json
{
  "full_name": "محمد علي الزنتاني",
  "email": "mohamed.fleet@darby.ly",
  "phone_number": "0912345678",
  "password": "Password123",
  "role_id": 5,
  "custom_permissions": [
    "notifications.broadcast"
  ],
  "is_active": 1
}
```

---

### الخطوة 3: إرسال طلب تعديل المشرف (Update Supervisor)
* **Endpoint:** `PUT /api/admin/admins/{id}` أو `POST /api/admin/admins/{id}`
* **Request Body:**
```json
{
  "full_name": "محمد علي الزنتاني",
  "phone_number": "0912345678",
  "role_id": 7,
  "custom_permissions": [
    "drivers.view"
  ],
  "is_active": 1
}
```

---

## 6. معالجة أخطاء رفض الصلاحية (403 Forbidden Error Handling)

عندما يحاول المستخدم استدعاء مسار لا يملك صلاحيته، يُرجع الباك إند:
```json
{
  "status": false,
  "success": false,
  "message": "عذراً، ليس لديك الصلاحية الكافية لتنفيذ هذا الإجراء.",
  "required_permission": "financial.manage_withdrawals"
}
```

### معالجة الخطأ في Axios / Interceptor:
```javascript
axiosInstance.interceptors.response.use(
  response => response,
  error => {
    if (error.response && error.response.status === 403) {
      const message = error.response.data.message || 'ليس لديك الصلاحية الكافية للوصول لهذا القسم.';
      toast.error(message, { duration: 4000 });
    }
    return Promise.reject(error);
  }
);
```

---

## 7. جدول مرجعي سريع لكافة مسارات النظام وصلاحياتها

| القسم في الواجهة | المسار (API Endpoint) | الطريقة (Method) | الصلاحية المطلوبة |
| :--- | :--- | :--- | :--- |
| **شجرة الصلاحيات** | `/api/admin/roles-permissions` | `GET` | متاح لكافة المشرفين المسجلين |
| **الملف الشخصي للمشرف** | `/api/admin/profile` | `GET/POST` | متاح لكافة المشرفين المسجلين |
| **إحصائيات الداشبورد** | `/api/admin/dashboard/stats` | `GET` | `dashboard.view_stats` |
| **رادار الرحلات الحية** | `/api/admin/dashboard/active-trips` | `GET` | `dashboard.view_radar` |
| **توليد الرحلات اليومية** | `/api/admin/trips/generate-daily` | `POST` | `trips.generate_daily` |
| **إلغاء الرحلة بالتعويض** | `/api/admin/financial/trips/{id}/cancel-with-matrix` | `POST` | `trips.emergency_cancel` |
| **قائمة وتفاصيل السائقين** | `/api/admin/drivers` & `/{id}` | `GET` | `drivers.view` |
| **اعتماد السائق الجديد** | `/api/admin/drivers/{id}/review` | `POST` | `drivers.review_initial` |
| **تعديلات المركبات المعلقة** | `/api/admin/drivers/pending-changes` | `GET/POST` | `drivers.review_changes` |
| **تعديل بيانات السائق** | `/api/admin/drivers/{id}` | `PUT/POST` | `drivers.edit_data` |
| **الشكاوى وخدمة العملاء** | `/api/admin/complaints` & `/{id}/review` | `GET/POST` | `complaints.view` / `complaints.resolve` |
| **تقييمات السائقين** | `/api/admin/driver-reviews/all` | `GET/DELETE` | `driver_reviews.manage` |
| **الملخص المالي والخزينة** | `/api/admin/financial/summary` | `GET` | `financial.view_summary` |
| **دفتر اليومية المحاسبي** | `/api/admin/financial/ledger` | `GET` | `financial.view_ledger` |
| **طلبات سحب الأرباح** | `/api/admin/financial/withdrawals` | `GET/POST` | `financial.manage_withdrawals` |
| **طلبات شحن المحافظ** | `/api/admin/financial/recharges` | `GET/POST` | `financial.manage_recharges` |
| **الأمانات المعلقة** | `/api/admin/financial/escrows` | `GET/POST` | `financial.release_escrows` |
| **النزاعات المالية** | `/api/admin/financial/disputes` | `GET/POST` | `financial.resolve_disputes` |
| **إعدادات التسعير والعمولات** | `/api/admin/financial/pricing-settings` | `GET/POST` | `financial.manage_pricing` |
| **طرق الدفع الإلكتروني** | `/api/admin/payment-methods` | `GET/POST/PUT` | `financial.manage_payment_methods` |
| **إدارة المدارس** | `/api/admin/schools` | `GET/POST/PUT/DELETE`| `schools.manage` |
| **البلديات والمناطق** | `/api/admin/municipalities` / `/zones` | `GET/POST/PUT/DELETE`| `geography.manage` |
| **إدارة حسابات المشرفين** | `/api/admin/admins` | `GET/POST/PUT/DELETE`| `admins.manage` |
| **سجل تدقيق الإجراءات** | `/api/admin/admin-audit-logs` | `GET` | `audit_logs.view` |
| **التقارير والإحصائيات** | `/api/admin/reports/kpi-summary` | `GET` | `reports.view` |
| **تصدير التقارير (Excel/PDF)**| `/api/admin/reports/export` | `GET` | `reports.export` |
