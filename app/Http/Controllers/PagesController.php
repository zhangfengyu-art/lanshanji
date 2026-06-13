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
        return view(is_site_mode_b() ? 'pages.order_flow_b' : 'pages.order_flow');
    }

    public function changeExchangeReturn()
    {
        return view(is_site_mode_b() ? 'pages.change_exchange_return_b' : 'pages.change_exchange_return');
    }

    public function faq()
    {
        return view(is_site_mode_b() ? 'pages.faq_b' : 'pages.faq');
    }

    public function emailVerifyNotice(Request $request)
    {
        return view('pages.email_verify_notice');
    }
}
