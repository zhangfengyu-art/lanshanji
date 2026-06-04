<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderRefundedNotification extends Notification
{
    use Queueable;

    protected $order;
    protected $agreed;
    protected $reason;

    public function __construct(Order $order, $agreed, $reason = '')
    {
        $this->order = $order;
        $this->agreed = (bool) $agreed;
        $this->reason = (string) $reason;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $mail = (new MailMessage)
            ->subject($this->agreed ? '退款已处理 - '.$this->order->no : '退款申请未通过 - '.$this->order->no)
            ->greeting(optional($this->order->user)->name.'您好：');

        if ($this->agreed) {
            $mail->line('您的订单 '.$this->order->no.' 退款已处理，款项将按支付渠道原路退回。');
        } else {
            $mail->line('您的订单 '.$this->order->no.' 退款申请未通过。');
            if ($this->reason !== '') {
                $mail->line('说明：'.$this->reason);
            }
        }

        return $mail
            ->action('查看订单', route('orders.show', [$this->order->id]))
            ->line('如有疑问请通过客户反馈联系我们。');
    }
}
