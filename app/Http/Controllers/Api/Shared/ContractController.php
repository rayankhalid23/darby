<?php

namespace App\Http\Controllers\Api\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Shared\StoreContractRequest;
use App\Http\Resources\Api\Shared\ContractResource;
use App\Services\Shared\ContractService;
use App\Models\Shared\Clause; // 🔥 لاحظ إضافة \Shared\
use Exception;
use App\Models\Shared\Contract; // استيراد موديل العقد
use Illuminate\Http\JsonResponse;
use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;



use Illuminate\Support\Facades\Log;

class ContractController extends Controller
{
    protected ContractService $contractService;

    // حقن السيرفس داخل الكنترولر عبر الـ Constructor
    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    /**
     * استقبال طلب إنشاء العقد من السائق
     */
    public function store(StoreContractRequest $request): JsonResponse
    {
        try {
            // استدعاء البيزنس لوجيك من السيرفس وتمرير البيانات الموثقة فقط
            $contract = $this->contractService->createContractForDriver($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء مسودة العقد بنجاح وبانتظار موافقة ولي الأمر.',
                'data'    => new ContractResource($contract)
            ], 201);

        } catch (Exception $e) {
            // التعامل مع الأخطاء المخصصة التي أطلقناها في السيرفس (مثل 403 أو 400)
            $statusCode = in_array($e->getCode(), [400, 403]) ? $e->getCode() : 500;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * موافقة ولي الأمر على العقد
     */
    public function accept($id): JsonResponse
    {
        try {
            $contract = $this->contractService->acceptContract($id);
            return response()->json([
                'success' => true,
                'message' => 'تم توقيع العقد بنجاح وبدأ سريان الاشتراك.',
                'data'    => new ContractResource($contract)
            ], 200);
        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403]) ? $e->getCode() : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }

    /**
     * رفض ولي الأمر للعقد
     */
    public function reject($id): JsonResponse
    {
        try {
            $contract = $this->contractService->rejectContract($id);
            return response()->json([
                'success' => true,
                'message' => 'تم رفض مسودة العقد وإلغاء الطلب.',
                'data'    => new ContractResource($contract)
            ], 200);
        } catch (Exception $e) {
            $statusCode = in_array($e->getCode(), [400, 403]) ? $e->getCode() : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $statusCode);
        }
    }
    public function clauses()
{
    // جلب كل الشروط من قاعدة البيانات وإرجاعها مجزأة حسب الأقسام أو مرتبة
    $clauses = Clause::all();
    return response()->json([
        'success' => true,
        'data' => $clauses
    ], 200);
}
public function generatePdf($id)
{
    // 1. كتم أي تحذيرات أو ملاحظات داخلية قد تسربها المكتبة وتفسد البافر
    $oldErrorReporting = error_reporting();
    error_reporting(0); 

    try {
        $contract = Contract::with([
            'driver.user',
            'driver.vehicles',
            'parent.user',
            'subscriptionRequest.children.school',
        ])->findOrFail($id);

        // 2. تنظيف بافر المخرجات (Output Buffering) بالكامل لإزالة أي فراغات أو مخرجات سابقة
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        // 3. حقن مصفوفة الإعدادات الكاملة والآمنة لمنع خطأ الـ display_mode وتمرير خط Tajawal
        config([
            'pdf' => [
                'mode'                  => 'utf-8',
                'format'                => 'A4',
                'default_font_size'     => '12',
                'default_font'          => 'tajawal',
                'margin_left'           => 15,
                'margin_right'          => 15,
                'margin_top'            => 15,
                'margin_bottom'         => 15,
                'margin_header'         => 0,
                'margin_footer'         => 0,
                'orientation'           => 'P',
                'title'                 => 'Darby Contract',
                'author'                => 'Darby',
                'watermark'             => '',
                'show_watermark'        => false,
                'watermark_font'        => 'sans-serif',
                'display_mode'          => 'fullpage', // 🔥 هذا هو المفتاح الذي حل المشكلة ومنع الانهيار
                'watermark_text_alpha'  => 0.1,
                'autoScriptToLang'      => true,       // تفعيل التعرف التلقائي على العربية
                'autoLangToFont'        => true,       // ربط الحروف بالخط تلقائياً
                
                // دعم مسار الخطوط للمسميات القديمة والجديدة للحزمة لضمان التشغيل الفوري
                'font_path'             => storage_path('fonts/'),
                'custom_font_dir'       => storage_path('fonts/'),
                
                'font_data'             => [
                    'tajawal' => [
                        'R'          => 'Tajawal-Regular.ttf',
                        'B'          => 'Tajawal-Bold.ttf',
                        'useOTL'     => 0xFF,          // تركيب الحروف العربية المترابطة
                        'useKashida' => 75,
                    ]
                ],
                'custom_font_data'      => [
                    'tajawal' => [
                        'R'          => 'Tajawal-Regular.ttf',
                        'B'          => 'Tajawal-Bold.ttf',
                        'useOTL'     => 0xFF,
                        'useKashida' => 75,
                    ]
                ]
            ]
        ]);

        // 4. بناء ملف الـ PDF عبر القالب بالاعتماد على الطريقة القياسية
        $pdf = PDF::loadView('Pdf.contract', ['contract' => $contract]);

        // جلب البيانات الثنائية الخام للملف (Raw Binary Data)
        $pdfContent = $pdf->output();

        // تنظيف نهائي للبافر للتأكد من خلوه من الشوائب
        ob_end_clean();
        
        // إعادة إعدادات تقارير الأخطاء لوضعها الطبيعي في النظام
        error_reporting($oldErrorReporting);

        // 5. بناء استجابة لارافيل النظيفة والمطابقة لمعايير المتصفحات و Postman
        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contract_' . $id . '.pdf"', // استخدام inline للعرض المباشر بالمتصفح
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);

    } catch (\Exception $e) {
        // إعادة إعدادات الأخطاء في حال حدوث استثناء
        error_reporting($oldErrorReporting);

        // تسجيل دقيق جداً للمشكلة الحقيقية في سجلات لارافيل لمعرفة الخطأ بالتفصيل
        \Log::error('PDF Critical Failure: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
        
        return response()->json([
            'status' => false,
            'error_code' => 'PDF_GENERATION_ERROR',
            'message' => 'حدث خطأ أثناء بناء الوثيقة: ' . $e->getMessage()
        ], 500);
    }
}

}