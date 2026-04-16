<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Http\Requests\StoreSupportFeedbackRequest;
use App\Models\Order;
use App\Models\SupportFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SupportFeedbacksController extends Controller
{
    const MAX_DAILY_SUBMISSIONS = 5;
    const MAX_PENDING_FEEDBACKS = 3;
    const SUBMIT_COOLDOWN_MINUTES = 2;

    public function replies(Request $request)
    {
        $submitPolicy = $this->buildSubmitPolicy((int) $request->user()->id);

        $feedbacks = SupportFeedback::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('support_feedbacks.replies', [
            'feedbacks' => $feedbacks,
            'questionTypeMap' => SupportFeedback::$questionTypeMap,
            'statusMap' => SupportFeedback::$statusMap,
            'submitPolicy' => $submitPolicy,
        ]);
    }

    public function replyDetail(Request $request, $supportFeedbackId)
    {
        $feedback = SupportFeedback::query()
            ->where('id', $supportFeedbackId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return view('support_feedbacks.reply_detail', [
            'feedback' => $feedback,
            'questionTypeMap' => SupportFeedback::$questionTypeMap,
            'statusMap' => SupportFeedback::$statusMap,
        ]);
    }

    public function create(Request $request)
    {
        $submitPolicy = $this->buildSubmitPolicy((int) $request->user()->id);
        $orderNo = trim((string) $request->query('order_no', ''));
        $isLocked = $request->query('locked') == '1';

        if ($orderNo !== '') {
            $exists = Order::query()
                ->where('no', $orderNo)
                ->where('user_id', $request->user()->id)
                ->exists();

            if (!$exists) {
                throw new InvalidRequestException('订单不存在或无权限访问');
            }
        }

        if ($isLocked && $orderNo !== '') {
            session(['support_feedback.locked_order_no' => $orderNo]);
        } else {
            session()->forget('support_feedback.locked_order_no');
            $isLocked = false;
        }

        return view('support_feedbacks.create', [
            'orderNo' => $orderNo,
            'isLocked' => $isLocked,
            'questionTypeMap' => SupportFeedback::$questionTypeMap,
            'submitPolicy' => $submitPolicy,
        ]);
    }

    public function store(StoreSupportFeedbackRequest $request)
    {
        $submitPolicy = $this->buildSubmitPolicy((int) $request->user()->id);
        if (!$submitPolicy['can_submit']) {
            throw new InvalidRequestException($submitPolicy['block_reasons'][0] ?? '提交过于频繁，请稍后再试');
        }

        $lockedOrderNo = session('support_feedback.locked_order_no');
        $isLocked = $request->input('locked_order_no') == '1' && !empty($lockedOrderNo);
        $orderNo = trim((string) $request->input('order_no'));

        if ($isLocked && $orderNo !== $lockedOrderNo) {
            throw new InvalidRequestException('订单编号已锁定，不可修改');
        }

        $user = $request->user();
        $contactNameRaw = (string) $user->name;
        $contactName = function_exists('mb_substr')
            ? mb_substr($contactNameRaw, 0, 30)
            : substr($contactNameRaw, 0, 30);
        $userPhone = trim((string) data_get($user, 'phone', ''));
        $contactPhoneRaw = $userPhone !== '' ? $userPhone : 'N/A';
        $contactPhone = function_exists('mb_substr')
            ? mb_substr($contactPhoneRaw, 0, 20)
            : substr($contactPhoneRaw, 0, 20);

        $imagePaths = [];
        $images = $request->file('images', []);
        if ($images instanceof UploadedFile) {
            $images = [$images];
        }
        if (!is_array($images)) {
            $images = [];
        }
        foreach ($images as $image) {
            $imagePaths[] = Storage::disk('public')->put('images/support-feedbacks', $image);
        }

        SupportFeedback::query()->create([
            'user_id' => $user->id,
            'order_no' => $orderNo,
            'contact_name' => $contactName,
            'contact_phone' => $contactPhone,
            'question_type' => $request->input('question_type'),
            'message' => trim((string) $request->input('message')),
            'images' => $imagePaths,
            'status' => SupportFeedback::STATUS_PENDING_REVIEW,
        ]);

        session()->forget('support_feedback.locked_order_no');

        return redirect()->back()->with('success', '反馈已提交，客服将尽快处理。');
    }

    private function buildSubmitPolicy($userId)
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();

        $dailyCount = (int) SupportFeedback::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $todayStart)
            ->count();

        $pendingCount = (int) SupportFeedback::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                SupportFeedback::STATUS_PENDING_REVIEW,
                SupportFeedback::STATUS_UNDER_INVESTIGATION,
            ])
            ->count();

        $lastFeedback = SupportFeedback::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->first(['created_at']);

        $nextAvailableAt = null;
        $cooldownSeconds = 0;
        if ($lastFeedback && $lastFeedback->created_at) {
            $nextAvailableAt = $lastFeedback->created_at->copy()->addMinutes(self::SUBMIT_COOLDOWN_MINUTES);
            $cooldownSeconds = max(0, $now->diffInSeconds($nextAvailableAt, false));
        }

        $blockReasons = [];
        if ($dailyCount >= self::MAX_DAILY_SUBMISSIONS) {
            $blockReasons[] = '今日咨询次数已达上限（' . self::MAX_DAILY_SUBMISSIONS . '次），请明天再提交。';
        }
        if ($pendingCount >= self::MAX_PENDING_FEEDBACKS) {
            $blockReasons[] = '当前仍有' . $pendingCount . '条问题待处理，请等待客服回复后再继续提交。';
        }
        if ($cooldownSeconds > 0) {
            $blockReasons[] = '提交过于频繁，请于 ' . $nextAvailableAt->format('H:i:s') . ' 后再试。';
        }

        return [
            'can_submit' => count($blockReasons) === 0,
            'max_daily_submissions' => self::MAX_DAILY_SUBMISSIONS,
            'max_pending_feedbacks' => self::MAX_PENDING_FEEDBACKS,
            'submit_cooldown_minutes' => self::SUBMIT_COOLDOWN_MINUTES,
            'daily_count' => $dailyCount,
            'daily_remaining' => max(0, self::MAX_DAILY_SUBMISSIONS - $dailyCount),
            'pending_count' => $pendingCount,
            'cooldown_seconds' => $cooldownSeconds,
            'next_available_at' => $nextAvailableAt,
            'block_reasons' => $blockReasons,
        ];
    }
}
