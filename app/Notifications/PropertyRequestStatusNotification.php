<?php

namespace App\Notifications;
use App\Models\Property;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PropertyRequestStatusNotification extends Notification
{
        use Queueable;

    protected Property $property;

    public function __construct(Property $property)
    {
        $this->property = $property;
     
    }

    /**
     * قنوات الإشعار
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * تخزين الإشعار في قاعدة البيانات
     */
    public function toDatabase(object $notifiable): array
    {
        $propertyTitle = $this->property->title ?? 'العقار';

        $message = match ($this->property->approval_status) {
            'approved' => "✅ تم قبول طلب إضافة {$propertyTitle}. عقارك الآن مرئي.",
            'rejected' => "❌ تم رفض طلب إضافة {$propertyTitle}.\nالسبب: {$this->property->rejection_reason}",
            'pending'  => "📋 تم استلام طلب إضافة {$propertyTitle} وهو قيد المراجعة.",
            default    => "تم تحديث حالة طلب {$propertyTitle}",
        };

        return [
            'title'            => 'تحديث حالة طلب العقار',
            'message'          => $message,
            'property_id'      => $this->property->id,
            'status'           => $this->property->approval_status,
            'status_text'      => $this->getStatusText(),
            'property_title'   => $propertyTitle,
            'rejection_reason' => $this->property->rejection_reason,
            'type'             => 'property_request_status',
        ];
    }

    /**
     * إرسال الإشعار عبر Firebase
     */
    public function toFirebase(object $notifiable): FirebaseMessage
    {
        $title = 'تحديث حالة طلب العقار';
        
        $body = match ($this->property->approval_status) {
            'approved' => '✅ تم قبول طلب إضافة عقارك',
            'rejected' => '❌ تم رفض طلبك: ' . $this->property->rejection_reason,
            'pending'  => '📋 طلبك قيد المراجعة',
            default    => 'تم تحديث حالة طلبك',
        };

        return (new FirebaseMessage)
            ->setTitle($title)
            ->setBody($body)
            ->setData([
                'property_id' => (string) $this->property->id,
                'status'      => $this->property->approval_status,
                'type'        => 'property_request_status',
            ]);
    }

    /**
     * نص الحالة بالعربي
     */
    private function getStatusText(): string
    {
        return match ($this->property->approval_status) {
            'pending'  => 'قيد المراجعة',
            'approved' => 'مقبول',
            'rejected' => 'مرفوض',
            default    => 'غير معروف',
        };
    }
}