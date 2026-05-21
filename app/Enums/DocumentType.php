<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    // أنواع مستندات الموارد البشرية HR
    case EMPLOYEE_PHOTO = 'employee_photo';
    case HR_CERTIFICATE = 'hr_certificate';
    case EMPLOYEE_CONTRACT = 'employee_contract';
    case NATIONAL_ID = 'national_id';
    case PASSPORT = 'passport';

    // أنواع مستندات الموديولات المالية والمخازن
    case INVOICE = 'invoice';
    case RECEIPT = 'receipt';
    case PAYMENT_VOUCHER = 'payment_voucher';
    case COMPANY_LICENSE = 'company_license';

    // أنواع عامة ومستقبلية
    case OTHER = 'other';

    /**
     * تحديد قرص التخزين المناسب بناءً على حساسية المستند
     * (المعيار العالمي لحماية البيانات الحساسة DMS)
     */
    public function disk(): string
    {
        return match($this) {
            // وثائق عامة لا تشكل خطراً أمنياً ويمكن كاشيرتها
            self::EMPLOYEE_PHOTO, self::OTHER => 'public',

            // وثائق حساسة جداً يجب حمايتها وتخزينها في مكان مغلق تماماً
            self::EMPLOYEE_CONTRACT,
            self::HR_CERTIFICATE,
            self::NATIONAL_ID,
            self::PASSPORT,
            self::INVOICE,
            self::RECEIPT,
            self::PAYMENT_VOUCHER,
            self::COMPANY_LICENSE => 'private',
        };
    }

    /**
     * جلب المسمى باللغة العربية لعرضه في الواجهات (Panda UI)
     */
    public function label(): string
    {
        return match($this) {
            self::EMPLOYEE_PHOTO => 'الصورة الشخصية',
            self::HR_CERTIFICATE => 'شهادة موارد بشرية',
            self::EMPLOYEE_CONTRACT => 'عقد عمل',
            self::NATIONAL_ID => 'الهوية الوطنية / الإقامة',
            self::PASSPORT => 'جواز السفر',
            self::INVOICE => 'فاتورة',
            self::RECEIPT => 'إيصال استلام',
            self::PAYMENT_VOUCHER => 'سند صرف',
            self::COMPANY_LICENSE => 'الرخصة التجارية',
            self::OTHER => 'مستند آخر',
        };
    }
}
