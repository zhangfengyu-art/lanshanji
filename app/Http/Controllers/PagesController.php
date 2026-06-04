<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PagesController extends Controller
{
    public function root()
    {
        return view('pages.root');
    }

    public function orderFlow()
    {
        return view('pages.order_flow');
    }

    public function changeExchangeReturn()
    {
        return view('pages.change_exchange_return');
    }

    public function faq()
    {
        return view('pages.faq');
    }

    public function emailVerifyNotice(Request $request)
    {
        return view('pages.email_verify_notice');
    }
}
