<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class UserNotificationService
{
    /**
     * Emit a user-scoped notification payload to Redis for SSE consumption.
     */
    public function emitToUser(int $userId, string $notificationType, array $data = []): void
    {
        $payload = array_merge([
            'event_type' => 'notification',
            'notification_type' => $notificationType,
            'timestamp' => now()->toISOString(),
            'severity' => 'info',
        ], $data);

        $redisKey = "user:{$userId}:notifications";
        Redis::rpush($redisKey, json_encode($payload));
        // Keep user notifications for 2 hours for reconnect replay.
        Redis::expire($redisKey, 7200);
    }

    /**
     * Emit one summary notification for bulk actions.
     */
    public function emitBulkActionSummary(int $userId, string $action, int $count, ?string $url = null): void
    {
        if ($count <= 0) {
            return;
        }

        $titles = [
            'pause' => 'تم إيقاف القضايا مؤقتا',
            'resume' => 'تم استئناف القضايا',
            'retry' => 'تمت إعادة محاولة القضايا',
        ];

        $messages = [
            'pause' => "تم إيقاف {$count} قضية مؤقتا.",
            'resume' => "تم استئناف معالجة {$count} قضية.",
            'retry' => "تمت إعادة معالجة {$count} قضية.",
        ];

        $this->emitToUser($userId, 'bulk.action.completed', [
            'title' => $titles[$action] ?? 'اكتملت عملية مجمعة',
            'body' => $messages[$action] ?? "اكتملت العملية على {$count} عنصر.",
            'severity' => 'info',
            'url' => $url,
            'action' => $action,
            'count' => $count,
        ]);
    }
}
