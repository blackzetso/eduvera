<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $sendEndpoint;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url', 'http://localhost:3000');
        $this->apiKey = config('services.whatsapp.api_key', '');
        $this->sendEndpoint = config('services.whatsapp.send_endpoint', '/api/send-message');
    }

    /**
     * Send WhatsApp message
     *
     * @param string $phone Phone number in international format (e.g., 201234567890)
     * @param string $message Message content
     * @param bool $async Send asynchronously via queue
     * @return bool
     */
    public function sendMessage(string $phone, string $message, bool $async = true): bool
    {
        if ($async) {
            // Dispatch to queue for async processing
            dispatch(function () use ($phone, $message) {
                $this->sendMessageSync($phone, $message);
            })->afterResponse();

            return true;
        }

        return $this->sendMessageSync($phone, $message);
    }

    /**
     * Send WhatsApp message synchronously
     *
     * @param string $phone
     * @param string $message
     * @return bool
     */
    protected function sendMessageSync(string $phone, string $message): bool
    {
        try {
            // Format phone number (remove + if exists, ensure it starts with country code)
            $phone = $this->formatPhoneNumber($phone);

            $url = rtrim($this->apiUrl, '/') . '/' . ltrim($this->sendEndpoint, '/');

            $payload = [
                'jid' => $phone . '@s.whatsapp.net',
                'text' => $message,
            ];

            $headers = ['Content-Type' => 'application/json'];
            if ($this->apiKey) {
                $headers['Authorization'] = 'Bearer ' . $this->apiKey;
            }

            $response = Http::timeout(30)
                ->withOptions(['verify' => false]) // Disable SSL verification (for testing only)
                ->withHeaders($headers)
                ->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Format phone number to international format
     *
     * @param string $phone
     * @return string
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove + if exists
        $phone = ltrim($phone, '+');

        // If phone doesn't start with country code, assume it's Egyptian (20)
        if (!preg_match('/^20/', $phone) && strlen($phone) == 10) {
            $phone = '20' . $phone;
        }

        return $phone;
    }

    /**
     * Send assignment notification to teacher
     *
     * @param \App\Models\User $teacher
     * @param \App\Models\TimetablePeriod $period
     * @param \App\Models\Subject $subject
     * @param string $type 'main' or 'backup'
    /**
     * Send assignment notification to teacher
     *
     * @param \App\Models\User $teacher
     * @param \App\Models\TimetablePeriod $period
     * @param \App\Models\Subject $subject
     * @param string $type 'main' or 'backup'
     * @return bool
     */
    public function sendAssignmentNotification($teacher, $period, $subject, $type = 'main'): bool
    {
        if (!$teacher->phone) {
            return false;
        }

        // Extract data before async to avoid relationship issues
        // Use getRelation() to access loaded relationships directly
        $teacherName = $teacher->name;
        $teacherPhone = $teacher->phone;
        $subjectName = $subject->name;

        $dayRelation = $period->getRelation('day');
        $categoryRelation = $period->getRelation('category');

        $dayName = ($dayRelation && isset($dayRelation->day_name)) ? $dayRelation->day_name : 'غير محدد';
        $categoryName = ($categoryRelation && isset($categoryRelation->name)) ? $categoryRelation->name : 'غير محدد';
        $timeFrom = $period->time_from;
        $timeTo = $period->time_to;
        $periodNumber = $period->period_number;

        if ($type === 'backup') {
            // Special message for backup assignments
            $message = "📢 *تنبيه تعيين احتياطي*\n\n";
            $message .= "مرحباً {$teacherName}\n\n";
            $message .= "تم تعيينك لحصة احتياطي ✅\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📚 *المادة:* {$subjectName}\n";
            $message .= "📅 *اليوم:* {$dayName}\n";
            $message .= "⏰ *الوقت:* {$timeFrom} - {$timeTo}\n";
            $message .= "🎓 *الصف:* {$categoryName}\n";
            $message .= "🔢 *رقم الحصة:* {$periodNumber}\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "⚠️ *ملاحظة:* هذه حصة احتياطية. يرجى التأكد من توفرك في هذا الموعد.\n\n";
            $message .= "شكراً لتعاونك! 🙏";
        } else {
            // Regular message for main assignments
            $message = "📢 *تنبيه تعيين حصة*\n\n";
            $message .= "مرحباً {$teacherName}\n\n";
            $message .= "تم تعيينك كمدرس أساسي للحصة التالية:\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📚 *المادة:* {$subjectName}\n";
            $message .= "📅 *اليوم:* {$dayName}\n";
            $message .= "⏰ *الوقت:* {$timeFrom} - {$timeTo}\n";
            $message .= "🎓 *الصف:* {$categoryName}\n";
            $message .= "🔢 *رقم الحصة:* {$periodNumber}\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "نتمنى لك يوم دراسي مثمر! 🌟";
        }

        return $this->sendMessage($teacherPhone, $message);
    }
}
