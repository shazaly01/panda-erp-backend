<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// 🔥 جدولة أمر تسجيل الحضور التلقائي الخاص بمديول الموارد البشرية
// سيعمل يومياً الساعة 11:30 مساءً بتوقيت السيرفر
Schedule::command('hr:record-auto-attendance')->dailyAt('23:30');
