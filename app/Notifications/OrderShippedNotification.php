<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShippedNotification extends Notification
{
    use Queueable;

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $company = data_get($this->order->ship_data, 'express_company', '-');
        $no = data_get($this->order->ship_data, 'express_no', '-');

        return (new MailMessage)
            ->subject('订单已发货 - '.$this->order->no)
            ->greeting(optional($this->order->user)->name.'您好：')
            ->line('您的订单 '.$this->order->no.' 已发货。')
            ->line('物流公司：'.$company)
            ->line('运单号：'.$no)
            ->action('查看订单', route('orders.show', [$this->order->id]))
            ->line('国际物流更新可能略有延迟，请耐心等待。');
    }
}
