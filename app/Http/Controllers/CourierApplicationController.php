<?php

namespace App\Http\Controllers;

use App\Models\CourierApplication;
use Illuminate\Http\Request;

class CourierApplicationController extends Controller
{
    public function create(Request $request)
    {
        abort_unless(is_site_mode_b(), 404);

        $application = CourierApplication::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('id', 'desc')
            ->first();

        return view()->first([
            'b_mode.products.procurement_apply',
            'products.procurement_apply',
        ], [
            'application' => $application,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(is_site_mode_b(), 404);

        $user = $request->user();
        $latest = CourierApplication::query()
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->first();

        if ($latest && $latest->status === CourierApplication::STATUS_PENDING) {
            return redirect()->back()->with('warning', '您的代购资质正在审核中，请耐心等待');
        }

        if ($latest && $latest->status === CourierApplication::STATUS_APPROVED) {
            return redirect()->route('products.index')->with('success', '您已通过代购资质审核，可直接承接任务');
        }

        $validated = $this->validate($request, [
            'real_name' => 'required|string|max:60',
            'phone' => ['required', 'string', 'max:32', 'regex:/^\+?[0-9\-\s]{6,20}$/'],
            'id_card_number' => 'required|string|max:64',
            'flight_ticket' => 'required|image|max:5120',
            'id_card_photo' => 'required|image|max:5120',
        ], [
            'real_name.required' => '请填写真实姓名',
            'phone.required' => '请填写手机号',
            'phone.regex' => '手机号格式不正确',
            'id_card_number.required' => '请填写身份证号',
            'flight_ticket.required' => '请上传机票凭证',
            'flight_ticket.image' => '机票凭证必须是图片',
            'flight_ticket.max' => '机票凭证大小不能超过 5MB',
            'id_card_photo.required' => '请上传证件照片',
            'id_card_photo.image' => '证件照片必须是图片',
            'id_card_photo.max' => '证件照片大小不能超过 5MB',
        ]);

        $flightTicketPath = (string) $request->file('flight_ticket')->store('courier_applications/flight_tickets', 'public');
        $idCardPhotoPath = (string) $request->file('id_card_photo')->store('courier_applications/id_cards', 'public');

        CourierApplication::query()->create([
            'user_id' => (int) $user->id,
            'real_name' => trim((string) $validated['real_name']),
            'phone' => trim((string) $validated['phone']),
            'id_card_number' => trim((string) $validated['id_card_number']),
            'flight_ticket_path' => $flightTicketPath,
            'id_card_photo_path' => $idCardPhotoPath,
            'status' => CourierApplication::STATUS_PENDING,
            'admin_note' => null,
        ]);

        return redirect()->route('procurement.apply')
            ->with('success', '资料提交成功，您的代购资质正在审核中');
    }
}
