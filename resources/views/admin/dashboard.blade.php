@extends('layouts.admin')

@section('title', 'الرئيسية والمتابعة الحية - دربي')
@section('page_title', 'الرئيسية والمتابعة الحية')

@section('content')
    <!-- هنا تضع محتوى الصفحة الخاص بك (البطاقات، الجداول، الخرائط...) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- بطاقة إحصائية مثال -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-400 font-medium mb-1">إجمالي مستخدمي المنصة</p>
                <h3 class="text-2xl font-bold text-gray-800">1,248</h3>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>
        <!-- أضف باقي البطاقات والمحتوى هنا -->
    </div>
@endsection