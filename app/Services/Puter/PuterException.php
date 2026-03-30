<?php

namespace App\Services\Puter;

use RuntimeException;

class PuterException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }

    public static function authRequired(): self
    {
        return new self(
            'يجب الاتصال بحساب Puter من صفحة الإعدادات قبل تشغيل القضايا.',
            'puter_auth_required',
            401,
        );
    }

    public static function authExpired(): self
    {
        return new self(
            'انتهت صلاحية جلسة Puter. يرجى العودة للإعدادات وإعادة الاتصال.',
            'puter_auth_expired',
            401,
        );
    }

    public static function modelUnavailable(string $model = ''): self
    {
        return new self(
            'النموذج المحدد غير متاح حالياً. جرّب نموذجاً آخر من الإعدادات.' . ($model ? " ({$model})" : ''),
            'puter_model_unavailable',
            404,
        );
    }

    public static function networkError(string $detail = ''): self
    {
        return new self(
            'تعذّر الوصول إلى خدمة Puter. تحقق من اتصالك وحاول مرة أخرى.' . ($detail ? " ({$detail})" : ''),
            'puter_network_error',
            503,
        );
    }

    public static function quotaExceeded(): self
    {
        return new self(
            'تجاوزت الحصة المسموح بها في حساب Puter. تحقق من حسابك على puter.com.',
            'puter_quota_exceeded',
            429,
        );
    }
}
