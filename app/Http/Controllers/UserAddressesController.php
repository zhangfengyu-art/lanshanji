<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAddress;
use App\Http\Requests\UserAddressRequest;

class UserAddressesController extends Controller
{
    public function index(Request $request)
    {
        return view($this->addressView('index'), [
            'addresses' => $request->user()->addresses()
                ->orderBy('is_default', 'desc')
                ->orderBy('last_used_at', 'desc')
                ->get(),
        ]);
    }

    public function create()
    {
        return view($this->addressView('create_and_edit'), [
            'address' => new UserAddress(),
            'redirectTo' => (string) request()->query('redirect', ''),
        ]);
    }

    public function store(UserAddressRequest $request)
    {
        $isDefault = $this->isDefaultRequested($request);
        if (!$request->user()->addresses()->exists()) {
            $isDefault = true;
        }
        if ($isDefault) {
            $request->user()->addresses()->update(['is_default' => 0]);
        }

        $address = $request->user()->addresses()->create(array_merge($request->only([
            'province',
            'city',
            'district',
            'address',
            'contact_name',
            'contact_phone',
            'id_card',
        ]), [
            'zip' => (int) $request->input('zip', 0),
            'is_default' => $isDefault ? 1 : 0,
        ]));

        if ($redirectTo = $this->resolveRedirectTo($request)) {
            return redirect()->to($redirectTo)->with('success', '地址保存成功，已返回支付页面。');
        }

        return redirect()
            ->route('user_addresses.edit', ['user_address' => $address->id])
            ->with('success', '地址保存成功，可继续编辑。');
    }

    public function edit(UserAddress $user_address)
    {
        $this->authorize('own', $user_address);

        return view($this->addressView('create_and_edit'), [
            'address' => $user_address,
            'redirectTo' => (string) request()->query('redirect', ''),
        ]);
    }

    public function update(UserAddress $user_address, UserAddressRequest $request)
    {
        $this->authorize('own', $user_address);

        $isDefault = $this->isDefaultRequested($request);
        if ($isDefault) {
            $request->user()->addresses()
                ->where('id', '<>', $user_address->id)
                ->update(['is_default' => 0]);
        }

        $user_address->update(array_merge($request->only([
            'province',
            'city',
            'district',
            'address',
            'contact_name',
            'contact_phone',
            'id_card',
        ]), [
            'zip' => (int) $request->input('zip', 0),
            'is_default' => $isDefault ? 1 : 0,
        ]));

        if ($redirectTo = $this->resolveRedirectTo($request)) {
            return redirect()->to($redirectTo)->with('success', '地址保存成功，已返回支付页面。');
        }

        return redirect()
            ->route('user_addresses.edit', ['user_address' => $user_address->id])
            ->with('success', '地址保存成功，可继续编辑。');
    }

    public function destroy(UserAddress $user_address)
    {
        $this->authorize('own', $user_address);
        $user_address->delete();

        return [];
    }

    protected function isDefaultRequested(Request $request)
    {
        $value = $request->input('is_default');

        return in_array($value, ['1', 1, true, 'on', 'yes'], true);
    }

    protected function resolveRedirectTo(Request $request)
    {
        $redirectTo = trim((string) $request->input('redirect', ''));
        if ($redirectTo === '') {
            return null;
        }

        if (strpos($redirectTo, '/') !== 0 && strpos($redirectTo, url('/')) !== 0) {
            return null;
        }

        return $redirectTo;
    }

    protected function addressView($name)
    {
        if (is_site_mode_b()) {
            return 'b_mode.user_addresses.' . $name;
        }

        return 'user_addresses.' . $name;
    }
}
