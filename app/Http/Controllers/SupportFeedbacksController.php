<?php

namespace App\Http\Controllers;

use App\Models\SupportFeedback;
use Illuminate\Http\Request;

class SupportFeedbacksController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('email_verified');
        $this->middleware('throttle:6,1')->only('store');
    }

    public function index()
    {
        return $this->renderFeedbackList();
    }

    public function create(Request $request)
    {
        $user = request()->user();
        $blocked = SupportFeedback::submissionBlockedMessage($user->id);
        $types = SupportFeedback::questionTypeOptions();
        $questionType = $request->query('question_type', '');
        if (!array_key_exists($questionType, $types)) {
            $questionType = '';
        }

        return view('support.feedbacks.create', [
            'questionTypes' => $types,
            'defaultName' => $user->name,
            'defaultPhone' => data_get($user, 'phone', ''),
            'defaultOrderNo' => trim((string) $request->query('order_no', '')),
            'defaultQuestionType' => $questionType,
            'defaultMessage' => trim((string) $request->query('message', '')),
            'submitBlockedMessage' => $blocked,
            'minIntervalMinutes' => (int) ceil(SupportFeedback::SUBMIT_MIN_INTERVAL_SECONDS / 60),
            'dailyMax' => SupportFeedback::SUBMIT_DAILY_MAX,
        ]);
    }

    public function store(Request $request)
    {
        $userId = (int) $request->user()->id;
        $blocked = SupportFeedback::submissionBlockedMessage($userId);
        if ($blocked !== null) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['rate_limit' => $blocked]);
        }

        $data = $this->validate($request, [
            'contact_name' => ['required', 'string', 'max:64'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'order_no' => ['nullable', 'string', 'max:64'],
            'question_type' => ['required', 'string', 'max:64'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], [
            'contact_name' => '联系人',
            'contact_phone' => '联系电话',
            'order_no' => '订单号',
            'question_type' => '问题类型',
            'message' => '反馈内容',
        ]);

        $types = SupportFeedback::questionTypeOptions();
        if (!array_key_exists($data['question_type'], $types)) {
            return redirect()->back()->withInput()->withErrors([
                'question_type' => '请选择有效的问题类型',
            ]);
        }

        $message = trim($data['message']);
        $duplicate = SupportFeedback::query()
            ->where('user_id', $userId)
            ->where('message', $message)
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();
        if ($duplicate) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['message' => '24 小时内已提交过相同内容的反馈，请勿重复提交']);
        }

        SupportFeedback::query()->create([
            'user_id' => $userId,
            'contact_name' => $data['contact_name'],
            'contact_phone' => $data['contact_phone'],
            'order_no' => trim((string) $data['order_no']) ?: null,
            'question_type' => $data['question_type'],
            'message' => $message,
            'status' => SupportFeedback::STATUS_PENDING,
        ]);

        return redirect()
            ->route('support.feedbacks.index')
            ->with('status', '反馈已提交，客服回复后可在本页查看。');
    }

    public function replies()
    {
        return redirect()->route('support.feedbacks.index');
    }

    protected function renderFeedbackList()
    {
        $feedbacks = SupportFeedback::query()
            ->where('user_id', request()->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $rateLimitMessage = null;
        if (session('errors') && session('errors')->has('rate_limit')) {
            $rateLimitMessage = session('errors')->first('rate_limit');
        }

        return view('support.feedbacks.index', [
            'feedbacks' => $feedbacks,
            'minIntervalMinutes' => (int) ceil(SupportFeedback::SUBMIT_MIN_INTERVAL_SECONDS / 60),
            'dailyMax' => SupportFeedback::SUBMIT_DAILY_MAX,
            'rateLimitMessage' => $rateLimitMessage,
        ]);
    }
}
