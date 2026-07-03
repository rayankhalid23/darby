<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>وثيقة عقد دربي الرقمية - {{ $contract?->contract_number }}</title>
    <style>
        @page {
            margin: 45px;
            margin-top: 25px;
            @bottom-center {
                content: "صفحة {PAGENO} من {nbpg}";
                font-family: 'tajawal', sans-serif;
                font-size: 10px;
                color: #94a3b8;
            }
        }
        
        body {
            font-family: 'tajawal', sans-serif;
            direction: rtl;
            text-align: right;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding-top: 5px;
            line-height: 1.9;
        }

        /* ترويسة متوازنة ومفتوحة المساحات */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .brand-logo {
            font-size: 34px;
            font-weight: 900;
            color: #007A99;
            letter-spacing: -0.5px;
        }
        
        .brand-logo .dot {
            color: #F59E0B;
        }
        
        .brand-tagline {
            font-size: 11px;
            color: #64748b;
            margin-top: 5px;
            font-weight: normal;
        }

        .document-meta-box {
    text-align: left;
    font-size: 11px;
    color: #475569;
    line-height: 24px; /* ◄◄ قم بتغيير هذا السطر (اضف 24px بدلاً من 2.6) */
    padding: 15px 20px;
    background-color: #f8fafc;
    border: 1px dashed #007A99;
    border-radius: 8px;
}

        .document-meta-box strong {
            color: #007A99;
        }

        .intro-text {
            font-size: 12px;
            color: #475569;
            text-align: justify;
            line-height: 2.2; 
            margin-bottom: 25px;
            background-color: #f0f7f9;
            padding: 15px 20px;
            border-radius: 8px;
            border-right: 4px solid #007A99;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #007A99;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 8px;
        }

        /* إصلاح الجداول: تم تحويل التداخل إلى separate لعدم ضرب المحرك */
        .info-card-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .card-inner-padding {
            padding: 15px 18px;
        }

        .card-header-parent {
            font-size: 12px;
            font-weight: bold;
            color: #007A99;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .card-header-driver {
            font-size: 12px;
            font-weight: bold;
            color: #F59E0B;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .data-nested-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-nested-table td {
            padding: 7px 0; 
            font-size: 11px;
            vertical-align: middle;
        }

        .info-item-label {
            color: #64748b;
            width: 30%;
        }

        .info-item-value {
            color: #1e293b;
            font-weight: bold;
        }

        .grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .grid-table td.grid-cell {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        /* إصلاح الجدول الآخر لمنع الانهيار */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 5px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .data-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: bold;
            padding: 12px;
            border-bottom: 2px solid #e2e8f0;
            text-align: right;
        }

        .data-table td {
            font-size: 11px;
            color: #334155;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            line-height: 1.8;
        }

        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }

        .badge-primary {
            background-color: #e6f2f5;
            color: #007A99;
            border: 1px solid #b3d7df;
        }

        .badge-success {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .legal-notice-box {
            font-size: 11px;
            color: #475569;
            text-align: justify;
            line-height: 2.2; 
            background-color: #fff9db;
            padding: 18px 22px;
            border-radius: 8px;
            margin-top: 15px;
            border-right: 4px solid #F59E0B;
        }

        .legal-item {
            margin-bottom: 10px;
            padding-right: 15px;
        }

        .signature-table {
            width: 100%;
            margin-top: 35px;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 33.33%;
            vertical-align: top;
            padding: 0 8px;
        }

        /* الكلاس الأول */
.sig-box {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 180px;
    text-align: center;
    background-color: #f8fafc;
    line-height: 2200px; /* ◄◄ قم بتغيير هذا السطر (اضف 22px بدلاً من 1.8) */
}

/* الكلاس الثاني تحته مباشرة */
.sig-box-platform {
    border: 1px solid #b3d7df;
    border-radius: 10px;
    padding: 180px;
    text-align: center;
    background-color: #e6f2f5;
    line-height: 2200px; /* ◄◄ قم بتغيير هذا السطر (اضف 22px بدلاً من 1.8) */
}

        .sig-title {
            font-size: 11px;
            font-weight: bold;
            color: #475569;
            margin-bottom: 20px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
        }

        .sig-name {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
        }

        .sig-status {
            font-size: 10px;
            color: #007A99;
            margin-top: 5px;
            font-weight: bold;
        }

        .footer-area {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
            line-height: 1.8em; 
        }
    </style>
</head>
<body>

    <watermarktext text="DARBY" alpha="0.03" font-style="B" />

    <!-- تعديل لتفادي انهيار محرك PDF بسبب الـ Gradient -->
    <div style="width: 100%; height: 5px; background-color: #007A99; margin-bottom: 25px;"></div>

    <table class="header-table">
        <tr>
            <td style="vertical-align: middle;">
                <div class="brand-logo">DARBY<span class="dot">.</span></div>
                <div class="brand-tagline">المنصة المعتمدة لتنظيم وتأمين النقل المدرسي الطلابي للأبناء</div>
            </td>
            <td style="vertical-align: middle; text-align: left; width: 290px;">
                <div class="document-meta-box">
                    رقم وثيقة التعاقد: <strong>#{{ $contract?->contract_number }}</strong><br>
                    تاريخ التوثيق الرقمي: <strong>{{ $contract?->signed_at ? \Carbon\Carbon::parse($contract->signed_at)->format('Y-m-d') : ($contract?->created_at ? \Carbon\Carbon::parse($contract->created_at)->format('Y-m-d') : date('Y-m-d')) }}</strong><br>
                    حالة الوثيقة: <span class="badge badge-success">✓ معتمد وساري المفعول</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="intro-text">
        تم إبرام هذا العقد الرقمي وتوثيقه آلياً عبر منصة <strong>دربي (Darby)</strong> التقنية، كوثيقة تشغيلية معتمدة تنظم أحكام الخدمة والالتزامات المتبادلة بين السائق المستقل (الطرف الأول) وولي الأمر المشترك (الطرف الثاني) لضمان رحلة نقل مدرسي آمنة ومنتظمة.
    </div>

    <div class="section-title">أولاً: بيانات أطراف الاتفاقية</div>
    
    <table class="grid-table">
        <tr>
            <!-- ولي الأمر -->
            <td class="grid-cell" style="padding-left: 10px;">
                <table class="info-card-table">
                    <tr>
                        <td class="card-inner-padding">
                            <div class="card-header-parent">◈ الطرف الأول (ولي الأمر)</div>
                            
                            <table class="data-nested-table">
                                <tr>
                                    <td class="info-item-label">الاسم الكامل:</td>
                                    <td class="info-item-value">{{ $contract?->parent?->user?->full_name ?? 'غير متوفر' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-item-label">الهاتف المحمول:</td>
                                    <td class="info-item-value" style="direction: ltr; text-align: right;">{{ $contract?->parent?->user?->phone_number ?? 'غير متوفر' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-item-label">الهاتف البديل:</td>
                                    <td class="info-item-value" style="direction: ltr; text-align: right;">{{ $contract?->parent?->user?->alternative_phone ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-item-label">البريد الإلكتروني:</td>
                                    <td class="info-item-value">{{ $contract?->parent?->user?->email ?? 'غير متوفر' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
            
            <!-- السائق -->
            <td class="grid-cell" style="padding-right: 10px;">
                <table class="info-card-table">
                    <tr>
                        <td class="card-inner-padding">
                            <div class="card-header-driver">◈ الطرف الثاني (السائق المستقل)</div>
                            
                            <table class="data-nested-table">
                                <tr>
                                    <td class="info-item-label">الاسم الكامل:</td>
                                    <td class="info-item-value">{{ $contract?->driver?->user?->full_name ?? 'غير متوفر' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-item-label">الهاتف المحمول:</td>
                                    <td class="info-item-value" style="direction: ltr; text-align: right;">{{ $contract?->driver?->user?->phone_number ?? 'غير متوفر' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-item-label">البريد الإلكتروني:</td>
                                    <td class="info-item-value">{{ $contract?->driver?->user?->email ?? 'غير متوفر' }}</td>
                                </tr>
                                <tr>
                                    <td class="info-item-label">الرقم الوطني:</td>
                                    <td class="info-item-value">{{ $contract?->driver?->national_id ?? 'غير متوفر' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">ثانياً: تفاصيل المركبة المعتمدة للنقل</div>
    <table class="info-card-table">
        <tr>
            <td class="card-inner-padding">
                @php
                    $vehicle = $contract?->driver?->vehicles?->first() ?? null;
                @endphp
                @if($vehicle)
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="width: 25%; line-height: 1.8;">
                                <span style="color: #64748b; font-size: 11px;">نوع وماركة المركبة:</span><br>
                                <span style="font-weight: bold; font-size: 12px; color: #1e293b;">{{ $vehicle->brand }} {{ $vehicle->model }}</span>
                            </td>
                            <td style="width: 25%; line-height: 1.8;">
                                <span style="color: #64748b; font-size: 11px;">رقم اللوحة المعدنية:</span><br>
                                <span style="font-weight: bold; font-size: 12px; direction: ltr; display: inline-block; color: #1e293b;">{{ $vehicle->plate_number }}</span>
                            </td>
                            <td style="width: 25%; line-height: 1.8;">
                                <span style="color: #64748b; font-size: 11px;">سنة الصنع واللون:</span><br>
                                <span style="font-weight: bold; font-size: 12px; color: #1e293b;">{{ $vehicle->year }} ({{ $vehicle->color ?? '—' }})</span>
                            </td>
                            <td style="width: 25%; line-height: 1.8; vertical-align: middle;">
                                <span style="color: #64748b; font-size: 11px;">حالة التكييف والسعة:</span><br>
                                <span class="badge badge-primary">{{ $vehicle->has_ac ? 'مركبة مكيفة' : 'غير مكيفة' }}</span>
                                <span style="font-weight: bold; font-size: 11px; margin-right: 3px; color: #1e293b;">({{ $vehicle->capacity_manual ?? '—' }} مقاعد)</span>
                            </td>
                        </tr>
                    </table>
                @else
                    <div style="color: #ef4444; font-size: 11px; font-weight: bold; text-align: center;">⚠️ تنبيه: لا توجد مركبة معتمدة مسجلة للسائق حالياً في النظام.</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="section-title">ثالثاً: محددات وتفاصيل باقة الاشتراك</div>
    <table class="info-card-table" style="background-color: #f8fafc;">
        <tr>
            <td class="card-inner-padding">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 33.33%; padding-bottom: 12px; line-height: 1.8;">
                            <span style="color: #64748b; font-size: 11px;">نوع الاشتراك ومدته:</span><br>
                            <span style="font-weight: bold; font-size: 12px; color: #007A99;">
                                {{ $contract?->subscription_type === 'monthly' ? 'اشتراك شهري منتظم' : 'اشتراك يومي' }}
                            </span>
                            <span style="font-size: 11px; color: #475569;">
                                @if($contract?->days_count) ({{ $contract->days_count }} يوم) @endif
                            </span>
                        </td>
                        <td style="width: 33.33%; padding-bottom: 12px; line-height: 1.8;">
                            <span style="color: #64748b; font-size: 11px;">اتجاه الرحلات وتوقيتها:</span><br>
                            <span style="font-weight: bold; font-size: 12px; color: #1e293b;">
                                @if($contract?->direction === 'go') ذهاب فقط (صباحاً)
                                @elseif($contract?->direction === 'return') إياب فقط (مساءً)
                                @elseif($contract?->direction === 'both') ذهاب وإياب
                                @else {{ $contract?->direction }} @endif
                            </span>
                            <span style="font-size: 11px; color: #475569;">
                                - 
                                @if($contract?->timing === 'MORNING') صباحي
                                @elseif($contract?->timing === 'EVENING') مسائي
                                @elseif($contract?->timing === 'BOTH') صباحي ومسائي
                                @else {{ $contract?->timing }} @endif
                            </span>
                        </td>
                        <td style="width: 33.33%; padding-bottom: 12px; line-height: 1.8;">
                            <span style="color: #64748b; font-size: 11px;">تاريخ سريان ونهاية العقد:</span><br>
                            <span style="font-weight: bold; font-size: 11px; color: #1e293b;">
                                من: {{ $contract?->start_date ? \Carbon\Carbon::parse($contract->start_date)->format('Y-m-d') : '—' }} 
                                إلى: {{ $contract?->end_date ? \Carbon\Carbon::parse($contract->end_date)->format('Y-m-d') : '—' }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 12px; border-top: 1px solid #e2e8f0; line-height: 1.8;">
                            <span style="color: #64748b; font-size: 11px;">مواعيد التجمع والانطلاق المقررة:</span><br>
                            <span style="font-weight: bold; font-size: 11px; color: #1e293b;">
                                الذهاب: {{ $contract?->pickup_time ? \Carbon\Carbon::parse($contract->pickup_time)->format('h:i A') : '—' }} | 
                                العودة: {{ $contract?->dropoff_time ? \Carbon\Carbon::parse($contract->dropoff_time)->format('h:i A') : '—' }}
                            </span>
                        </td>
                        <td style="padding-top: 12px; border-top: 1px solid #e2e8f0; line-height: 1.8;">
                            <span style="color: #64748b; font-size: 11px;">أقصى وقت انتظار مسموح للسائق:</span><br>
                            <span style="font-weight: bold; font-size: 12px; color: #b45309;">{{ $contract?->max_waiting_time ?? 15 }} دقيقة</span>
                        </td>
                        <td style="padding-top: 12px; border-top: 1px solid #e2e8f0; line-height: 1.8;">
                            <span style="color: #64748b; font-size: 11px;">القيمة المالية الإجمالية للاشتراك:</span><br>
                            <span style="font-weight: 900; font-size: 16px; color: #007A99;">{{ number_format($contract?->total_price ?? 0, 2) }} د.ل</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="section-title">رابعاً: بيانات الأطفال المشمولين بالخدمة الجغرافية</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 25%;">اسم الطفل</th>
                <th style="width: 20%;">المدرسة والصف</th>
                <th style="width: 22%;">نقطة الانطلاق (المنزل)</th>
                <th style="width: 18%;">نقطة الوصول (المدرسة)</th>
                <th style="width: 10%;">سعر النقل</th>
            </tr>
        </thead>
        <tbody>
            @php
                $children = $contract?->subscriptionRequest?->children ?? collect();
                $rowIndex = 1;
            @endphp
            @forelse($children as $child)
                <tr>
                    <td>{{ $rowIndex++ }}</td>
                    <td>
                        <strong>{{ $child->full_name }}</strong><br>
                        <span style="font-size: 9px; color: #64748b;">العمر: {{ $child->age }} سنة | الجنس: {{ $child->gender === 'female' ? 'أنثى' : 'ذكر' }}</span>
                    </td>
                    <td>
                        {{ $child->school?->name ?? 'غير متوفر' }}<br>
                        <span style="font-size: 9px; color: #64748b;">الصف: {{ $child->grade ?? '—' }}</span>
                    </td>
                    <td>
                        <span style="font-weight: 500;">{{ $child->pivot?->home_label ?? '—' }}</span>
                        @if($child->pivot?->home_lat)
                            <br><span style="font-size: 8px; color: #94a3b8; font-family: monospace;">({{ number_format($child->pivot->home_lat, 5) }}, {{ number_format($child->pivot->home_lng, 5) }})</span>
                        @endif
                    </td>
                    <td>
                        <span style="font-weight: 500;">{{ $child->pivot?->school_label ?? '—' }}</span>
                        @if($child->pivot?->school_lat)
                            <br><span style="font-size: 8px; color: #94a3b8; font-family: monospace;">({{ number_format($child->pivot->school_lat, 5) }}, {{ number_format($child->pivot->school_lng, 5) }})</span>
                        @endif
                    </td>
                    <td style="font-weight: bold; color: #007A99;">
                        {{ number_format($child->pivot?->price_per_child ?? 0, 2) }} د.ل
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">لا توجد بيانات للأطفال مرتبطة بهذا العقد.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">خامساً: شروط السلامة والخصوصية والأحكام العامة للتعاقد</div>
    <div class="legal-notice-box">
        @if(isset($contract?->clauses) && is_array($contract->clauses) && count($contract->clauses) > 0)
            @foreach($contract->clauses as $index => $clauseText)
                <div class="legal-item">
                    <strong>البند ({{ $index + 1 }}):</strong> {{ $clauseText }}
                </div>
            @endforeach
        @else
            <div class="legal-item">يقر السائق المستقل بالتزامه التام بكافة قوانين السير والسلامة العامة، وبمسؤوليته الكاملة عن سلامة الطلاب داخل المركبة أثناء الرحلة.</div>
            <div class="legal-item">يلتزم ولي الأمر بتجهيز وإعداد الطالب قبل وصول السائق بوقت مناسب، تلافياً لتعطيل وإعاقة مسار الجدول الزمني اللوجستي للمنصة.</div>
            <div class="legal-item">يوافق الطرفان بصيغة نهائية على مشاركة وتتبع الموقع الجغرافي (GPS) للرحلات بصفة حية ومباشرة عبر خوادم تطبيق دربي لأغراض الأمان والجودة.</div>
        @endif
    </div>

    <table class="signature-table">
        <tr>
            <td>
                <div class="sig-box">
                    <div class="sig-title">الطرف الأول (ولي الأمر)</div>
                    <div class="sig-name">{{ $contract?->parent?->user?->full_name ?? '—' }}</div>
                    <div class="sig-status">موافق ومعتمد إلكترونياً</div>
                </div>
            </td>
            <td>
                <div class="sig-box">
                    <div class="sig-title">الطرف الثاني (السائق المستقل)</div>
                    <div class="sig-name">{{ $contract?->driver?->user?->full_name ?? '—' }}</div>
                    <div class="sig-status">موافق ومعتمد إلكترونياً</div>
                </div>
            </td>
            <td>
                <div class="sig-box-platform">
                    <div class="sig-title" style="color: #007A99; border-bottom-color: #b3d7df;">ختم التوثيق التقني للمنصة</div>
                    <div class="sig-name" style="color: #007A99;">DARBY<span style="color:#F59E0B;">.</span></div>
                    <div class="sig-status" style="color: #F59E0B;">توثيق رقمي آمن 100%</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-area">
        وثيقة رقمية معتمدة ومصدرة آلياً من أنظمة منصة دربي للنقل المدرسي الآمن.<br>
        هذا العقد إلكتروني بالكامل ولا يتطلب توقيعاً خطياً. تم توثيق هويات الأطراف وتواقيعهم برمجياً.<br>
        © {{ date('Y') }} Darby. جميع الحقوق محفوظة.
    </div>

</body>
</html>