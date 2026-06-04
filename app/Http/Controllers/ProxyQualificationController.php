<?php

namespace App\Http\Controllers;

use App\Models\ProxyQualification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProxyQualificationController extends Controller
{
    public function __construct()
    {
        // B站资质审核页面只在B站模式下有效
        $this->middleware(function ($request, $next) {
            abort_unless(is_site_mode_b(), 404);
            return $next($request);
        });
    }

    /**
     * 显示提交资质申请表单，或如果已有申请则显示状态页
     */
    public function create()
    {
        $user = auth()->user();
        $qualification = ProxyQualification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        // 已有申请记录，跳转到状态页
        if ($qualification) {
            return redirect()->route('procurement.qualification.status');
        }

        return view('procurement.qualification_create');
    }

    /**
     * 接收资质申请表单提交
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        // 如果已有待审核或已通过的申请，直接跳状态页
        $existing = ProxyQualification::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [ProxyQualification::STATUS_PENDING, ProxyQualification::STATUS_APPROVED])
            ->first();
        if ($existing) {
            return redirect()->route('procurement.qualification.status');
        }

        $request->validate([
            'id_card_front' => 'required|image|max:5120',
            'id_card_back'  => 'required|image|max:5120',
            'flight_ticket' => 'required|image|max:5120',
            'applicant_note' => 'nullable|string|max:500',
        ], [
            'id_card_front.required' => '请上传身份证正面照片',
            'id_card_front.image'    => '身份证正面必须是图片',
            'id_card_back.required'  => '请上传身份证背面照片',
            'id_card_back.image'     => '身份证背面必须是图片',
            'flight_ticket.required' => '请上传机票凭证照片',
            'flight_ticket.image'    => '机票凭证必须是图片',
        ]);

        $frontPath  = $request->file('id_card_front')->store('qualifications', 'public');
        $backPath   = $request->file('id_card_back')->store('qualifications', 'public');
        $ticketPath = $request->file('flight_ticket')->store('qualifications', 'public');

        ProxyQualification::query()->create([
            'user_id'        => $user->id,
            'id_card_front'  => $frontPath,
            'id_card_back'   => $backPath,
            'flight_ticket'  => $ticketPath,
            'status'         => ProxyQualification::STATUS_PENDING,
            'applicant_note' => $request->input('applicant_note'),
        ]);

        return redirect()->route('procurement.qualification.status')
            ->with('success', '申请已提交，请等待管理员审核（1-3个工作日）。');
    }

    /**
     * 查看当前申请状态
     */
    public function status()
    {
        $user = auth()->user();
        $qualification = ProxyQualification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        return view('procurement.qualification_status', [
            'qualification' => $qualification,
        ]);
    }
}
